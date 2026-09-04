<?php
declare(strict_types=1);

namespace Oshim\Tests\Unit\Dns;

use Oshim\Dns\Exceptions\BindParseException;
use Oshim\Dns\Parser\BindZoneParser;
use Oshim\Dns\Records\RecordType;
use Oshim\Tests\Harness\TestCase;

class BindZoneParserTest extends TestCase
{
    public function testParseStandardBindZoneFile(): void
    {
        $zoneText = <<<BIND
; BIND Master Zone File
\$ORIGIN example.com.
\$TTL 1h

@       IN      SOA     ns1.example.com. hostmaster.example.com. (
                        2026082901 ; Serial
                        3600       ; Refresh (1 hour)
                        1800       ; Retry (30 mins)
                        604800     ; Expire (1 week)
                        86400      ; Minimum TTL (1 day)
)

; Nameservers
@       IN      NS      ns1.example.com.
@       IN      NS      ns2.example.com.

; Mail Exchangers
@       IN      MX      10 mail.example.com.

; Host Addresses
@       IN      A       192.0.2.1
@       IN      AAAA    2001:db8::1
mail    IN      A       192.0.2.25
www     IN      CNAME   @

; TXT Records with internal semicolons
@       IN      TXT     "v=spf1 include:_spf.example.com ~all"
_dmarc  IN      TXT     "v=DMARC1; p=reject; rua=mailto:dmarc@example.com"

; CAA Records
@       IN      CAA     0 issue "letsencrypt.org"
BIND;

        $zone = BindZoneParser::parse($zoneText);

        $this->assertSame('example.com', $zone->getName());
        $this->assertSame(3600, $zone->getDefaultTtl());
        $this->assertSame(2026082901, $zone->getSerial());

        // Check SOA
        $soa = $zone->getSoaRecord();
        $this->assertNotNull($soa);
        $this->assertSame('ns1.example.com', $soa->getData()['mname']);
        $this->assertSame('hostmaster.example.com', $soa->getData()['rname']);

        // Check NS
        $nsRecords = $zone->getNsRecords();
        $this->assertCount(2, $nsRecords);

        // Check A records
        $apexA = $zone->findRecords('example.com', RecordType::A);
        $this->assertCount(1, $apexA);
        $this->assertSame('192.0.2.1', $apexA[0]->getData());

        // Check relative subdomain expansion: 'mail' -> 'mail.example.com'
        $mailA = $zone->findRecords('mail.example.com', RecordType::A);
        $this->assertCount(1, $mailA);
        $this->assertSame('192.0.2.25', $mailA[0]->getData());

        // Check CNAME: 'www' -> 'www.example.com'
        $cname = $zone->findRecords('www.example.com', RecordType::CNAME);
        $this->assertCount(1, $cname);
        $this->assertSame('example.com', $cname[0]->getData());

        // Check TXT with semicolon inside quotes
        $dmarcTxt = $zone->findRecords('_dmarc.example.com', RecordType::TXT);
        $this->assertCount(1, $dmarcTxt);
        $this->assertSame('v=DMARC1; p=reject; rua=mailto:dmarc@example.com', $dmarcTxt[0]->getData());

        // Check CAA
        $caa = $zone->findRecords('example.com', RecordType::CAA);
        $this->assertCount(1, $caa);
        $this->assertSame('issue', $caa[0]->getData()['tag']);
        $this->assertSame('letsencrypt.org', $caa[0]->getData()['value']);
    }

    public function testParseTtlUnitSuffixes(): void
    {
        $this->assertSame(30, BindZoneParser::parseTtl('30s'));
        $this->assertSame(300, BindZoneParser::parseTtl('5m'));
        $this->assertSame(7200, BindZoneParser::parseTtl('2h'));
        $this->assertSame(86400, BindZoneParser::parseTtl('1d'));
        $this->assertSame(1209600, BindZoneParser::parseTtl('2w'));
        $this->assertSame(3600, BindZoneParser::parseTtl('3600'));
    }

    public function testParseSyntaxErrorThrowsBindParseException(): void
    {
        $badZone = "\$ORIGIN example.com.\n@ IN";

        $this->assertThrows(BindParseException::class, function () use ($badZone) {
            BindZoneParser::parse($badZone);
        }, 'Missing record type');
    }
}
