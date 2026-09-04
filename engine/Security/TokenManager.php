<?php
declare(strict_types=1);

namespace Oshim\Security;

/**
 * Stateless Ed25519-Signed Auth Token Manager.
 * Format: oshim.<header_b64url>.<payload_b64url>.<signature_b64url>
 */
final class TokenManager
{
    public const PREFIX = 'oshim';
    public const ISSUER = 'oshim-cloud';

    /**
     * Issue a signed cryptographic token.
     */
    public static function issue(array $claims, string $secretKey, int $ttlSeconds = 86400): string
    {
        $now = time();

        $header = [
            'typ' => 'OSHIM+Ed25519',
            'alg' => 'Ed25519',
        ];

        $payload = array_merge([
            'iss'         => self::ISSUER,
            'iat'         => $now,
            'nbf'         => $now,
            'exp'         => $now + $ttlSeconds,
            'jti'         => bin2hex(random_bytes(16)),
            'role'        => 'client',
            'permissions' => [],
        ], $claims);

        $headerEncoded = Cipher::base64UrlEncode((string)json_encode($header, JSON_UNESCAPED_SLASHES));
        $payloadEncoded = Cipher::base64UrlEncode((string)json_encode($payload, JSON_UNESCAPED_SLASHES));

        $signingInput = "{$headerEncoded}.{$payloadEncoded}";
        $signature = Ed25519Signer::sign($signingInput, $secretKey);

        return self::PREFIX . ".{$headerEncoded}.{$payloadEncoded}.{$signature}";
    }

    /**
     * Verify and decode a token against an Ed25519 public key.
     *
     * @return array<string, mixed>|null Payload claims or null if invalid/expired.
     */
    public static function verify(string $token, string $publicKey, int $clockSkew = 60): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 4 || $parts[0] !== self::PREFIX) {
            return null;
        }

        [, $headerEncoded, $payloadEncoded, $signature] = $parts;
        $signingInput = "{$headerEncoded}.{$payloadEncoded}";

        // Verify cryptographic signature
        if (!Ed25519Signer::verify($signature, $signingInput, $publicKey)) {
            return null;
        }

        $payloadJson = Cipher::base64UrlDecode($payloadEncoded);
        $payload = json_decode($payloadJson, true);

        if (!is_array($payload)) {
            return null;
        }

        $now = time();

        // Verify expiration
        if (isset($payload['exp']) && ($now - $clockSkew) > $payload['exp']) {
            return null;
        }

        // Verify not before
        if (isset($payload['nbf']) && ($now + $clockSkew) < $payload['nbf']) {
            return null;
        }

        return $payload;
    }

    /**
     * Parse token claims without signature verification.
     */
    public static function parse(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 4 || $parts[0] !== self::PREFIX) {
            return null;
        }

        $payloadJson = Cipher::base64UrlDecode($parts[2]);
        $payload = json_decode($payloadJson, true);

        return is_array($payload) ? $payload : null;
    }
}
