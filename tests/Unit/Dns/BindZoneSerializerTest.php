<?php
declare(strict_types=1);

namespace Oshim\Tests\Unit\Dns;

use Oshim\Dns\Parser\BindZoneParser;
use Oshim\Dns\Parser\BindZoneSerializer;
use Oshim\Dns\Records\RecordType;
use Oshim\Dns\Records\ResourceRecord;
use Oshim\Dns\Zone\Zone;
use Oshim\Tests\Harness\TestCase;

class BindZoneSerializerTest extends TestCase
{
    public function testSerializeZoneToBindFormat(): void
    {
        $zone = new Zone('testzone.cloud', 3600, 2026082901, [
            ResourceRecord::soa('testzone.cloud', 'ns1.testzone.cloud', 'admin.testzone.cloud', 2026082901),
            ResourceRecord::ns('testzone.cloud', 'ns1.testzone.cloud'),
            ResourceRecord::ns('testzone.cloud', 'ns2.testzone.cloud'),
            ResourceRecord::a('testzone.cloud', '198.51.100.1'),
            ResourceRecord::aaaa('testzone.cloud', '2001:db8:100::1'),
            ResourceRecord::mx('testzone.cloud', 10, 'mail.testzone.cloud'),
            ResourceRecord::cname('www.testzone.cloud', 'testzone.cloud'),
            ResourceRecord::txt('testzone.cloud', 'v=spf1 -all'),
            ResourceRecord::caa('testzone.cloud', 0, 'issue', 'letsencrypt.org'),
        ]);

        $output = BindZoneSerializer::serialize($zone);

        $this->assertStringContains('$ORIGIN testzone.cloud.', $output);
        $this->assertStringContains('$TTL 3600', $output);
        $this->assertStringContains('SOA', $output);
        $this->assertStringContains('2026082901 ; Serial', $output);
        $this->assertStringContains('NS', $output);
        $this->assertStringContains('198.51.100.1', $output);
        $this->assertStringContains('2001:db8:100::1', $output);
        $this->assertStringContains('CNAME', $output);
        $this->assertStringContains('"v=spf1 -all"', $output);
        $this->assertStringContains('CAA', $output);

        // Round-trip parse
        $reparsedZone = BindZoneParser::parse($output);
        $this->assertSame('testzone.cloud', $reparsedZone->getName());
        $this->assertCount(1, $reparsedZone->findRecords('testzone.cloud', RecordType::A));
        $this->assertSame('198.51.100.1', $reparsedZone->findRecords('testzone.cloud', RecordType::A)[0]->getData());
        $this->assertCount(2, $reparsedZone->getNsRecords());
    }
}
