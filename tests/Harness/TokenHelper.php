<?php
declare(strict_types=1);

namespace Oshim\Tests\Harness;

/**
 * Helper for generating valid test JWT/Ed25519/HMAC authentication tokens.
 */
class TokenHelper
{
    private static ?string $keyPair = null;

    public static function generateTestToken(array|object $user, string $role = 'client', array $extraClaims = []): string
    {
        $userData = (array)$user;
        $header = [
            'alg' => 'EdDSA',
            'typ' => 'JWT',
        ];

        $payload = array_merge([
            'sub' => (string)($userData['id'] ?? '1'),
            'email' => $userData['email'] ?? 'test@oshim.cloud',
            'role' => $userData['role'] ?? $role,
            'iat' => time(),
            'exp' => time() + 3600,
        ], $extraClaims);

        $base64Header = self::base64UrlEncode((string)json_encode($header));
        $base64Payload = self::base64UrlEncode((string)json_encode($payload));
        $msg = $base64Header . '.' . $base64Payload;

        if (function_exists('sodium_crypto_sign_detached')) {
            if (self::$keyPair === null) {
                self::$keyPair = sodium_crypto_sign_keypair();
            }
            $secretKey = sodium_crypto_sign_secretkey(self::$keyPair);
            $sig = sodium_crypto_sign_detached($msg, $secretKey);
            $base64Sig = self::base64UrlEncode($sig);
        } else {
            $base64Sig = self::base64UrlEncode(hash_hmac('sha256', $msg, 'test_secret_key_123', true));
        }

        return $base64Header . '.' . $base64Payload . '.' . $base64Sig;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
