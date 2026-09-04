<?php
declare(strict_types=1);

namespace Oshim\Security\Oidc;

use RuntimeException;
use InvalidArgumentException;

/**
 * Sovereign OAuth2 & OpenID Connect (OIDC) Token Provider with PKCE (S256).
 */
class OidcTokenProvider
{
    private string $issuer;
    private string $signingKey;
    private array $registeredClients = [];
    private array $authCodes = [];

    public function __construct(string $issuer = 'https://auth.oshim.cloud', ?string $signingKey = null)
    {
        $this->issuer = $issuer;
        $this->signingKey = $signingKey ?? 'oshim_oidc_secret_signing_key_3847291';
    }

    public function registerClient(string $clientId, string $clientSecret, array $redirectUris, array $scopes = ['openid', 'profile', 'email']): void
    {
        $this->registeredClients[$clientId] = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uris' => $redirectUris,
            'scopes' => $scopes,
        ];
    }

    /**
     * Create an authorization code with PKCE challenge.
     */
    public function createAuthCode(
        string $clientId,
        string $redirectUri,
        string $userId,
        array $scopes = ['openid'],
        ?string $codeChallenge = null,
        string $codeChallengeMethod = 'S256'
    ): string {
        if (!isset($this->registeredClients[$clientId])) {
            throw new InvalidArgumentException("Client ID '{$clientId}' is not registered.");
        }

        $code = bin2hex(random_bytes(24));
        $this->authCodes[$code] = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'user_id' => $userId,
            'scopes' => $scopes,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => $codeChallengeMethod,
            'expires_at' => time() + 300, // 5 minutes
        ];

        return $code;
    }

    /**
     * Exchange authorization code for ID Token & Access Token using PKCE verification.
     */
    public function exchangeAuthCode(
        string $code,
        string $clientId,
        string $redirectUri,
        ?string $codeVerifier = null,
        ?string $clientSecret = null
    ): array {
        if (!isset($this->authCodes[$code])) {
            throw new RuntimeException("Invalid or expired authorization code.");
        }

        $record = $this->authCodes[$code];
        unset($this->authCodes[$code]); // single use

        if ($record['expires_at'] < time()) {
            throw new RuntimeException("Authorization code has expired.");
        }

        if ($record['client_id'] !== $clientId || $record['redirect_uri'] !== $redirectUri) {
            throw new RuntimeException("Client or redirect URI mismatch.");
        }

        // Verify PKCE if challenge was set
        if (!empty($record['code_challenge'])) {
            if ($codeVerifier === null) {
                throw new RuntimeException("PKCE code_verifier is required.");
            }

            $computedChallenge = self::computePkceChallenge($codeVerifier, $record['code_challenge_method']);
            if (!hash_equals($record['code_challenge'], $computedChallenge)) {
                throw new RuntimeException("PKCE verification failed.");
            }
        }

        $userId = $record['user_id'];
        $scopes = $record['scopes'];

        $accessToken = $this->generateJwt([
            'iss' => $this->issuer,
            'sub' => $userId,
            'aud' => $clientId,
            'scope' => implode(' ', $scopes),
            'exp' => time() + 3600,
            'iat' => time(),
            'token_type' => 'Bearer',
        ]);

        $idToken = null;
        if (in_array('openid', $scopes, true)) {
            $idToken = $this->generateJwt([
                'iss' => $this->issuer,
                'sub' => $userId,
                'aud' => $clientId,
                'exp' => time() + 3600,
                'iat' => time(),
                'auth_time' => time(),
            ]);
        }

        return [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'id_token' => $idToken,
            'scope' => implode(' ', $scopes),
        ];
    }

    public static function computePkceChallenge(string $verifier, string $method = 'S256'): string
    {
        if ($method === 'plain') {
            return $verifier;
        }

        $hash = hash('sha256', $verifier, true);
        return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    }

    public function generateJwt(array $claims): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $encodedHeader = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
        $encodedPayload = rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=');

        $signature = hash_hmac('sha256', "{$encodedHeader}.{$encodedPayload}", $this->signingKey, true);
        $encodedSignature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        return "{$encodedHeader}.{$encodedPayload}.{$encodedSignature}";
    }

    public function verifyJwt(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new RuntimeException("Malformed JWT string.");
        }

        [$headerB64, $payloadB64, $sigB64] = $parts;
        $expectedSig = hash_hmac('sha256', "{$headerB64}.{$payloadB64}", $this->signingKey, true);
        $computedSigB64 = rtrim(strtr(base64_encode($expectedSig), '+/', '-_'), '=');

        if (!hash_equals($computedSigB64, $sigB64)) {
            throw new RuntimeException("JWT signature verification failed.");
        }

        $payloadJson = base64_decode(strtr($payloadB64, '-_', '+/'));
        $claims = json_decode($payloadJson, true);

        if (!is_array($claims)) {
            throw new RuntimeException("Invalid JWT claims payload.");
        }

        if (isset($claims['exp']) && $claims['exp'] < time()) {
            throw new RuntimeException("JWT token has expired.");
        }

        return $claims;
    }
}
