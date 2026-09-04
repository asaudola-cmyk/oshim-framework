<?php
declare(strict_types=1);

namespace Oshim\Tests\Unit\Epp;

use InvalidArgumentException;
use Oshim\Epp\Xml\EppXmlBuilder;
use Oshim\Tests\Harness\TestCase;

class EppXmlBuilderTest extends TestCase
{
    public function testBuildHelloEnvelope(): void
    {
        $xml = EppXmlBuilder::buildHello();
        $this->assertStringContains('<hello/>', $xml);
        $this->assertStringContains('xmlns="urn:ietf:params:xml:ns:epp-1.0"', $xml);
    }

    public function testBuildLoginEnvelope(): void
    {
        $xml = EppXmlBuilder::buildLogin(
            'REGISTRAR_01',
            'SecretPass123!',
            'NewSecretPass456!',
            ['version' => '1.0', 'lang' => 'en'],
            [EppXmlBuilder::NS_DOMAIN, EppXmlBuilder::NS_HOST],
            [EppXmlBuilder::NS_SECDNS],
            'TRID-LOGIN-99'
        );

        $this->assertStringContains('<clID>REGISTRAR_01</clID>', $xml);
        $this->assertStringContains('<pw>SecretPass123!</pw>', $xml);
        $this->assertStringContains('<newPW>NewSecretPass456!</newPW>', $xml);
        $this->assertStringContains('<objURI>' . EppXmlBuilder::NS_DOMAIN . '</objURI>', $xml);
        $this->assertStringContains('<extURI>' . EppXmlBuilder::NS_SECDNS . '</extURI>', $xml);
        $this->assertStringContains('<clTRID>TRID-LOGIN-99</clTRID>', $xml);
    }

    public function testBuildLogoutEnvelope(): void
    {
        $xml = EppXmlBuilder::buildLogout('TRID-LOGOUT-01');
        $this->assertStringContains('<logout/>', $xml);
        $this->assertStringContains('<clTRID>TRID-LOGOUT-01</clTRID>', $xml);
    }

    public function testBuildPollEnvelope(): void
    {
        // Poll req
        $xmlReq = EppXmlBuilder::buildPoll('req', null, 'TRID-POLL-REQ');
        $this->assertStringContains('<poll op="req"/>', $xmlReq);
        $this->assertStringContains('<clTRID>TRID-POLL-REQ</clTRID>', $xmlReq);

        // Poll ack
        $xmlAck = EppXmlBuilder::buildPoll('ack', 'MSG-12345', 'TRID-POLL-ACK');
        $this->assertStringContains('<poll op="ack" msgID="MSG-12345"/>', $xmlAck);
        $this->assertStringContains('<clTRID>TRID-POLL-ACK</clTRID>', $xmlAck);
    }

    public function testBuildDomainCheckEnvelope(): void
    {
        $xml = EppXmlBuilder::buildDomainCheck(['test1.com', 'test2.net'], 'TRID-CHK-01');
        $this->assertStringContains('<domain:check', $xml);
        $this->assertStringContains('<domain:name>test1.com</domain:name>', $xml);
        $this->assertStringContains('<domain:name>test2.net</domain:name>', $xml);
        $this->assertStringContains('<clTRID>TRID-CHK-01</clTRID>', $xml);
    }

    public function testBuildDomainInfoEnvelope(): void
    {
        $xml = EppXmlBuilder::buildDomainInfo('example.org', 'AuthPwSecret!', 'all', 'TRID-INF-01');
        $this->assertStringContains('<domain:info', $xml);
        $this->assertStringContains('<domain:name hosts="all">example.org</domain:name>', $xml);
        $this->assertStringContains('<domain:pw>AuthPwSecret!</domain:pw>', $xml);
        $this->assertStringContains('<clTRID>TRID-INF-01</clTRID>', $xml);
    }

    public function testBuildDomainCreateEnvelope(): void
    {
        $xml = EppXmlBuilder::buildDomainCreate(
            'brand-new-domain.cloud',
            2,
            ['ns1.nameserver.org', 'ns2.nameserver.org'],
            'REG-CONTACT-01',
            'AuthSecretDomain!123',
            ['admin' => 'ADM-01', 'tech' => 'TEC-01'],
            'TRID-CRE-01'
        );

        $this->assertStringContains('<domain:create', $xml);
        $this->assertStringContains('<domain:name>brand-new-domain.cloud</domain:name>', $xml);
        $this->assertStringContains('<domain:period unit="y">2</domain:period>', $xml);
        $this->assertStringContains('<domain:hostObj>ns1.nameserver.org</domain:hostObj>', $xml);
        $this->assertStringContains('<domain:registrant>REG-CONTACT-01</domain:registrant>', $xml);
        $this->assertStringContains('<domain:contact type="admin">ADM-01</domain:contact>', $xml);
        $this->assertStringContains('<domain:pw>AuthSecretDomain!123</domain:pw>', $xml);
        $this->assertStringContains('<clTRID>TRID-CRE-01</clTRID>', $xml);
    }

