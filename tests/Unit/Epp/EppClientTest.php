<?php
declare(strict_types=1);

namespace Oshim\Tests\Unit\Epp;

use Oshim\Epp\EppClient;
use Oshim\Epp\Exceptions\EppAuthException;
use Oshim\Epp\Exceptions\EppObjectExistsException;
use Oshim\Epp\Exceptions\EppObjectNotFoundException;
use Oshim\Epp\Transport\MemoryTransport;
use Oshim\Tests\Harness\TestCase;
use Oshim\Tests\Harness\MockEppRegistry;

class EppClientTest extends TestCase
{
    private MockEppRegistry $mockRegistry;
    private MemoryTransport $transport;
    private EppClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockRegistry = $this->createMockEppRegistry();
        $this->transport = new MemoryTransport(
            $this->mockRegistry->generateGreeting(),
            fn(string $xml) => $this->mockRegistry->unframeXml(
                $this->mockRegistry->dispatch($this->mockRegistry->frameXml($xml))
            )
        );
        $this->client = new EppClient($this->transport);
    }

    public function testClientConnectAndReceiveGreeting(): void
    {
        $greeting = $this->client->connect();
        $this->assertSame('OSHIM-MOCK-EPP-REGISTRY-v1.0', $greeting->getServerIdentifier());
        $this->assertContains('urn:ietf:params:xml:ns:domain-1.0', $greeting->getObjectUris());
        $this->assertSame($greeting, $this->client->getGreeting());
    }

    public function testClientLoginSuccess(): void
    {
        $resp = $this->client->login('OSHIM_REGISTRAR', 'ValidRegistrarPassword');
        $this->assertSame(1000, $resp->getCode());
        $this->assertTrue($resp->isSuccess());
    }

    public function testClientLoginFailureThrowsAuthException(): void
    {
        $this->assertThrows(EppAuthException::class, function () {
            $this->client->login('OSHIM_REGISTRAR', 'invalid_pw');
        });
    }

    public function testClientLogout(): void
    {
        $this->client->login('OSHIM_REGISTRAR', 'ValidRegistrarPassword');
        $resp = $this->client->logout();
        $this->assertSame(1500, $resp->getCode());
        $this->assertFalse($this->transport->isConnected());
    }

    public function testCheckDomainsWorkflow(): void
    {
        $this->client->login('OSHIM_REGISTRAR', 'ValidRegistrarPassword');
        $results = $this->client->checkDomains(['free-domain.cloud', 'another-free.cloud']);
        $this->assertCount(2, $results);
        $this->assertTrue($results['free-domain.cloud']->isAvailable());
        $this->assertTrue($results['another-free.cloud']->isAvailable());
    }

    public function testCreateAndGetDomainInfoWorkflow(): void
    {
        $this->client->login('OSHIM_REGISTRAR', 'ValidRegistrarPassword');
        // 1. Create domain
        $createResp = $this->client->createDomain(
            'my-test-domain.cloud',
            2,
            ['ns1.nameserver.org', 'ns2.nameserver.org'],
            'REG-1001',
            'AuthSecretDomain!123',
            ['admin' => 'ADM-1001', 'tech' => 'TEC-1001']
        );
        $this->assertSame(1000, $createResp->getCode());
        $this->assertTrue($createResp->isSuccess());

        // 2. Query info
        $info = $this->client->getDomainInfo('my-test-domain.cloud', 'AuthSecretDomain!123');
        $this->assertSame('my-test-domain.cloud', $info->getName());
        $this->assertSame('REG-1001', $info->getRegistrant());
        $this->assertContains('ns1.nameserver.org', $info->getNameservers());
        $this->assertSame('AuthSecretDomain!123', $info->getAuthPw());

        // 3. Check domain is no longer available
        $check = $this->client->checkDomains(['my-test-domain.cloud']);
        $this->assertFalse($check['my-test-domain.cloud']->isAvailable());
    }

    public function testCreateDuplicateDomainThrowsObjectExistsException(): void
    {
        $this->client->login('OSHIM_REGISTRAR', 'ValidRegistrarPassword');
        $this->client->createDomain(
            'duplicate.cloud',
            1,
            ['ns1.nameserver.org'],
            'REG-1',
            'Auth123'
        );

        $this->assertThrows(EppObjectExistsException::class, function () {
            $this->client->createDomain(
                'duplicate.cloud',
                1,
                ['ns1.nameserver.org'],
                'REG-1',
                'Auth123'
            );
        });
    }

    public function testGetNonExistentDomainInfoThrowsObjectNotFoundException(): void
    {
        $this->client->login('OSHIM_REGISTRAR', 'ValidRegistrarPassword');
        $this->assertThrows(EppObjectNotFoundException::class, function () {
            $this->client->getDomainInfo('non-existent-domain-404.cloud');
        });
    }

    public function testDomainRenewWorkflow(): void
    {
        $this->client->login('OSHIM_REGISTRAR', 'ValidRegistrarPassword');
        $this->client->createDomain('renew-domain.cloud', 1, ['ns1.nameserver.org'], 'REG-1', 'Auth123');
        $infoBefore = $this->client->getDomainInfo('renew-domain.cloud');
        $initialExp = $infoBefore->getExDate();

        $renewResp = $this->client->renewDomain('renew-domain.cloud', (string)$initialExp, 2);
        $this->assertSame(1000, $renewResp->getCode());

        $infoAfter = $this->client->getDomainInfo('renew-domain.cloud');
        $this->assertGreaterThan(strtotime((string)$initialExp), strtotime((string)$infoAfter->getExDate()));
    }

    public function testDomainTransferWorkflow(): void
    {
        $this->client->login('OSHIM_REGISTRAR', 'ValidRegistrarPassword');
        $this->client->createDomain('transfer-domain.cloud', 1, ['ns1.nameserver.org'], 'REG-1', 'TransferSecret123!');

        // Valid transfer request
        $trnResp = $this->client->transferDomain('transfer-domain.cloud', 'TransferSecret123!', 'request');
        $this->assertSame(1001, $trnResp->getCode());
        $this->assertTrue($trnResp->isPending());

        // Invalid transfer password throws EppAuthException
        $this->assertThrows(EppAuthException::class, function () {
            $this->client->transferDomain('transfer-domain.cloud', 'WrongPassword!', 'request');
        });
    }

    public function testUpdateNameserversWorkflow(): void
    {
        $this->client->login('OSHIM_REGISTRAR', 'ValidRegistrarPassword');
        $this->client->createDomain('update-ns-domain.cloud', 1, ['ns1.old.org', 'ns2.old.org'], 'REG-1', 'Auth123');

        $this->client->updateNameservers('update-ns-domain.cloud', ['ns1.new.org'], ['ns1.old.org']);
        $info = $this->client->getDomainInfo('update-ns-domain.cloud');

        $this->assertContains('ns1.new.org', $info->getNameservers());
        $this->assertNotContains('ns1.old.org', $info->getNameservers());
    }

    public function testHostGlueCreationWorkflow(): void
    {
        $this->client->login('OSHIM_REGISTRAR', 'ValidRegistrarPassword');
        $hostResp = $this->client->createHost(
            'ns1.customdns.cloud',
            ['203.0.113.10'],
            ['2001:db8::10']
        );
        $this->assertSame(1000, $hostResp->getCode());

        $check = $this->client->checkHosts(['ns1.customdns.cloud', 'ns2.customdns.cloud']);
        $this->assertFalse($check['ns1.customdns.cloud']); // Taken
        $this->assertTrue($check['ns2.customdns.cloud']);  // Available
    }
}
