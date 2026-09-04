<?php
declare(strict_types=1);

namespace Oshim\Tests\Unit\Dns;

use Oshim\Dns\Exceptions\BindParseException;
use Oshim\Dns\Exceptions\DnsParseException;
use Oshim\Dns\Exceptions\InvalidRecordException;
use Oshim\Dns\Packet\DnsPacket;
use Oshim\Dns\Parser\BindZoneParser;
use Oshim\Dns\Parser\BindZoneSerializer;
use Oshim\Dns\Records\Codec\RecordDataCodec;
use Oshim\Dns\Records\RecordType;
use Oshim\Dns\Records\ResourceRecord;
use Oshim\Dns\Resolver\AuthoritativeResolver;
use Oshim\Dns\Server\DnsServer;
use Oshim\Dns\Server\DnsServerConfig;
use Oshim\Dns\Wire\DnsCodec;
use Oshim\Dns\Wire\DnsHeader;
use Oshim\Dns\Wire\DnsQuestion;
use Oshim\Dns\Zone\MemoryZoneRepository;
use Oshim\Dns\Zone\SqliteZoneRepository;
use Oshim\Dns\Zone\Zone;
use Oshim\Tests\Harness\TestCase;
use Throwable;

class DnsAdversarialChallengeTest extends TestCase
{
    public function testDirectPointerLoopDetection(): void
    {
        $header = (new DnsHeader(1, false, 0, false, false, false, false, 0, 1, 0, 0, 0))->pack();
        $corruptWire = $header . pack('n', 0xC00C) . pack('nn', 1, 1);

        $this->assertThrows(DnsParseException::class, function () use ($corruptWire) {
            $offset = 12;
            DnsCodec::decodeDomainName($corruptWire, $offset);
        }, 'loop detected');
    }

    public function testMultiStepPointerLoopDetection(): void
    {
        $header = (new DnsHeader(2, false, 0, false, false, false, false, 0, 1, 0, 0, 0))->pack();
        $wire = $header . pack('n', 0xC00E) . pack('n', 0xC00C);

        $this->assertThrows(DnsParseException::class, function () use ($wire) {
            $offset = 12;
            DnsCodec::decodeDomainName($wire, $offset);
        }, 'loop detected');
    }

    public function testPointerOutOfBoundsOffsets(): void
    {
        $header = (new DnsHeader(3, false, 0, false, false, false, false, 0, 1, 0, 0, 0))->pack();
        $wire = $header . pack('n', 0xC000 | 999);

        $this->assertThrows(DnsParseException::class, function () use ($wire) {
            $offset = 12;
            DnsCodec::decodeDomainName($wire, $offset);
        }, 'out of bounds');
    }

    public function testExcessivePointerJumpsTermination(): void
    {
        $header = (new DnsHeader(4, false, 0, false, false, false, false, 0, 1, 0, 0, 0))->pack();
        $wire = $header;
        for ($i = 0; $i < 18; $i++) {
            $wire .= pack('n', 0xC000 | (12 + ($i + 1) * 2));
        }
        $wire .= "\x03com\x00";

        $this->assertThrows(DnsParseException::class, function () use ($wire) {
            $offset = 12;
            DnsCodec::decodeDomainName($wire, $offset);
        }, 'Excessive DNS compression pointer jumps');
    }

    public function testLabelLengthBoundariesAndViolations(): void
    {
        // 1. Label length > 63 in wire
        $wire = "\x40" . str_repeat('a', 64) . "\x00";
        $this->assertThrows(DnsParseException::class, function () use ($wire) {
            $offset = 0;
            DnsCodec::decodeDomainName($wire, $offset);
        }, 'exceeds maximum allowed 63');

        // 2. Encoding label > 63
        $this->assertThrows(InvalidRecordException::class, function () {
            $m = [];
            DnsCodec::encodeDomainName(str_repeat('b', 64) . '.com', $m);
        }, 'exceeds maximum length of 63');

        // 3. Exact 63 label succeeds
        $m = [];
        $w = DnsCodec::encodeDomainName(str_repeat('c', 63) . '.com', $m);
        $off = 0;
        $dec = DnsCodec::decodeDomainName($w, $off);
        $this->assertSame(str_repeat('c', 63) . '.com', $dec);
    }

    public function testDomainTotalLengthViolations(): void
    {
        $longDomain = str_repeat('a', 60) . '.' . str_repeat('b', 60) . '.' . str_repeat('c', 60) . '.' . str_repeat('d', 60) . '.' . str_repeat('e', 20) . '.com';
        $this->assertThrows(InvalidRecordException::class, function () use ($longDomain) {
            $m = [];
            DnsCodec::encodeDomainName($longDomain, $m);
        }, 'exceeds maximum length of 255');
    }

    public function testTruncatedHeadersAndBufferFuzzing(): void
    {
        for ($len = 0; $len <= 11; $len++) {
            $this->assertThrows(DnsParseException::class, function () use ($len) {
                DnsPacket::parse(str_repeat("\x00", $len));
            });
        }
    }

