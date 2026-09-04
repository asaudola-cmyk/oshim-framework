<?php
declare(strict_types=1);

namespace Oshim\Tests\Unit\Dns;

use Oshim\Dns\Packet\DnsPacket;
use Oshim\Dns\Records\Codec\RecordDataCodec;
use Oshim\Dns\Records\RecordType;
use Oshim\Dns\Records\ResourceRecord;
use Oshim\Dns\Wire\DnsHeader;
use Oshim\Dns\Wire\DnsQuestion;
use Oshim\Tests\Harness\TestCase;

class ResourceRecordCodecTest extends TestCase
{
    public function testARecordCodec(): void
    {
        $map = [];
        $ip = '192.0.2.42';
        $encoded = RecordDataCodec::encode(RecordType::A, $ip, $map);
        $this->assertSame(4, strlen($encoded));

        $decoded = RecordDataCodec::decode(RecordType::A, $encoded, 0, 4);
        $this->assertSame($ip, $decoded);
    }

    public function testAaaaRecordCodec(): void
    {
        $map = [];
        $ipv6 = '2001:db8:85a3::8a2e:370:7334';
        $encoded = RecordDataCodec::encode(RecordType::AAAA, $ipv6, $map);
        $this->assertSame(16, strlen($encoded));

        $decoded = RecordDataCodec::decode(RecordType::AAAA, $encoded, 0, 16);
        $this->assertSame(inet_ntop(inet_pton($ipv6)), $decoded);
    }

    public function testCnameAndNsAndPtrCodec(): void
    {
        $map = [];
        $target = 'canonical.example.com';
        $encoded = RecordDataCodec::encode(RecordType::CNAME, $target, $map);

        $decoded = RecordDataCodec::decode(RecordType::CNAME, $encoded, 0, strlen($encoded));
        $this->assertSame($target, $decoded);
    }

    public function testMxRecordCodec(): void
    {
        $map = [];
        $mxData = ['preference' => 10, 'exchange' => 'mail.example.org'];
        $encoded = RecordDataCodec::encode(RecordType::MX, $mxData, $map);

        $decoded = RecordDataCodec::decode(RecordType::MX, $encoded, 0, strlen($encoded));
        $this->assertSame(10, $decoded['preference']);
        $this->assertSame('mail.example.org', $decoded['exchange']);
    }

    public function testTxtRecordMultiChunkCodec(): void
    {
        $map = [];
        // Test long string that splits across 255-byte chunks
        $longText = str_repeat('A', 300);
        $encoded = RecordDataCodec::encode(RecordType::TXT, $longText, $map);

        // 300 bytes split into 255 + 45 -> 1+255 + 1+45 = 302 bytes
        $this->assertSame(302, strlen($encoded));

        $decoded = RecordDataCodec::decode(RecordType::TXT, $encoded, 0, strlen($encoded));
        $this->assertSame($longText, $decoded);
    }

    public function testSoaRecordCodec(): void
    {
        $map = [];
        $soaData = [
            'mname' => 'ns1.example.com',
            'rname' => 'hostmaster.example.com',
            'serial' => 2026082901,
            'refresh' => 7200,
            'retry' => 3600,
            'expire' => 1209600,
            'minimum' => 86400,
        ];

        $encoded = RecordDataCodec::encode(RecordType::SOA, $soaData, $map);
        $decoded = RecordDataCodec::decode(RecordType::SOA, $encoded, 0, strlen($encoded));

        $this->assertSame('ns1.example.com', $decoded['mname']);
        $this->assertSame('hostmaster.example.com', $decoded['rname']);
        $this->assertSame(2026082901, $decoded['serial']);
        $this->assertSame(7200, $decoded['refresh']);
        $this->assertSame(3600, $decoded['retry']);
        $this->assertSame(1209600, $decoded['expire']);
        $this->assertSame(86400, $decoded['minimum']);
    }

    public function testCaaRecordCodec(): void
    {
        $map = [];
        $caaData = ['flags' => 0, 'tag' => 'issue', 'value' => 'letsencrypt.org'];
        $encoded = RecordDataCodec::encode(RecordType::CAA, $caaData, $map);

        $decoded = RecordDataCodec::decode(RecordType::CAA, $encoded, 0, strlen($encoded));
        $this->assertSame(0, $decoded['flags']);
        $this->assertSame('issue', $decoded['tag']);
        $this->assertSame('letsencrypt.org', $decoded['value']);
    }

    public function testPacketSerializationAndParsing(): void
    {
        $packet = new DnsPacket(
            new DnsHeader(1234, true, 0, true, false, true, false, 0),
            [new DnsQuestion('example.com', RecordType::A)],
            [ResourceRecord::a('example.com', '93.184.216.34', 300)]
        );

        $wire = $packet->serialize();
        $this->assertNotEmpty($wire);

        $parsed = DnsPacket::parse($wire);
        $this->assertSame(1234, $parsed->getHeader()->getId());
        $this->assertTrue($parsed->getHeader()->isResponse());
        $this->assertTrue($parsed->getHeader()->isAuthoritative());
        $this->assertCount(1, $parsed->getQuestions());
        $this->assertSame('example.com', $parsed->getQuestions()[0]->getName());
        $this->assertCount(1, $parsed->getAnswers());
        $this->assertSame('93.184.216.34', $parsed->getAnswers()[0]->getData());
    }

    public function testPacketUdpTruncationEnforcement(): void
    {
        $header = new DnsHeader(555, true, 0, true, false, true, false, 0);
        $questions = [new DnsQuestion('example.com', RecordType::TXT)];
        $answers = [];

        // Add 20 large TXT records that exceed 512 bytes
        for ($i = 0; $i < 20; $i++) {
            $answers[] = ResourceRecord::txt('example.com', 'Large payload record text chunk number ' . $i . ' ' . str_repeat('X', 50));
        }

        $packet = new DnsPacket($header, $questions, $answers);

        // Without truncation limit
        $unlimitedWire = $packet->serialize(0);
        $this->assertGreaterThan(512, strlen($unlimitedWire));

        // With 512 bytes limit
        $truncatedWire = $packet->serialize(512);
        $this->assertLessThanOrEqual(512, strlen($truncatedWire));

        $parsedTrunc = DnsPacket::parse($truncatedWire);
        $this->assertTrue($parsedTrunc->getHeader()->isTruncated());
    }
}
