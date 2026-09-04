<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Security\Totp\TotpEngine;
use Oshim\Security\Oidc\OidcTokenProvider;
use RuntimeException;

final class TotpOidcTest extends TestCase
{
    public function testTotpSecretGenerationAndCodeVerification(): void
    {
        $secret = TotpEngine::generateSecret(20);
        $this->assertNotEmpty($secret);

        $now = time();
        $code = TotpEngine::generateCode($secret, $now);
        $this->assertSame(6, strlen($code));
        $this->assertTrue(is_numeric($code));

        $this->assertTrue(TotpEngine::verify($secret, $code, 1, $now));
        $this->assertFalse(TotpEngine::verify($secret, '000000', 1, $now));

        $uri = TotpEngine::getProvisioningUri($secret, 'admin@oshim.cloud', 'OSHIM Sovereign');
        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString("secret={$secret}", $uri);
    }

    public function testOidcAuthCodeAndPkceExchange(): void
    {
        $provider = new OidcTokenProvider('https://auth.oshim.test', 'super-secret-key-32-chars-long!');
        $provider->registerClient('client-app-123', 'secret-456', ['https://app.test/callback'], ['openid', 'email']);

        // PKCE setup
        $codeVerifier = bin2hex(random_bytes(32));
        $codeChallenge = OidcTokenProvider::computePkceChallenge($codeVerifier, 'S256');

        $authCode = $provider->createAuthCode(
            'client-app-123',
            'https://app.test/callback',
            'user-uuid-999',
            ['openid', 'email'],
            $codeChallenge,
            'S256'
        );

        $this->assertNotEmpty($authCode);

        // Exchange code with PKCE
        $tokens = $provider->exchangeAuthCode(
            $authCode,
            'client-app-123',
            'https://app.test/callback',
            $codeVerifier
        );

        $this->assertArrayHasKey('access_token', $tokens);
        $this->assertArrayHasKey('id_token', $tokens);
        $this->assertSame('Bearer', $tokens['token_type']);

        // Verify JWT claims
        $claims = $provider->verifyJwt($tokens['access_token']);
        $this->assertSame('user-uuid-999', $claims['sub']);
        $this->assertSame('client-app-123', $claims['aud']);
        $this->assertSame('https://auth.oshim.test', $claims['iss']);
    }
}
