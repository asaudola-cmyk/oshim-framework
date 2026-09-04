<?php
declare(strict_types=1);

namespace Oshim\Tests\Unit\Epp;

use Oshim\Epp\Exceptions\EppAuthException;
use Oshim\Epp\Exceptions\EppBillingException;
use Oshim\Epp\Exceptions\EppObjectExistsException;
use Oshim\Epp\Exceptions\EppObjectNotFoundException;
use Oshim\Epp\Exceptions\EppObjectStatusProhibitsException;
use Oshim\Epp\Exceptions\EppPolicyException;
use Oshim\Epp\Exceptions\EppSessionLimitException;
use Oshim\Epp\Exceptions\EppXmlException;
use Oshim\Epp\Xml\EppXmlParser;
use Oshim\Tests\Harness\TestCase;

class EppXmlParserTest extends TestCase
{
    public function testParseStandardSuccessResponse(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <response>
    <result code="1000">
      <msg>Command completed successfully</msg>
    </result>
    <trID>
      <clTRID>CLTRID-100</clTRID>
      <svTRID>SVTRID-200</svTRID>
    </trID>
  </response>
</epp>
XML;

        $resp = EppXmlParser::parseResponse($xml);
        $this->assertSame(1000, $resp->getCode());
        $this->assertSame('Command completed successfully', $resp->getMessage());
        $this->assertSame('CLTRID-100', $resp->getClTRID());
        $this->assertSame('SVTRID-200', $resp->getSvTRID());
        $this->assertTrue($resp->isSuccess());
        $this->assertFalse($resp->isPending());
    }

    public function testParseActionPendingResponse(): void
    {
        $xml = <<<XML
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <response>
    <result code="1001">
      <msg>Command completed successfully; action pending</msg>
    </result>
    <trID>
      <clTRID>TR-PENDING</clTRID>
      <svTRID>SV-PENDING</svTRID>
    </trID>
  </response>
</epp>
XML;

        $resp = EppXmlParser::parseResponse($xml);
        $this->assertSame(1001, $resp->getCode());
        $this->assertTrue($resp->isSuccess());
        $this->assertTrue($resp->isPending());
    }

    public function testParseGreetingStructure(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <greeting>
    <svID>OSHIM-PRODUCTION-REGISTRY-01</svID>
    <svDate>2026-08-29T12:00:00Z</svDate>
    <svcMenu>
      <version>1.0</version>
      <lang>en</lang>
      <lang>es</lang>
      <objURI>urn:ietf:params:xml:ns:domain-1.0</objURI>
      <objURI>urn:ietf:params:xml:ns:host-1.0</objURI>
      <objURI>urn:ietf:params:xml:ns:contact-1.0</objURI>
      <svcExtension>
        <extURI>urn:ietf:params:xml:ns:secDNS-1.1</extURI>
      </svcExtension>
    </svcMenu>
  </greeting>
</epp>
XML;

        $greeting = EppXmlParser::parseGreeting($xml);
        $this->assertSame('OSHIM-PRODUCTION-REGISTRY-01', $greeting->getServerIdentifier());
        $this->assertSame('2026-08-29T12:00:00Z', $greeting->getServerDate());
        $this->assertContains('1.0', $greeting->getVersions());
        $this->assertContains('en', $greeting->getLanguages());
        $this->assertContains('urn:ietf:params:xml:ns:domain-1.0', $greeting->getObjectUris());
        $this->assertContains('urn:ietf:params:xml:ns:secDNS-1.1', $greeting->getExtensionUris());
    }

    public function testParseDomainCheckResponse(): void
    {
        $xml = <<<XML
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <response>
    <result code="1000">
      <msg>Command completed successfully</msg>
    </result>
    <resData>
      <domain:chkData xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
        <domain:cd>
          <domain:name avail="1">avail.com</domain:name>
        </domain:cd>
        <domain:cd>
          <domain:name avail="0">taken.com</domain:name>
          <domain:reason>In use</domain:reason>
        </domain:cd>
      </domain:chkData>
    </resData>
    <trID>
      <clTRID>TRID-CHK-1</clTRID>
      <svTRID>SVR-CHK-1</svTRID>
    </trID>
  </response>
</epp>
XML;

        $results = EppXmlParser::parseDomainCheck($xml);
        $this->assertArrayHasKey('avail.com', $results);
        $this->assertArrayHasKey('taken.com', $results);

        $this->assertTrue($results['avail.com']->isAvailable());
        $this->assertNull($results['avail.com']->getReason());

        $this->assertFalse($results['taken.com']->isAvailable());
        $this->assertSame('In use', $results['taken.com']->getReason());
    }