    public function testUdp512ByteTruncationAndTcBit(): void
    {
        $zone = new Zone('overflow.test', 300);
        $zone->addRecord(ResourceRecord::soa('overflow.test', 'ns1.overflow.test', 'admin.overflow.test', 1));
        for ($i = 1; $i <= 25; $i++) {
            $zone->addRecord(ResourceRecord::txt('overflow.test', "txt-payload-{$i}-" . str_repeat("ABCDEFGH", 10)));
        }

        $repo = new MemoryZoneRepository([$zone]);
        $resolver = new AuthoritativeResolver($repo);

        $query = new DnsPacket(
            new DnsHeader(0x1234, false, 0, false, false, true, false, 0, 1, 0, 0, 0),
            [new DnsQuestion('overflow.test', RecordType::TXT)]
        );
        $queryWire = $query->pack();

        $udpRespWire = $resolver->resolveQueryWire($queryWire, 'udp', 512);
        $udpResp = DnsPacket::parse($udpRespWire);

        $this->assertTrue($udpResp->getHeader()->isTruncated());
        $this->assertLessThanOrEqual(512, strlen($udpRespWire));
        $this->assertCount(0, $udpResp->getAnswers());
        $this->assertCount(1, $udpResp->getQuestions());

        // TCP query allows full payload
        $tcpRespWire = $resolver->resolveQueryWire($queryWire, 'tcp', 0);
        $tcpResp = DnsPacket::parse($tcpRespWire);

        $this->assertFalse($tcpResp->getHeader()->isTruncated());
        $this->assertCount(25, $tcpResp->getAnswers());
    }

    public function testAll9ResourceRecordCodecs(): void
    {
        // A
        $mA = [];
        $encA = RecordDataCodec::encode(RecordType::A, '192.168.1.1', $mA);
        $this->assertSame('192.168.1.1', RecordDataCodec::decode(RecordType::A, $encA, 0, 4));
        $this->assertThrows(DnsParseException::class, fn() => RecordDataCodec::decode(RecordType::A, "\x01\x02\x03", 0, 3));

        // AAAA
        $mAaaa = [];
        $encAaaa = RecordDataCodec::encode(RecordType::AAAA, '2001:db8::1', $mAaaa);
        $this->assertSame('2001:db8::1', RecordDataCodec::decode(RecordType::AAAA, $encAaaa, 0, 16));
        $this->assertThrows(DnsParseException::class, fn() => RecordDataCodec::decode(RecordType::AAAA, str_repeat("\x00", 15), 0, 15));

        // CNAME
        $mCname = [];
        $encCname = RecordDataCodec::encode(RecordType::CNAME, 'cname.target.com', $mCname);
        $this->assertSame('cname.target.com', RecordDataCodec::decode(RecordType::CNAME, $encCname, 0, strlen($encCname)));

        // NS
        $mNs = [];
        $encNs = RecordDataCodec::encode(RecordType::NS, 'ns1.target.com', $mNs);
        $this->assertSame('ns1.target.com', RecordDataCodec::decode(RecordType::NS, $encNs, 0, strlen($encNs)));

        // PTR
        $mPtr = [];
        $encPtr = RecordDataCodec::encode(RecordType::PTR, 'host.target.com', $mPtr);
        $this->assertSame('host.target.com', RecordDataCodec::decode(RecordType::PTR, $encPtr, 0, strlen($encPtr)));

        // MX
        $mMx = [];
        $encMx = RecordDataCodec::encode(RecordType::MX, ['preference' => 20, 'exchange' => 'mail.target.com'], $mMx);
        $decMx = RecordDataCodec::decode(RecordType::MX, $encMx, 0, strlen($encMx));
        $this->assertSame(20, $decMx['preference']);
        $this->assertSame('mail.target.com', $decMx['exchange']);

        // TXT
        $mTxt = [];
        $txtLong = str_repeat("ABCDEFGHIJKLMNOPQRSTUVWXYZ", 15); // 390 bytes
        $encTxt = RecordDataCodec::encode(RecordType::TXT, $txtLong, $mTxt);
        $decTxt = RecordDataCodec::decode(RecordType::TXT, $encTxt, 0, strlen($encTxt));
        $this->assertSame($txtLong, $decTxt);

        // SOA
        $mSoa = [];
        $soaData = [
            'mname' => 'ns1.target.com',
            'rname' => 'hostmaster.target.com',
            'serial' => 2026082901,
            'refresh' => 3600,
            'retry' => 1800,
            'expire' => 604800,
            'minimum' => 86400,
        ];
        $encSoa = RecordDataCodec::encode(RecordType::SOA, $soaData, $mSoa);
        $decSoa = RecordDataCodec::decode(RecordType::SOA, $encSoa, 0, strlen($encSoa));
        $this->assertSame(2026082901, $decSoa['serial']);
        $this->assertSame(86400, $decSoa['minimum']);

        // CAA
        $mCaa = [];
        $caaData = ['flags' => 0, 'tag' => 'issue', 'value' => 'letsencrypt.org'];
        $encCaa = RecordDataCodec::encode(RecordType::CAA, $caaData, $mCaa);
        $decCaa = RecordDataCodec::decode(RecordType::CAA, $encCaa, 0, strlen($encCaa));
        $this->assertSame('issue', $decCaa['tag']);
        $this->assertSame('letsencrypt.org', $decCaa['value']);
    }

