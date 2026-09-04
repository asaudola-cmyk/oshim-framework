<?php
declare(strict_types=1);

namespace Oshim\Tests\Harness;

class DnsZoneResolverHelper
{
    private array $zones = [];

    public function addZone(string $zone): void
    {
        $this->zones[strtolower(trim($zone, '.'))] = [];
    }

    public function addRecord(string $domain, string $type, mixed $data): void
    {
        $d = strtolower(trim($domain, '.'));
        $t = strtoupper(trim($type));
        $this->zones[$d][$t][] = $data;
    }

    public function resolveQuery(string $queryWire): string
    {
        $parsed = DnsWireResponse::parse($queryWire);
        $q = $parsed->getQuestions()[0] ?? null;
        if (!$q) {
            return (new MockDnsClient())->buildResponsePacket($parsed->getId(), '', 'A', [], true, 1);
        }

        $qname = strtolower(trim($q['name'], '.'));
        $qtype = strtoupper($q['type']);
        $dnsClient = new MockDnsClient();

        if (!isset($this->zones[$qname])) {
            return $dnsClient->buildResponsePacket($parsed->getId(), $qname, $qtype, [], true, 3);
        }

        $records = $this->zones[$qname][$qtype] ?? [];
        return $dnsClient->buildResponsePacket($parsed->getId(), $qname, $qtype, $records, true, 0, 300);
    }
}