    public function testParseDomainInfoResponse(): void
    {
        $xml = <<<XML
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <response>
    <result code="1000"><msg>Command completed successfully</msg></result>
    <resData>
      <domain:infData xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
        <domain:name>example.com</domain:name>
        <domain:roid>EXAMPLE-ROID-01</domain:roid>
        <domain:status s="ok"/>
        <domain:registrant>REG-001</domain:registrant>
        <domain:contact type="admin">ADM-001</domain:contact>
        <domain:contact type="tech">TEC-001</domain:contact>
        <domain:ns>
          <domain:hostObj>ns1.example.com</domain:hostObj>
          <domain:hostObj>ns2.example.com</domain:hostObj>
        </domain:ns>
        <domain:clID>OSHIM_REGISTRAR</domain:clID>
        <domain:crDate>2026-01-01T00:00:00Z</domain:crDate>
        <domain:exDate>2027-01-01T00:00:00Z</domain:exDate>
        <domain:authInfo>
          <domain:pw>SecretAuth123</domain:pw>
        </domain:authInfo>
      </domain:infData>
    </resData>
    <trID><clTRID>INF-01</clTRID><svTRID>SV-INF-01</svTRID></trID>
  </response>
</epp>
XML;

        $info = EppXmlParser::parseDomainInfo($xml);
        $this->assertSame('example.com', $info->getName());
        $this->assertSame('EXAMPLE-ROID-01', $info->getRoid());
        $this->assertContains('ok', $info->getStatus());
        $this->assertSame('REG-001', $info->getRegistrant());
        $this->assertSame(['admin' => 'ADM-001', 'tech' => 'TEC-001'], $info->getContacts());
        $this->assertContains('ns1.example.com', $info->getNameservers());
        $this->assertContains('ns2.example.com', $info->getNameservers());
        $this->assertSame('2026-01-01T00:00:00Z', $info->getCrDate());
        $this->assertSame('2027-01-01T00:00:00Z', $info->getExDate());
        $this->assertSame('SecretAuth123', $info->getAuthPw());
    }

    public function testParsePollResponse(): void
    {
        $xml = <<<XML
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
  <response>
    <result code="1301"><msg>Command completed successfully; ack to dequeue</msg></result>
    <msgQ count="5" id="MSG-999">
      <qDate>2026-08-29T10:00:00Z</qDate>
      <msg>Domain transfer approved</msg>
    </msgQ>
    <trID><clTRID>POLL-1</clTRID><svTRID>SV-POLL-1</svTRID></trID>
  </response>
</epp>
XML;

        $poll = EppXmlParser::parsePoll($xml);
        $this->assertTrue($poll->hasMessage());
        $this->assertSame(5, $poll->getCount());
        $this->assertSame('MSG-999', $poll->getMsgId());
        $this->assertSame('2026-08-29T10:00:00Z', $poll->getEnqueueDate());
        $this->assertSame('Domain transfer approved', $poll->getMessage());
    }

    public function testResultCodeExceptionClassification(): void
    {
        // 2200 -> EppAuthException
        $this->assertThrows(EppAuthException::class, function () {
            EppXmlParser::throwForCode(2200, 'Authentication error');
        });

        // 2302 -> EppObjectExistsException
        $this->assertThrows(EppObjectExistsException::class, function () {
            EppXmlParser::throwForCode(2302, 'Object exists');
        });

        // 2303 -> EppObjectNotFoundException
        $this->assertThrows(EppObjectNotFoundException::class, function () {
            EppXmlParser::throwForCode(2303, 'Object does not exist');
        });

        // 2304 -> EppObjectStatusProhibitsException
        $this->assertThrows(EppObjectStatusProhibitsException::class, function () {
            EppXmlParser::throwForCode(2304, 'Object status prohibits operation');
        });

        // 2104 -> EppBillingException
        $this->assertThrows(EppBillingException::class, function () {
            EppXmlParser::throwForCode(2104, 'Billing failure');
        });

        // 2105 -> EppPolicyException
        $this->assertThrows(EppPolicyException::class, function () {
            EppXmlParser::throwForCode(2105, 'Object not eligible for renewal');
        });

        // 2502 -> EppSessionLimitException
        $this->assertThrows(EppSessionLimitException::class, function () {
            EppXmlParser::throwForCode(2502, 'Session limit exceeded');
        });
    }

    public function testMalformedXmlThrowsEppXmlException(): void
    {
        $this->assertThrows(EppXmlException::class, function () {
            EppXmlParser::parseResponse('<epp><unclosed-tag>');
        }, 'Failed to parse EPP XML');
    }

    public function testParseResponseMissingResultThrowsEppXmlException(): void
    {
        $this->assertThrows(EppXmlException::class, function () {
            EppXmlParser::parseResponse('<root><data>123</data></root>');
        }, 'missing <result> element');
    }

    public function testParseResponseEmptyStringThrowsEppXmlException(): void
    {
        $this->assertThrows(EppXmlException::class, function () {
            EppXmlParser::parseResponse('');
        }, 'cannot be empty');

        $this->assertThrows(EppXmlException::class, function () {
            EppXmlParser::parseResponse('   ');
        }, 'cannot be empty');
    }

    public function testParseResponseHtmlErrorPageThrowsEppXmlException(): void
    {
        $html = '<!DOCTYPE html><html><body>502 Bad Gateway</body></html>';
        $this->assertThrows(EppXmlException::class, function () use ($html) {
            EppXmlParser::parseResponse($html);
        });
    }
}
