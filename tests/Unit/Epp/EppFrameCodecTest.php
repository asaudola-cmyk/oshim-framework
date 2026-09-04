<?php
declare(strict_types=1);

namespace Oshim\Tests\Unit\Epp;

use Oshim\Epp\Codec\EppFrameCodec;
use Oshim\Epp\Exceptions\EppFramingException;
use Oshim\Epp\Exceptions\EppTransportException;
use Oshim\Tests\Harness\TestCase;

class EppFrameCodecTest extends TestCase
{
    public function testPackCalculatesCorrectLengthPrefix(): void
    {
        $xml = '<epp xmlns="urn:ietf:params:xml:ns:epp-1.0"><hello/></epp>';
        $framed = EppFrameCodec::pack($xml);

        $this->assertSame(strlen($xml) + 4, strlen($framed));
        $unpackedLength = unpack('N', substr($framed, 0, 4))[1];
        $this->assertSame(strlen($xml) + 4, $unpackedLength);
        $this->assertSame($xml, substr($framed, 4));
    }

    public function testUnpackExtractsXmlPayload(): void
    {
        $xml = '<epp xmlns="urn:ietf:params:xml:ns:epp-1.0"><command><logout/></command></epp>';
        $framed = EppFrameCodec::pack($xml);
        $unpacked = EppFrameCodec::unpack($framed);

        $this->assertSame($xml, $unpacked);
    }

    public function testUnpackRejectsBufferShorterThan4Bytes(): void
    {
        $this->assertThrows(EppFramingException::class, function () {
            EppFrameCodec::unpack("\x00\x00\x05");
        }, 'buffer too short');
    }

    public function testUnpackRejectsTotalLengthLessThan4(): void
    {
        $this->assertThrows(EppFramingException::class, function () {
            EppFrameCodec::unpack("\x00\x00\x00\x02");
        }, 'cannot be less than 4');
    }

    public function testUnpackRejectsIncompleteFrameBuffer(): void
    {
        $this->assertThrows(EppFramingException::class, function () {
            // Declares length 100, but only provides 10 bytes
            EppFrameCodec::unpack(pack('N', 100) . '123456');
        }, 'Incomplete EPP frame');
    }

    public function testPackAndUnpackLarge5MbPayload(): void
    {
        $largeXml = '<epp>' . str_repeat('<domain:name>subdomain' . bin2hex(random_bytes(4)) . '.cloud</domain:name>', 50000) . '</epp>';
        $framed = EppFrameCodec::pack($largeXml);

        $this->assertSame(strlen($largeXml) + 4, strlen($framed));
        $unpacked = EppFrameCodec::unpack($framed);
        $this->assertSame($largeXml, $unpacked);
    }

    public function testStreamReadAndWriteWithMemoryStream(): void
    {
        $stream = fopen('php://memory', 'r+');
        $this->assertNotNull($stream);

        $xml = '<epp><greeting><svID>TEST-STREAM</svID></greeting></epp>';
        EppFrameCodec::writeToStream($stream, $xml);

        rewind($stream);
        $readXml = EppFrameCodec::readFromStream($stream);

        $this->assertSame($xml, $readXml);
        fclose($stream);
    }

    public function testStreamReadFromInvalidResourceThrowsException(): void
    {
        $this->assertThrows(EppTransportException::class, function () {
            EppFrameCodec::readFromStream(null);
        });
    }
}
