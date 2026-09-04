<?php
declare(strict_types=1);

namespace Oshim\Tests\Unit\Dns;

use Oshim\Dns\Exceptions\DnsParseException;
use Oshim\Dns\Exceptions\InvalidRecordException;
use Oshim\Dns\Wire\DnsCodec;
use Oshim\Tests\Harness\TestCase;

class DnsCodecTest extends TestCase
{
    public function testEncodeDomainNameStandardFormat(): void
    {
        $domain = 'www.example.com';
        $offsetMap = [];
        $wire = DnsCodec::encodeDomainName($domain, $offsetMap, 0);

        $this->assertSame("\x03www\x07example\x03com\x00", $wire);

        $offset = 0;
        $decoded = DnsCodec::decodeDomainName($wire, $offset);
        $this->assertSame($domain, $decoded);
        $this->assertSame(strlen($wire), $offset);
    }

    public function testEncodeDomainNameCompressionPointers(): void
    {
        $offsetMap = [];
        $wire = '';

        // 1. Encode first domain 'ns1.example.com' at offset 0
        $d1 = DnsCodec::encodeDomainName('ns1.example.com', $offsetMap, 0);
        $wire .= $d1; // length: 1 + 3 + 1 + 7 + 1 + 3 + 1 = 17 bytes
        $this->assertSame(17, strlen($wire));

        // 2. Encode second domain 'ns2.example.com' at offset 17
        $d2 = DnsCodec::encodeDomainName('ns2.example.com', $offsetMap, strlen($wire));
        $wire .= $d2;

        // Second domain should contain '\x03ns2' + pointer to 'example.com' at offset 4
        $expectedPointer = 0xC000 | 4;
        $this->assertSame("\x03ns2" . pack('n', $expectedPointer), $d2);

        // Decode both from combined buffer
        $off1 = 0;
        $dec1 = DnsCodec::decodeDomainName($wire, $off1);
        $this->assertSame('ns1.example.com', $dec1);

        $off2 = 17;
        $dec2 = DnsCodec::decodeDomainName($wire, $off2);
        $this->assertSame('ns2.example.com', $dec2);
    }

    public function testDecodeCyclicPointerLoopThrowsParseException(): void
    {
        // Construct a wire packet where pointer at offset 0 points to offset 0
        $corruptWire = pack('n', 0xC000);

        $this->assertThrows(DnsParseException::class, function () use ($corruptWire) {
            $offset = 0;
            DnsCodec::decodeDomainName($corruptWire, $offset);
        }, 'loop detected');
    }

    public function testDecodeOutOfBoundsPointerThrowsParseException(): void
    {
        // Pointer points to offset 500 in a 10-byte buffer
        $corruptWire = pack('n', 0xC000 | 500);

        $this->assertThrows(DnsParseException::class, function () use ($corruptWire) {
            $offset = 0;
            DnsCodec::decodeDomainName($corruptWire, $offset);
        }, 'out of bounds');
    }

    public function testEncodeLabelExceeding63BytesThrowsInvalidRecordException(): void
    {
        $longLabel = str_repeat('a', 64) . '.com';

        $this->assertThrows(InvalidRecordException::class, function () use ($longLabel) {
            $map = [];
            DnsCodec::encodeDomainName($longLabel, $map);
        }, 'exceeds maximum length');
    }

    public function testEncodeApexAndRootDomain(): void
    {
        $map = [];
        $wireRoot = DnsCodec::encodeDomainName('', $map);
        $this->assertSame("\x00", $wireRoot);

        $wireApex = DnsCodec::encodeDomainName('@', $map);
        $this->assertSame("\x00", $wireApex);
    }
}
