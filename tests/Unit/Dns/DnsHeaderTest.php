<?php
declare(strict_types=1);

namespace Oshim\Tests\Unit\Dns;

use Oshim\Dns\Exceptions\DnsParseException;
use Oshim\Dns\Wire\DnsHeader;
use Oshim\Tests\Harness\TestCase;

class DnsHeaderTest extends TestCase
{
    public function testHeaderPackAndUnpackExact12Bytes(): void
    {
        $header = new DnsHeader(
            0x4321,
            true, // QR
            DnsHeader::OPCODE_QUERY,
            true, // AA
            false, // TC
            true, // RD
            true, // RA
            DnsHeader::RCODE_NOERROR,
            1, // QD
            2, // AN
            1, // NS
            0  // AR
        );

        $packed = $header->pack();
        $this->assertSame(12, strlen($packed));

        $unpacked = DnsHeader::unpack($packed);
        $this->assertSame(0x4321, $unpacked->id);
        $this->assertTrue($unpacked->qr);
        $this->assertSame(DnsHeader::OPCODE_QUERY, $unpacked->opcode);
        $this->assertTrue($unpacked->aa);
        $this->assertFalse($unpacked->tc);
        $this->assertTrue($unpacked->rd);
        $this->assertTrue($unpacked->ra);
        $this->assertSame(0, $unpacked->z);
        $this->assertSame(DnsHeader::RCODE_NOERROR, $unpacked->rcode);
        $this->assertSame(1, $unpacked->qdCount);
        $this->assertSame(2, $unpacked->anCount);
        $this->assertSame(1, $unpacked->nsCount);
        $this->assertSame(0, $unpacked->arCount);
    }

    public function testHeaderBitmaskFlags(): void
    {
        // Test Truncated TC bit + NXDOMAIN RCODE (3)
        $header = new DnsHeader(101, true, DnsHeader::OPCODE_QUERY, false, true, false, false, DnsHeader::RCODE_NXDOMAIN);
        $packed = $header->pack();
        $unpacked = DnsHeader::unpack($packed);

        $this->assertTrue($unpacked->isResponse());
        $this->assertFalse($unpacked->isAuthoritative());
        $this->assertTrue($unpacked->isTruncated());
        $this->assertSame(DnsHeader::RCODE_NXDOMAIN, $unpacked->getRcode());
    }

    public function testHeaderUnpackRejectsBufferShorterThan12Bytes(): void
    {
        $this->assertThrows(DnsParseException::class, function () {
            DnsHeader::unpack("1234567890"); // 10 bytes
        }, 'Buffer too short');
    }

    public function testHeaderRcodeConstants(): void
    {
        $rcodes = [
            DnsHeader::RCODE_NOERROR => 0,
            DnsHeader::RCODE_FORMERR => 1,
            DnsHeader::RCODE_SERVFAIL => 2,
            DnsHeader::RCODE_NXDOMAIN => 3,
            DnsHeader::RCODE_NOTIMP => 4,
            DnsHeader::RCODE_REFUSED => 5,
        ];

        foreach ($rcodes as $rcode => $expectedVal) {
            $h = new DnsHeader(1, true, 0, false, false, false, false, $rcode);
            $unpacked = DnsHeader::unpack($h->pack());
            $this->assertSame($expectedVal, $unpacked->getRcode());
        }
    }
}
