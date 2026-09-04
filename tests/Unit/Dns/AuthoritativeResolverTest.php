<?php
declare(strict_types=1);

namespace Oshim\Tests\Unit\Dns;

use Oshim\Dns\Packet\DnsPacket;
use Oshim\Dns\Records\RecordType;
use Oshim\Dns\Records\ResourceRecord;
use Oshim\Dns\Resolver\AuthoritativeResolver;
use Oshim\Dns\Wire\DnsHeader;
use Oshim\Dns\Wire\DnsQuestion;
use Oshim\Dns\Zone\MemoryZoneRepository;
use Oshim\Dns\Zone\Zone;
use Oshim\Tests\Harness\TestCase;
use Oshim\Tests\Harness\MockDnsClient;
use Oshim\Tests\Harness\DnsWireResponse;

class AuthoritativeResolverTest extends TestCase
{
    private MemoryZoneRepository $repo;
    private AuthoritativeResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new MemoryZoneRepository();
        $this->resolver = new AuthoritativeResolver($this->repo);

        $zone = new Zone('resolver-test.com', 3600, 2026082901, [
            ResourceRecord::soa('resolver-test.com', 'ns1.resolver-test.com', 'admin.resolver-test.com', 2026082901, 3600, 1800, 604800, 300),
            ResourceRecord::ns('resolver-test.com', 'ns1.resolver-test.com'),
            ResourceRecord::a('resolver-test.com', '192.0.2.1'),
            ResourceRecord::aaaa('resolver-test.com', '2001:db8::1'),
            ResourceRecord::mx('resolver-test.com', 10, 'mail.resolver-test.com'),
            ResourceRecord::txt('resolver-test.com', 'v=spf1 -all'),
            ResourceRecord::cname('alias.resolver-test.com', 'target.resolver-test.com'),
            ResourceRecord::a('target.resolver-test.com', '192.0.2.99'),
            ResourceRecord::a('*.wild.resolver-test.com', '192.0.2.200'),
        ]);

        $this->repo->saveZone($zone);
    }

    public function testExactMatchApexResolution(): void
    {
        $queryPacket = new DnsPacket(
            new DnsHeader(1001, false, 0, false, false, true),
            [new DnsQuestion('resolver-test.com', RecordType::A)]
        );

        $resp = $this->resolver->resolve($queryPacket);
        $this->assertTrue($resp->getHeader()->isResponse());
        $this->assertTrue($resp->getHeader()->isAuthoritative());
        $this->assertSame(DnsHeader::RCODE_NOERROR, $resp->getHeader()->getRcode());
        $this->assertCount(1, $resp->getAnswers());
        $this->assertSame('192.0.2.1', $resp->getAnswers()[0]->getData());
    }

    public function testCnameChasingResolution(): void
    {
        $queryPacket = new DnsPacket(
            new DnsHeader(1002, false, 0, false, false, true),
            [new DnsQuestion('alias.resolver-test.com', RecordType::A)]
        );

        $resp = $this->resolver->resolve($queryPacket);
        $this->assertSame(DnsHeader::RCODE_NOERROR, $resp->getHeader()->getRcode());
        $this->assertCount(2, $resp->getAnswers());

        // First answer is CNAME
        $this->assertSame(RecordType::CNAME, $resp->getAnswers()[0]->getType());
        $this->assertSame('target.resolver-test.com', $resp->getAnswers()[0]->getData());

        // Second answer is chased A record
        $this->assertSame(RecordType::A, $resp->getAnswers()[1]->getType());
        $this->assertSame('192.0.2.99', $resp->getAnswers()[1]->getData());
    }

    public function testWildcardResolution(): void
    {
        $queryPacket = new DnsPacket(
            new DnsHeader(1003, false, 0, false, false, true),
            [new DnsQuestion('dynamic.client.wild.resolver-test.com', RecordType::A)]
        );

        $resp = $this->resolver->resolve($queryPacket);
        $this->assertSame(DnsHeader::RCODE_NOERROR, $resp->getHeader()->getRcode());
        $this->assertCount(1, $resp->getAnswers());
        $this->assertSame('dynamic.client.wild.resolver-test.com', $resp->getAnswers()[0]->getName());
        $this->assertSame('192.0.2.200', $resp->getAnswers()[0]->getData());
    }

    public function testNoDataResponseIncludesSoaInAuthority(): void
    {
        // Query PTR for apex which only has A, AAAA, MX, TXT
        $queryPacket = new DnsPacket(
            new DnsHeader(1004, false, 0, false, false, true),
            [new DnsQuestion('resolver-test.com', RecordType::PTR)]
        );

        $resp = $this->resolver->resolve($queryPacket);
        $this->assertSame(DnsHeader::RCODE_NOERROR, $resp->getHeader()->getRcode());
        $this->assertCount(0, $resp->getAnswers()); // NODATA
        $this->assertCount(1, $resp->getAuthorities()); // SOA negative caching
        $this->assertSame(RecordType::SOA, $resp->getAuthorities()[0]->getType());
        $this->assertSame(300, $resp->getAuthorities()[0]->getTtl());
    }

    public function testNxDomainResponseIncludesSoaInAuthority(): void
    {
        $queryPacket = new DnsPacket(
            new DnsHeader(1005, false, 0, false, false, true),
            [new DnsQuestion('missing-record.resolver-test.com', RecordType::A)]
        );

        $resp = $this->resolver->resolve($queryPacket);
        $this->assertSame(DnsHeader::RCODE_NXDOMAIN, $resp->getHeader()->getRcode());
        $this->assertCount(0, $resp->getAnswers());
        $this->assertCount(1, $resp->getAuthorities());
        $this->assertSame(RecordType::SOA, $resp->getAuthorities()[0]->getType());
    }

    public function testRefusedResponseForNonHostedZone(): void
    {
        $queryPacket = new DnsPacket(
            new DnsHeader(1006, false, 0, false, false, true),
            [new DnsQuestion('external-zone.org', RecordType::A)]
        );

        $resp = $this->resolver->resolve($queryPacket);
        $this->assertSame(DnsHeader::RCODE_REFUSED, $resp->getHeader()->getRcode());
        $this->assertFalse($resp->getHeader()->isAuthoritative());
    }

    public function testResolveQueryWireEndToEnd(): void
    {
        $dnsClient = new MockDnsClient();
        $queryWire = $dnsClient->buildQueryPacket('resolver-test.com', 'AAAA', 5001);

        $responseWire = $this->resolver->resolveQueryWire($queryWire, 'udp', 512);
        $parsed = DnsWireResponse::parse($responseWire);

        $parsed->assertNoError();
        $parsed->assertAuthoritative();
        $parsed->assertHasRecord('AAAA', '2001:db8::1');
    }
}