    public function testBuildDomainRenewEnvelope(): void
    {
        $xml = EppXmlBuilder::buildDomainRenew('renew-me.com', '2026-12-31', 3, 'TRID-REN-01');
        $this->assertStringContains('<domain:renew', $xml);
        $this->assertStringContains('<domain:name>renew-me.com</domain:name>', $xml);
        $this->assertStringContains('<domain:curExpDate>2026-12-31</domain:curExpDate>', $xml);
        $this->assertStringContains('<domain:period unit="y">3</domain:period>', $xml);
    }

    public function testBuildDomainTransferEnvelope(): void
    {
        $xml = EppXmlBuilder::buildDomainTransfer('transfer-target.com', 'TransferAuthPw#1', 'request', 1, 'TRID-TRN-01');
        $this->assertStringContains('<transfer op="request">', $xml);
        $this->assertStringContains('<domain:name>transfer-target.com</domain:name>', $xml);
        $this->assertStringContains('<domain:pw>TransferAuthPw#1</domain:pw>', $xml);
        $this->assertStringContains('<domain:period unit="y">1</domain:period>', $xml);
    }

    public function testBuildDomainUpdateEnvelope(): void
    {
        $xml = EppXmlBuilder::buildDomainUpdate(
            'update-target.com',
            ['ns' => ['ns3.nameserver.org'], 'status' => ['clientHold']],
            ['ns' => ['ns1.nameserver.org']],
            ['registrant' => 'NEW-REGISTRANT-ID', 'authInfo' => 'NewDomainPassword123'],
            'TRID-UPD-01'
        );

        $this->assertStringContains('<domain:update', $xml);
        $this->assertStringContains('<domain:add>', $xml);
        $this->assertStringContains('<domain:hostObj>ns3.nameserver.org</domain:hostObj>', $xml);
        $this->assertStringContains('<domain:status s="clientHold"/>', $xml);
        $this->assertStringContains('<domain:rem>', $xml);
        $this->assertStringContains('<domain:hostObj>ns1.nameserver.org</domain:hostObj>', $xml);
        $this->assertStringContains('<domain:chg>', $xml);
        $this->assertStringContains('<domain:registrant>NEW-REGISTRANT-ID</domain:registrant>', $xml);
        $this->assertStringContains('<domain:pw>NewDomainPassword123</domain:pw>', $xml);
    }

    public function testBuildDomainDeleteEnvelope(): void
    {
        $xml = EppXmlBuilder::buildDomainDelete('delete-target.com', 'TRID-DEL-01');
        $this->assertStringContains('<domain:delete', $xml);
        $this->assertStringContains('<domain:name>delete-target.com</domain:name>', $xml);
    }

    public function testBuildHostCreateWithIpv4AndIpv6Glue(): void
    {
        $xml = EppXmlBuilder::buildHostCreate(
            'ns1.customdomain.cloud',
            ['192.0.2.53', '198.51.100.53'],
            ['2001:db8:1::53', '2001:db8:2::53'],
            'TRID-HST-CRE'
        );

        $this->assertStringContains('<host:create', $xml);
        $this->assertStringContains('<host:name>ns1.customdomain.cloud</host:name>', $xml);
        $this->assertStringContains('<host:addr ip="v4">192.0.2.53</host:addr>', $xml);
        $this->assertStringContains('<host:addr ip="v4">198.51.100.53</host:addr>', $xml);
        $this->assertStringContains('<host:addr ip="v6">2001:db8:1::53</host:addr>', $xml);
        $this->assertStringContains('<host:addr ip="v6">2001:db8:2::53</host:addr>', $xml);
    }

    public function testBuildHostCreateRejectsInvalidIpAddresses(): void
    {
        $this->assertThrows(InvalidArgumentException::class, function () {
            EppXmlBuilder::buildHostCreate('ns1.badip.com', ['999.999.999.999']);
        }, 'Invalid IPv4');

        $this->assertThrows(InvalidArgumentException::class, function () {
            EppXmlBuilder::buildHostCreate('ns1.badip.com', [], ['not-a-valid-ipv6']);
        }, 'Invalid IPv6');
    }

    public function testBuildContactCreateEnvelope(): void
    {
        $postalInfo = [
            'type' => 'int',
            'name' => 'Jane Doe',
            'org' => 'Enterprise Corp',
            'street' => ['123 Main St', 'Suite 400'],
            'city' => 'Metropolis',
            'sp' => 'NY',
            'pc' => '10001',
            'cc' => 'US',
        ];

        $xml = EppXmlBuilder::buildContactCreate(
            'CNT-USER-101',
            $postalInfo,
            'janedoe@example.com',
            '+1.2125550100',
            '+1.2125550101',
            'ContactAuthSecret99',
            'TRID-CNT-CRE'
        );

        $this->assertStringContains('<contact:create', $xml);
        $this->assertStringContains('<contact:id>CNT-USER-101</contact:id>', $xml);
        $this->assertStringContains('<contact:name>Jane Doe</contact:name>', $xml);
        $this->assertStringContains('<contact:org>Enterprise Corp</contact:org>', $xml);
        $this->assertStringContains('<contact:street>123 Main St</contact:street>', $xml);
        $this->assertStringContains('<contact:street>Suite 400</contact:street>', $xml);
        $this->assertStringContains('<contact:city>Metropolis</contact:city>', $xml);
        $this->assertStringContains('<contact:cc>US</contact:cc>', $xml);
        $this->assertStringContains('<contact:voice>+1.2125550100</contact:voice>', $xml);
        $this->assertStringContains('<contact:fax>+1.2125550101</contact:fax>', $xml);
        $this->assertStringContains('<contact:email>janedoe@example.com</contact:email>', $xml);
        $this->assertStringContains('<contact:pw>ContactAuthSecret99</contact:pw>', $xml);
    }

