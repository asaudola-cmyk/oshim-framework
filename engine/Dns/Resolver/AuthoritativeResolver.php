<?php
declare(strict_types=1);

namespace Oshim\Dns\Resolver;

use Oshim\Dns\Packet\DnsPacket;
use Oshim\Dns\Records\RecordType;
use Oshim\Dns\Records\ResourceRecord;
use Oshim\Dns\Wire\DnsHeader;
use Oshim\Dns\Wire\DnsQuestion;
use Oshim\Dns\Zone\Zone;
use Oshim\Dns\Zone\ZoneRepositoryInterface;

/**
 * RFC 1034 / 1035 / 2308 Authoritative Query Resolution Engine.
 */
class AuthoritativeResolver
{
    private ZoneRepositoryInterface $repository;

    public function __construct(ZoneRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getRepository(): ZoneRepositoryInterface
    {
        return $this->repository;
    }

    /**
     * Resolves an incoming DNS query packet and produces a response packet.
     */
    public function resolve(DnsPacket $queryPacket): DnsPacket
    {
        $header = clone $queryPacket->getHeader();
        $header->qr = true; // Response
        $header->ra = false; // Authoritative only, recursion not available

        $responsePacket = new DnsPacket($header, $queryPacket->getQuestions());

        // Check Opcode
        if ($header->opcode !== DnsHeader::OPCODE_QUERY) {
            $header->rcode = DnsHeader::RCODE_NOTIMP;
            $header->aa = false;
            return $responsePacket;
        }

        // Check Question count
        if (empty($queryPacket->getQuestions())) {
            $header->rcode = DnsHeader::RCODE_FORMERR;
            $header->aa = false;
            return $responsePacket;
        }

        $question = $queryPacket->getQuestions()[0];
        $qname = strtolower(trim($question->getName(), '.'));
        $qtype = $question->getType();

        // 1. Find best matching authoritative zone
        $zone = $this->repository->findBestMatchingZone($qname);
        if ($zone === null) {
            // Not authoritative for this domain -> REFUSED (RCODE 5)
            $header->rcode = DnsHeader::RCODE_REFUSED;
            $header->aa = false;
            return $responsePacket;
        }

        $header->aa = true; // Authoritative answer

        // 2. Exact match check
        $nodeRecords = $zone->findRecords($qname);

        if (!empty($nodeRecords)) {
            // Check if node is a CNAME alias
            $cnameRecords = array_values(array_filter($nodeRecords, fn(ResourceRecord $r) => $r->getType() === RecordType::CNAME));

            if (!empty($cnameRecords) && $qtype !== RecordType::CNAME && $qtype !== RecordType::ANY) {
                $cname = $cnameRecords[0];
                $responsePacket->addAnswer($cname);

                // Local CNAME chasing within repository
                $target = strtolower(trim((string)$cname->getData(), '.'));
                $targetZone = $this->repository->findBestMatchingZone($target);
                if ($targetZone !== null) {
                    $targetRecords = $targetZone->findRecords($target, $qtype);
                    foreach ($targetRecords as $tr) {
                        $responsePacket->addAnswer($tr);
                    }
                }

                $header->rcode = DnsHeader::RCODE_NOERROR;
                return $responsePacket;
            }

            // Filter by requested QTYPE or ANY
            $matchingRecords = array_values(array_filter(
                $nodeRecords,
                fn(ResourceRecord $r) => $qtype === RecordType::ANY || $r->getType() === $qtype
            ));

            if (!empty($matchingRecords)) {
                foreach ($matchingRecords as $r) {
                    $responsePacket->addAnswer($r);
                }
                $header->rcode = DnsHeader::RCODE_NOERROR;
                return $responsePacket;
            }

            // NODATA (RFC 2308): Domain exists, but requested record type does not
            $header->rcode = DnsHeader::RCODE_NOERROR;
            $soa = $zone->getSoaRecord();
            if ($soa !== null) {
                $negativeTtl = is_array($soa->getData()) ? ($soa->getData()['minimum'] ?? 300) : 300;
                $soaNegative = clone $soa;
                $soaNegative->setTtl((int)$negativeTtl);
                $responsePacket->addAuthority($soaNegative);
            }
            return $responsePacket;
        }

        // 3. Wildcard matching (e.g. *.example.com)
        $wildcardRecords = $this->findWildcardMatch($zone, $qname, $qtype);
        if (!empty($wildcardRecords)) {
            foreach ($wildcardRecords as $wr) {
                // Synthesize record with queried QNAME
                $synthesized = new ResourceRecord($qname, $wr->getType(), $wr->getData(), $wr->getTtl(), $wr->getClass());
                $responsePacket->addAnswer($synthesized);
            }
            $header->rcode = DnsHeader::RCODE_NOERROR;
            return $responsePacket;
        }

        // 4. NXDOMAIN (RCODE 3)
        $header->rcode = DnsHeader::RCODE_NXDOMAIN;
        $soa = $zone->getSoaRecord();
        if ($soa !== null) {
            $negativeTtl = is_array($soa->getData()) ? ($soa->getData()['minimum'] ?? 300) : 300;
            $soaNegative = clone $soa;
            $soaNegative->setTtl((int)$negativeTtl);
            $responsePacket->addAuthority($soaNegative);
        }

        return $responsePacket;
    }

    /**
     * Resolves a binary wire query and returns the binary response.
     */
    public function resolveQueryWire(string $queryWire, string $transport = 'udp', int $maxUdpSize = 512): string
    {
        try {
            $queryPacket = DnsPacket::parse($queryWire);
            $responsePacket = $this->resolve($queryPacket);
        } catch (\Throwable $e) {
            // Malformed query packet -> FORMERR (RCODE 1)
            $header = new DnsHeader(0, true, 0, false, false, false, false, DnsHeader::RCODE_FORMERR);
            $responsePacket = new DnsPacket($header);
        }

        $limit = (strtolower($transport) === 'tcp') ? 0 : $maxUdpSize;
        return $responsePacket->serialize($limit);
    }

    /**
     * Searches for wildcard records (*.domain) matching a query.
     *
     * @return list<ResourceRecord>
     */
    private function findWildcardMatch(Zone $zone, string $qname, int $qtype): array
    {
        $zoneName = $zone->getName();
        if ($qname === $zoneName) {
            return [];
        }

        $parts = explode('.', $qname);
        $count = count($parts);

        for ($i = 1; $i < $count; $i++) {
            $wildcardOwner = '*.' . implode('.', array_slice($parts, $i));
            $records = $zone->findRecords($wildcardOwner, $qtype);
            if (!empty($records)) {
                return $records;
            }
        }

        return [];
    }
}
