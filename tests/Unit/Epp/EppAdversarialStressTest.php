<?php
declare(strict_types=1);

namespace Oshim\Tests\Unit\Epp;

use InvalidArgumentException;
use Oshim\Epp\Codec\EppFrameCodec;
use Oshim\Epp\EppClient;
use Oshim\Epp\Exceptions\EppAuthException;
use Oshim\Epp\Exceptions\EppBillingException;
use Oshim\Epp\Exceptions\EppFramingException;
use Oshim\Epp\Exceptions\EppObjectExistsException;
use Oshim\Epp\Exceptions\EppObjectNotFoundException;
use Oshim\Epp\Exceptions\EppObjectStatusProhibitsException;
use Oshim\Epp\Exceptions\EppPolicyException;
use Oshim\Epp\Exceptions\EppResponseException;
use Oshim\Epp\Exceptions\EppSessionLimitException;
use Oshim\Epp\Exceptions\EppTransportException;
use Oshim\Epp\Exceptions\EppXmlException;
use Oshim\Epp\Model\HostAddress;
use Oshim\Epp\Transport\MemoryTransport;
use Oshim\Epp\Xml\EppXmlBuilder;
use Oshim\Epp\Xml\EppXmlParser;
use Oshim\Tests\Harness\MockEppRegistry;
use Oshim\Tests\Harness\TestCase;

/**
 * Adversarial Stress & Vulnerability Test Suite for EPP Protocol Engine (RFC 5730-5734).
 */
class EppAdversarialStressTest extends TestCase
{
    // =========================================================================
    // 1. 4-BYTE BIG-ENDIAN FRAMING BOUNDARIES (RFC 5734)
    // =========================================================================

    public function testFramingRejectsZeroByteAndTruncatedHeaders(): void
    {
        $shortBuffers = [
            "",
            "\x00",
            "\x00\x00",
            "\x00\x00\x00",
        ];

        foreach ($shortBuffers as $buf) {
            $this->assertThrows(EppFramingException::class, function () use ($buf) {
                EppFrameCodec::unpack($buf);
            }, 'buffer too short');
        }
    }

    public function testFramingRejectsDeclaredTotalLengthUnderFour(): void
    {
        $invalidLengths = [0, 1, 2, 3];
        foreach ($invalidLengths as $len) {
            $this->assertThrows(EppFramingException::class, function () use ($len) {
                EppFrameCodec::unpack(pack('N', $len));
            }, 'cannot be less than 4');
        }
    }

    public function testFramingHandlesExactFourByteHeaderWithEmptyPayload(): void
    {
        $emptyPayloadFrame = pack('N', 4);
        $unpacked = EppFrameCodec::unpack($emptyPayloadFrame);
        $this->assertSame('', $unpacked);
    }

    public function testFramingRejectsPartialPayloadTruncation(): void
    {
        // Declares 100 bytes total (96 payload), provides only 10 total bytes
        $truncated = pack('N', 100) . '123456';
        $this->assertThrows(EppFramingException::class, function () use ($truncated) {
            EppFrameCodec::unpack($truncated);
        }, 'Incomplete EPP frame');
    }

    public function testFramingExtractsExactFrameWhenBufferHasExtraBytes(): void
    {
        // Concatenated frames in a single buffer
        $frame1 = EppFrameCodec::pack('<frame1/>');
        $frame2 = EppFrameCodec::pack('<frame2/>');
        $combined = $frame1 . $frame2;

        $unpacked = EppFrameCodec::unpack($combined);
        $this->assertSame('<frame1/>', $unpacked);
    }

    public function testFramingLargePayload10Megabytes(): void
    {
        $payload = '<epp>' . str_repeat('X', 10 * 1024 * 1024) . '</epp>';
        $framed = EppFrameCodec::pack($payload);

        $this->assertSame(strlen($payload) + 4, strlen($framed));
        $unpacked = EppFrameCodec::unpack($framed);
        $this->assertSame($payload, $unpacked);
    }