    public function testBuildContactCreateWithVoiceAndFax(): void
    {
        $postalInfo = [
            'type' => 'int',
            'name' => 'Jane Doe',
            'org' => 'Enterprise Corp',
            'street' => ['123 Main St'],
            'city' => 'Metropolis',
            'cc' => 'US',
        ];

        $xml = EppXmlBuilder::buildContactCreate(
            'CNT-USER-101',
            $postalInfo,
            'janedoe@example.com',
            '+1.2125550100',
            '+1.2125550101',
            'ContactAuthSecret99',
            'TRID-CNT-CRE'
        );

        $this->assertStringContains('<contact:voice>+1.2125550100</contact:voice>', $xml);
        $this->assertStringContains('<contact:fax>+1.2125550101</contact:fax>', $xml);
        $this->assertFalse(str_contains($xml, '<contact:voice><contact:voice>'));
    }

    public function testBuildContactCreateWithoutVoiceOrFax(): void
    {
        $postalInfo = [
            'type' => 'int',
            'name' => 'Jane Doe',
            'city' => 'Metropolis',
            'cc' => 'US',
        ];

        $xml = EppXmlBuilder::buildContactCreate(
            'CNT-USER-102',
            $postalInfo,
            'janedoe@example.com',
            null,
            null
        );

        $this->assertFalse(str_contains($xml, '<contact:voice>'));
        $this->assertFalse(str_contains($xml, '<contact:fax>'));
    }

    public function testBuildDomainUpdateWithContactArrayFormats(): void
    {
        $xml1 = EppXmlBuilder::buildDomainUpdate(
            'test.com',
            ['contact' => ['tech' => ['TEC-1', 'TEC-2']]]
        );
        $this->assertStringContains('<domain:contact type="tech">TEC-1</domain:contact>', $xml1);
        $this->assertStringContains('<domain:contact type="tech">TEC-2</domain:contact>', $xml1);
        $this->assertFalse(str_contains($xml1, 'Array'));

        $xml2 = EppXmlBuilder::buildDomainUpdate(
            'test.com',
            ['contact' => [['type' => 'admin', 'id' => 'ADM-1'], ['type' => 'billing', 'id' => 'BIL-1']]]
        );
        $this->assertStringContains('<domain:contact type="admin">ADM-1</domain:contact>', $xml2);
        $this->assertStringContains('<domain:contact type="billing">BIL-1</domain:contact>', $xml2);
    }

    public function testBuildHostUpdateWithValidAndInvalidIps(): void
    {
        $xml = EppXmlBuilder::buildHostUpdate(
            'ns1.example.com',
            ['192.0.2.1'],
            ['192.0.2.2'],
            ['2001:db8::1'],
            ['2001:db8::2'],
            'ns2.example.com',
            'TRID-HST-UPD'
        );
        $this->assertStringContains('<host:update', $xml);
        $this->assertStringContains('<host:name>ns1.example.com</host:name>', $xml);
        $this->assertStringContains('<host:addr ip="v4">192.0.2.1</host:addr>', $xml);
        $this->assertStringContains('<host:addr ip="v6">2001:db8::1</host:addr>', $xml);
        $this->assertStringContains('<host:addr ip="v4">192.0.2.2</host:addr>', $xml);
        $this->assertStringContains('<host:addr ip="v6">2001:db8::2</host:addr>', $xml);
        $this->assertStringContains('<host:name>ns2.example.com</host:name>', $xml);

        $this->assertThrows(InvalidArgumentException::class, function () {
            EppXmlBuilder::buildHostUpdate('ns1.bad.com', ['999.999.999.999']);
        }, 'Invalid IPv4');

        $this->assertThrows(InvalidArgumentException::class, function () {
            EppXmlBuilder::buildHostUpdate('ns1.bad.com', [], [], ['invalid-ipv6']);
        }, 'Invalid IPv6');
    }

    public function testXmlAttributeEscapingWithQuotes(): void
    {
        $xmlPoll = EppXmlBuilder::buildPoll('req', 'msg" evil="1');
        $this->assertStringContains('msgID="msg&quot; evil=&quot;1"', $xmlPoll);

        $xmlDomain = EppXmlBuilder::buildDomainInfo('example.com', 'pass', 'all" evil="1');
        $this->assertStringContains('hosts="all&quot; evil=&quot;1"', $xmlDomain);
    }
}