    public function testBindParserQuotedSemicolonsAndMultilineParentheses(): void
    {
        $zoneContent = <<<ZONE
\$ORIGIN bind-adv.test.
\$TTL 1h
@       IN      SOA     ns1.bind-adv.test. admin.bind-adv.test. (
                        2026082901 ; Serial
                        2h         ; Refresh
                        30m        ; Retry
                        2w         ; Expire
                        1d         ; Negative TTL
                        )
@       IN      TXT     "v=spf1 include:_spf.google.com; ip4:192.0.2.1; ~all" ; comment
@       IN      CAA     0 issue "letsencrypt.org; validationmethods=dns-01"
mail    IN      A       10.0.0.1
        IN      MX      10 mail.bind-adv.test.
ZONE;

        $zone = BindZoneParser::parse($zoneContent);
        $soa = $zone->getSoaRecord();
        $this->assertSame(2026082901, $soa->getData()['serial']);
        $this->assertSame(7200, $soa->getData()['refresh']);
        $this->assertSame(86400, $soa->getData()['minimum']);

        $txt = $zone->findRecords('bind-adv.test', RecordType::TXT)[0];
        $this->assertSame("v=spf1 include:_spf.google.com; ip4:192.0.2.1; ~all", $txt->getData());

        $mx = $zone->findRecords('mail.bind-adv.test', RecordType::MX)[0];
        $this->assertSame('mail.bind-adv.test', $mx->getData()['exchange']);
    }

    public function testConcurrentAndRepositoryResolution(): void
    {
        $repo = new MemoryZoneRepository();
        $zone = new Zone('resolver-test.com', 300);
        $zone->addRecord(ResourceRecord::soa('resolver-test.com', 'ns1.resolver-test.com', 'admin.resolver-test.com', 1, 3600, 1800, 604800, 300));
        $zone->addRecord(ResourceRecord::a('app.resolver-test.com', '10.0.0.50'));
        $zone->addRecord(ResourceRecord::cname('alias.resolver-test.com', 'app.resolver-test.com'));
        $zone->addRecord(ResourceRecord::a('*.wild.resolver-test.com', '10.0.0.99'));
        $repo->saveZone($zone);

        $resolver = new AuthoritativeResolver($repo);

        // 1. Exact match
        $q1 = new DnsPacket(new DnsHeader(1), [new DnsQuestion('app.resolver-test.com', RecordType::A)]);
        $resp1 = $resolver->resolve($q1);
        $this->assertSame('10.0.0.50', $resp1->getAnswers()[0]->getData());

        // 2. CNAME chasing
        $q2 = new DnsPacket(new DnsHeader(2), [new DnsQuestion('alias.resolver-test.com', RecordType::A)]);
        $resp2 = $resolver->resolve($q2);
        $this->assertCount(2, $resp2->getAnswers());
        $this->assertSame('app.resolver-test.com', $resp2->getAnswers()[0]->getData());
        $this->assertSame('10.0.0.50', $resp2->getAnswers()[1]->getData());

        // 3. Wildcard synthesis
        $q3 = new DnsPacket(new DnsHeader(3), [new DnsQuestion('custom.wild.resolver-test.com', RecordType::A)]);
        $resp3 = $resolver->resolve($q3);
        $this->assertSame('10.0.0.99', $resp3->getAnswers()[0]->getData());
        $this->assertSame('custom.wild.resolver-test.com', $resp3->getAnswers()[0]->getName());

        // 4. NODATA
        $q4 = new DnsPacket(new DnsHeader(4), [new DnsQuestion('app.resolver-test.com', RecordType::AAAA)]);
        $resp4 = $resolver->resolve($q4);
        $this->assertSame(DnsHeader::RCODE_NOERROR, $resp4->getHeader()->getRcode());
        $this->assertCount(0, $resp4->getAnswers());
        $this->assertCount(1, $resp4->getAuthorities());

        // 5. NXDOMAIN
        $q5 = new DnsPacket(new DnsHeader(5), [new DnsQuestion('missing.resolver-test.com', RecordType::A)]);
        $resp5 = $resolver->resolve($q5);
        $this->assertSame(DnsHeader::RCODE_NXDOMAIN, $resp5->getHeader()->getRcode());

        // 6. REFUSED (foreign domain)
        $q6 = new DnsPacket(new DnsHeader(6), [new DnsQuestion('unmanaged.foreign.org', RecordType::A)]);
        $resp6 = $resolver->resolve($q6);
        $this->assertSame(DnsHeader::RCODE_REFUSED, $resp6->getHeader()->getRcode());
    }
}
