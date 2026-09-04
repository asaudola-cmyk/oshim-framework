<?php
declare(strict_types=1);

namespace Tests\Unit\Security;

use Oshim\Testing\TestCase;
use Oshim\Security\Ssl\AcmeV2Client;
use Oshim\Security\Cipher;

class AcmeV2ClientTest extends TestCase
{
    public function testAcmeV2ClientAccountKeyAndDirectory(): void
    {
        $client = new AcmeV2Client('security@oshim.cloud');
        $key = $client->generateAccountKey();
        $this->assertStringContainsString('PRIVATE KEY', $key);
        $this->assertSame($key, $client->getAccountKey());

        // Test getDirectory / fetchDirectory
        $dir = $client->getDirectory();
        $this->assertIsArray($dir);
        $this->assertArrayHasKey('newNonce', $dir);
        $this->assertArrayHasKey('newAccount', $dir);
        $this->assertArrayHasKey('newOrder', $dir);
        $this->assertArrayHasKey('revokeCert', $dir);

        $this->assertSame($dir, $client->fetchDirectory());

        // Test getNewNonce / getNonce
        $nonce = $client->getNewNonce();
        $this->assertNotEmpty($nonce);
        $this->assertIsString($nonce);
    }

    public function testAcmeV2JwsSigning(): void
    {
        $client = new AcmeV2Client('security@oshim.cloud');

        // Test JWK format and thumbprint
        $jwk = $client->getJwk();
        $this->assertSame('RSA', $jwk['kty']);
        $this->assertNotEmpty($jwk['e']);
        $this->assertNotEmpty($jwk['n']);

        $thumbprint = $client->getJwkThumbprint();
        $this->assertNotEmpty($thumbprint);
        $this->assertIsString($thumbprint);

        // 1. Sign JWS with JWK (Account creation)
        $url = 'https://acme-v02.api.letsencrypt.org/acme/new-acct';
        $payload = ['termsOfServiceAgreed' => true, 'contact' => ['mailto:security@oshim.cloud']];
        $jwsWithJwk = $client->signJws($url, $payload, null);

        $this->assertArrayHasKey('protected', $jwsWithJwk);
        $this->assertArrayHasKey('payload', $jwsWithJwk);
        $this->assertArrayHasKey('signature', $jwsWithJwk);

        $protectedJson = json_decode((string)Cipher::base64UrlDecode($jwsWithJwk['protected']), true);
        $this->assertSame('RS256', $protectedJson['alg']);
        $this->assertSame($url, $protectedJson['url']);
        $this->assertArrayHasKey('jwk', $protectedJson);
        $this->assertFalse(isset($protectedJson['kid']));

        // 2. Sign JWS with KID (Authenticated operations)
        $kid = 'https://acme-v02.api.letsencrypt.org/acme/acct/12345678';
        $orderUrl = 'https://acme-v02.api.letsencrypt.org/acme/new-order';
        $jwsWithKid = $client->signJws($orderUrl, ['identifiers' => [['type' => 'dns', 'value' => 'oshim.cloud']]], $kid);

        $protectedKidJson = json_decode((string)Cipher::base64UrlDecode($jwsWithKid['protected']), true);
        $this->assertSame('RS256', $protectedKidJson['alg']);
        $this->assertSame($orderUrl, $protectedKidJson['url']);
        $this->assertSame($kid, $protectedKidJson['kid']);
        $this->assertFalse(isset($protectedKidJson['jwk']));
    }

    public function testAcmeV2AccountRegistrationAndOrder(): void
    {
        $client = new AcmeV2Client('admin@oshim.cloud');
        $acct = $client->registerAccount('admin@oshim.cloud');

        $this->assertSame('valid', $acct['status']);
        $this->assertNotEmpty($acct['account_url']);
        $this->assertSame($acct['account_url'], $client->getAccountUrl());

        $order = $client->createOrder(['oshim.cloud', 'api.oshim.cloud']);
        $this->assertIsArray($order);
        $this->assertSame('pending', $order['status']);
        $this->assertCount(2, $order['identifiers']);
        $this->assertArrayHasKey('authorizations', $order);
        $this->assertArrayHasKey('finalize', $order);
    }

    public function testAcmeV2ChallengesAndVerification(): void
    {
        $client = new AcmeV2Client('admin@oshim.cloud');
        $authUrl = 'https://acme-v02.api.letsencrypt.org/acme/authz/mock_auth_01';
        $challengeData = $client->getChallenges($authUrl);

        $this->assertArrayHasKey('challenges', $challengeData);
        $this->assertCount(2, $challengeData['challenges']);

        $http01 = $challengeData['challenges'][0];
        $this->assertSame('http-01', $http01['type']);
        $this->assertNotEmpty($http01['token']);
        $this->assertNotEmpty($http01['key_authorization']);
        $this->assertStringContainsString('/.well-known/acme-challenge/', $http01['http_path']);

        $dns01 = $challengeData['challenges'][1];
        $this->assertSame('dns-01', $dns01['type']);
        $this->assertNotEmpty($dns01['dns_record']);
        $this->assertNotEmpty($dns01['dns_value']);

        // Verify challenge
        $verifyResult = $client->verifyChallenge($http01['url']);
        $this->assertSame('valid', $verifyResult['status']);
    }

    public function testAcmeV2FinalizeAndDownloadCertificate(): void
    {
        $client = new AcmeV2Client('admin@oshim.cloud');
        $finalizeUrl = 'https://acme-v02.api.letsencrypt.org/acme/finalize/mock_finalize_01';

        $mockCsr = "-----BEGIN CERTIFICATE REQUEST-----\n" . base64_encode(random_bytes(128)) . "\n-----END CERTIFICATE REQUEST-----";
        $finalized = $client->finalizeOrder($finalizeUrl, $mockCsr);

        $this->assertSame('valid', $finalized['status']);
        $this->assertArrayHasKey('certificate', $finalized);

        $certChainPem = $client->downloadCertificate($finalized['certificate']);
        $this->assertStringContainsString('BEGIN CERTIFICATE', $certChainPem);
    }

    public function testAcmeV2RequestCertificateGeneratesChallenges(): void
    {
        $client = new AcmeV2Client('admin@oshim.cloud');
        $cert = $client->requestCertificate('oshim.cloud', ['api.oshim.cloud']);

        $this->assertSame('valid', $cert['status']);
        $this->assertSame('oshim.cloud', $cert['domain']);
        $this->assertCount(2, $cert['san']);
        $this->assertArrayHasKey('challenges', $cert);
        $this->assertArrayHasKey('oshim.cloud', $cert['challenges']);
        $this->assertArrayHasKey('http_path', $cert['challenges']['oshim.cloud']);
        $this->assertStringContainsString('/.well-known/acme-challenge/', $cert['challenges']['oshim.cloud']['http_path']);
        $this->assertArrayHasKey('dns_record', $cert['challenges']['oshim.cloud']);
        $this->assertArrayHasKey('dns_value', $cert['challenges']['oshim.cloud']);
        $this->assertStringContainsString('BEGIN CERTIFICATE', $cert['certificate_pem']);
        $this->assertStringContainsString('BEGIN CERTIFICATE', $cert['chain_pem']);
        $this->assertStringContainsString('PRIVATE KEY', $cert['private_key_pem']);
        $this->assertTrue($cert['auto_renew']);
    }
}