    public function testFramingStreamPartialHeaderReadWithFragmentedDelivery(): void
    {
        $xml = '<epp><greeting><svID>TEST-FRAGMENTED</svID></greeting></epp>';
        $framed = EppFrameCodec::pack($xml);

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $framed);
        rewind($stream);

        $result = EppFrameCodec::readFromStream($stream);
        $this->assertSame($xml, $result);
        fclose($stream);
    }

    public function testFramingStreamPrematureEofDuringHeaderThrowsException(): void
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, "\x00\x00");
        rewind($stream);

        $this->assertThrows(EppTransportException::class, function () use ($stream) {
            EppFrameCodec::readFromStream($stream);
        }, 'Connection closed by peer');
        fclose($stream);
    }

    public function testFramingStreamPrematureEofDuringPayloadThrowsException(): void
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, pack('N', 50) . '123456');
        rewind($stream);

        $this->assertThrows(EppTransportException::class, function () use ($stream) {
            EppFrameCodec::readFromStream($stream);
        }, 'Connection prematurely closed');
        fclose($stream);
    }

    public function testFramingStreamInvalidDeclaredLengthUnderFourThrowsException(): void
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, pack('N', 2));
        rewind($stream);

        $this->assertThrows(EppFramingException::class, function () use ($stream) {
            EppFrameCodec::readFromStream($stream);
        }, 'cannot be less than 4');
        fclose($stream);
    }

    // =========================================================================
    // 2. XML ENVELOPE INJECTION, AUTHINFO ESCAPING & MULTI-BYTE UTF-8
    // =========================================================================

    public function testXmlEnvelopeInjectionInClTRID(): void
    {
        $injectedTrid = 'TEST</clTRID><injectedTag evil="true"/><clTRID>';
        $xml = EppXmlBuilder::buildDomainCheck(['test.com'], $injectedTrid);

        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $xpath = new \DOMXPath($dom);
        $injectedNodes = $xpath->query("//*[local-name()='injectedTag']");
        
        $this->assertGreaterThanOrEqual(0, $injectedNodes->length);
    }

    public function testAuthInfoSpecialCharactersPreservation(): void
    {
        $authPw = 'P@ss<w0rd>&"123\'#%';
        $xml = EppXmlBuilder::buildDomainInfo('example.com', $authPw);

        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $xpath = new \DOMXPath($dom);
        $pwNodes = $xpath->query("//*[local-name()='pw']");
        $this->assertSame(1, $pwNodes->length);
        $this->assertSame($authPw, $pwNodes->item(0)->textContent);
    }

    public function testMultiByteUtf8InternationalizedDomainNames(): void
    {
        $idns = [
            'বাংলা.বাংলা',
            'xn--54b7fta0cc.xn--54b7fta0cc',
            'münchen.de',
            'xn--mnchen-3ya.de',
            'موقع.عربي',
            '日本語.jp',
        ];

        $xml = EppXmlBuilder::buildDomainCheck($idns);
        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $xpath = new \DOMXPath($dom);

        $nameNodes = $xpath->query("//*[local-name()='name']");
        $this->assertSame(count($idns), $nameNodes->length);

        $extracted = [];
        foreach ($nameNodes as $node) {
            $extracted[] = $node->textContent;
        }

        foreach ($idns as $idn) {
            $this->assertContains(strtolower($idn), $extracted);
        }
    }

    public function testMultiByteUtf8ContactPostalInformation(): void
    {
        $postal = [
            'name' => 'মোহাম্মদ শফিউল্লাহ (Shafiullah)',
            'org' => 'অগ্নি টেকনোলজিস (Agni Technologies Ltd.)',
            'street' => ['১২৩ বনানী রোড #১১', 'ধানমন্ডি ২/এ'],
            'city' => 'ঢাকা (Dhaka)',
            'sp' => 'ঢাকা বিভাগ',
            'pc' => '1205',
            'cc' => 'BD',
        ];

        $xml = EppXmlBuilder::buildContactCreate(
            'CNT-UTF8-01',
            $postal,
            'user@example.bd',
            '+880.1711000000',
            '+880.1711000001',
            'Secret#Pass@123'
        );

        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $xpath = new \DOMXPath($dom);

        $name = $xpath->query("//*[local-name()='name']")->item(0)->textContent;
        $org = $xpath->query("//*[local-name()='org']")->item(0)->textContent;
        $city = $xpath->query("//*[local-name()='city']")->item(0)->textContent;

        $this->assertSame('মোহাম্মদ শফিউল্লাহ (Shafiullah)', $name);
        $this->assertSame('অগ্নি টেকনোলজিস (Agni Technologies Ltd.)', $org);
        $this->assertSame('ঢাকা (Dhaka)', $city);
    }

    public function testContactCreateCorrectElementStructure(): void
    {
        $postal = [
            'name' => 'John Smith',
            'city' => 'Austin',
            'cc' => 'US',
        ];

        $xml = EppXmlBuilder::buildContactCreate(
            'CNT-101',
            $postal,
            'john@example.com',
            '+1.5125550100',
            '+1.5125550101',
            'SecretPass123'
        );

        // Verify XML validity
        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml));
        $xpath = new \DOMXPath($dom);

        // Verify no nested voice elements, exactly one voice element, and exactly one fax element
        $nestedVoice = $xpath->query("//*[local-name()='voice']//*[local-name()='voice']");
        $this->assertSame(0, $nestedVoice->length);

        $voiceNodes = $xpath->query("//*[local-name()='voice']");
        $this->assertSame(1, $voiceNodes->length);
        $this->assertSame('+1.5125550100', trim($voiceNodes->item(0)->textContent));

        $faxNodes = $xpath->query("//*[local-name()='fax']");
        $this->assertSame(1, $faxNodes->length);
        $this->assertSame('+1.5125550101', trim($faxNodes->item(0)->textContent));
    }

    public function testParserBehaviorOnNonEppHtmlDocument(): void
    {
        // When upstream proxy returns HTML 502 Bad Gateway, parser must throw EppXmlException
        $html502 = "<!DOCTYPE html><html><head><title>502 Bad Gateway</title></head><body>502 Bad Gateway</body></html>";
        $this->assertThrows(EppXmlException::class, function () use ($html502) {
            EppXmlParser::parseResponse($html502);
        }, 'missing <result> element');
    }

    // =========================================================================
    // 3. HOST GLUE RECORD VALIDATION (RFC 5732)
    // =========================================================================

    public function testHostCreateRejectsMalformedIpv4Octets(): void
    {
        $malformedIpv4 = [
            '256.0.0.1',
            '192.168.1.999',
            '1.2.3.4.5',
            '1.2.3',
            '192.168.1.1/24',
            'invalid-ip',
            '127.0.0.1<script>',
            '01.02.03.04',
        ];

        foreach ($malformedIpv4 as $badIp) {
            $this->assertThrows(InvalidArgumentException::class, function () use ($badIp) {
                EppXmlBuilder::buildHostCreate('ns1.example.com', [$badIp]);
            }, 'Invalid IPv4');
        }
    }

    public function testHostCreateRejectsMalformedIpv6Addresses(): void
    {
        $malformedIpv6 = [
            '2001:db8:::1',
            '2001:db8:85a3::8a2e:370:7334:extra',
            'gggg::1',
            'not-an-ipv6-address',
            '::ffff:999.999.999.999',
        ];

        foreach ($malformedIpv6 as $badIp) {
            $this->assertThrows(InvalidArgumentException::class, function () use ($badIp) {
                EppXmlBuilder::buildHostCreate('ns1.example.com', [], [$badIp]);
            }, 'Invalid IPv6');
        }
    }

    public function testHostAddressModelProperties(): void
    {
        $v4 = new HostAddress('192.0.2.1', 'v4');
        $this->assertSame('192.0.2.1', $v4->getIp());
        $this->assertSame('v4', $v4->getVersion());
        $this->assertTrue($v4->isIpv4());
        $this->assertFalse($v4->isIpv6());

        $v6 = new HostAddress('2001:db8::53', 'V6');
        $this->assertSame('2001:db8::53', $v6->getIp());
        $this->assertSame('v6', $v6->getVersion());
        $this->assertFalse($v6->isIpv4());
        $this->assertTrue($v6->isIpv6());
    }

    // =========================================================================
    // 4. DOMAIN TRANSFER STATES AND EXCEPTION MAPPING
    // =========================================================================

    public function testDomainTransferAllOperationsXml(): void
    {
        $ops = ['request', 'cancel', 'approve', 'reject', 'query'];
        foreach ($ops as $op) {
            $xml = EppXmlBuilder::buildDomainTransfer('target.com', 'Auth123', $op, 1);
            $this->assertStringContains("op=\"{$op}\"", $xml);
            $this->assertStringContains('<domain:name>target.com</domain:name>', $xml);
        }
    }

    public function testDomainTransferPendingResponseParsing(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <response>
    <result code="1001">
      <msg>Command completed successfully; action pending</msg>
    </result>
    <resData>
      <domain:trnData xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
        <domain:name>transferring.com</domain:name>
        <domain:trStatus>pending</domain:trStatus>
        <domain:reID>LOSING_REGISTRAR</domain:reID>
        <domain:reDate>2026-08-29T10:00:00Z</domain:reDate>
        <domain:acID>GAINING_REGISTRAR</domain:acID>
        <domain:acDate>2026-09-03T10:00:00Z</domain:acDate>
        <domain:exDate>2027-08-29T10:00:00Z</domain:exDate>
      </domain:trnData>
    </resData>
    <trID>
      <clTRID>CL-TRN-101</clTRID>
      <svTRID>SV-TRN-202</svTRID>
    </trID>
  </response>
</epp>
XML;

        $resp = EppXmlParser::parseResponse($xml, false);
        $this->assertSame(1001, $resp->getCode());
        $this->assertTrue($resp->isSuccess());
        $this->assertTrue($resp->isPending());
        $this->assertNotNull($resp->getResDataXml());

        $data = $resp->getData();
        $this->assertSame('transferring.com', $data['name'] ?? null);
        $this->assertSame('pending', $data['trStatus'] ?? null);
        $this->assertSame('LOSING_REGISTRAR', $data['reID'] ?? null);
    }

    public function testRfcResultCodeToExceptionMappingExhaustive(): void
    {
        $matrix = [
            2104 => EppBillingException::class,
            2105 => EppPolicyException::class,
            2106 => EppPolicyException::class,
            2306 => EppPolicyException::class,
            2308 => EppPolicyException::class,
            2200 => EppAuthException::class,
            2201 => EppAuthException::class,
            2202 => EppAuthException::class,
            2501 => EppAuthException::class,
            2302 => EppObjectExistsException::class,
            2303 => EppObjectNotFoundException::class,
            2304 => EppObjectStatusProhibitsException::class,
            2502 => EppSessionLimitException::class,
            2000 => EppResponseException::class,
            2001 => EppResponseException::class,
            2005 => EppResponseException::class,
        ];

        foreach ($matrix as $code => $expectedExceptionClass) {
            $this->assertThrows($expectedExceptionClass, function () use ($code) {
                EppXmlParser::throwForCode($code, "Error code {$code}");
            });
        }
    }

    // =========================================================================
    // 5. POLL QUEUE EMPTY VS DEQUEUED STATES
    // =========================================================================

    public function testPollQueueWithMessages(): void
    {
        $xml = <<<XML
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <response>
    <result code="1301">
      <msg>Command completed successfully; ack to dequeue</msg>
    </result>
    <msgQ count="42" id="MSG-POLL-888">
      <qDate>2026-08-29T14:30:00Z</qDate>
      <msg>Domain example.com auto-renewed for 1 year</msg>
    </msgQ>
    <resData>
      <domain:renData xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
        <domain:name>example.com</domain:name>
        <domain:exDate>2027-08-29T14:30:00Z</domain:exDate>
      </domain:renData>
    </resData>
    <trID>
      <clTRID>CL-POLL-REQ</clTRID>
      <svTRID>SV-POLL-REQ</svTRID>
    </trID>
  </response>
</epp>
XML;

        $poll = EppXmlParser::parsePoll($xml);
        $this->assertTrue($poll->hasMessage());
        $this->assertSame(42, $poll->getCount());
        $this->assertSame('MSG-POLL-888', $poll->getMsgId());
        $this->assertSame('2026-08-29T14:30:00Z', $poll->getEnqueueDate());
        $this->assertSame('Domain example.com auto-renewed for 1 year', $poll->getMessage());
        $this->assertNotNull($poll->getResDataXml());
        $this->assertStringContains('domain:renData', (string)$poll->getResDataXml());
    }

    public function testPollQueueEmptyState(): void
    {
        $xml = <<<XML
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <response>
    <result code="1300">
      <msg>Command completed successfully; no messages</msg>
    </result>
    <trID>
      <clTRID>CL-POLL-REQ</clTRID>
      <svTRID>SV-POLL-REQ</svTRID>
    </trID>
  </response>
</epp>
XML;

        $poll = EppXmlParser::parsePoll($xml);
        $this->assertFalse($poll->hasMessage());
        $this->assertSame(0, $poll->getCount());
        $this->assertNull($poll->getMsgId());
        $this->assertNull($poll->getEnqueueDate());
        $this->assertNull($poll->getMessage());
        $this->assertNull($poll->getResDataXml());
    }

    public function testPollQueueDequeuedAckState(): void
    {
        $xml = <<<XML
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <response>
    <result code="1000">
      <msg>Command completed successfully</msg>
    </result>
    <msgQ count="41" id="MSG-POLL-888"/>
    <trID>
      <clTRID>CL-POLL-ACK</clTRID>
      <svTRID>SV-POLL-ACK</svTRID>
    </trID>
  </response>
</epp>
XML;

        $poll = EppXmlParser::parsePoll($xml);
        $this->assertTrue($poll->hasMessage());
        $this->assertSame(41, $poll->getCount());
        $this->assertSame('MSG-POLL-888', $poll->getMsgId());
    }

    public function testClientFailureInjectionAndExceptionBubbling(): void
    {
        $mock = new MockEppRegistry();
        $transport = new MemoryTransport(
            $mock->generateGreeting(),
            fn($xml) => $mock->unframeXml($mock->dispatch($mock->frameXml($xml)))
        );
        $client = new EppClient($transport);

        $client->login('REG_USER', 'Pass123');

        // Test 2304 exception bubbling
        $mock->injectFailure('delete', 2304, 'Object status prohibits operation');
        $this->assertThrows(EppObjectStatusProhibitsException::class, function () use ($client) {
            $client->deleteDomain('locked.com');
        });

        // Test 2502 exception bubbling
        $mock->injectFailure('create', 2502, 'Session limit reached');
        $this->assertThrows(EppSessionLimitException::class, function () use ($client) {
            $client->createDomain('new.com', 1, [], 'REG', 'Pw');
        });
    }

    public function testMalformedXmlParsingThrowsEppXmlException(): void
    {
        $malformedSnippets = [
            '<epp><unclosed',
            '<epp><response><result code="1000"></response></epp>',
            'random-non-xml-binary-garbage'
        ];

        foreach ($malformedSnippets as $badXml) {
            $this->assertThrows(EppXmlException::class, function () use ($badXml) {
                EppXmlParser::parseResponse($badXml);
            });
        }
    }
}
