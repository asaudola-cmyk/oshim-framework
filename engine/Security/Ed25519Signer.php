<?php
declare(strict_types=1);

namespace Oshim\Security;

use Oshim\Security\Exceptions\SecurityException;

/**
 * Libsodium Ed25519 Asymmetric Signature & Keypair Manager.
 */
final class Ed25519Signer
{
    /**
     * Generate a new Ed25519 cryptographic keypair.
     *
     * @return array{publicKey: string, secretKey: string, publicKeyRaw: string, secretKeyRaw: string}
     */
    public static function generateKeypair(): array
    {
        if (!function_exists('sodium_crypto_sign_keypair')) {
            throw new SecurityException("Sodium PHP extension is required for Ed25519 signatures.");
        }

        $keypair = sodium_crypto_sign_keypair();
        $secretKeyRaw = sodium_crypto_sign_secretkey($keypair);
        $publicKeyRaw = sodium_crypto_sign_publickey($keypair);

        return [
            'publicKey'    => bin2hex($publicKeyRaw),
            'secretKey'    => bin2hex($secretKeyRaw),
            'publicKeyRaw' => $publicKeyRaw,
            'secretKeyRaw' => $secretKeyRaw,
        ];
    }

    /**
     * Produces a 64-byte detached signature formatted as Base64Url.
     */
    public static function sign(string $message, string $secretKey): string
    {
        $rawKey = self::normalizeKey($secretKey, SODIUM_CRYPTO_SIGN_SECRETKEYBYTES);
        $signature = sodium_crypto_sign_detached($message, $rawKey);

        return Cipher::base64UrlEncode($signature);
    }

    /**
     * Verifies detached signature against public key.
     */
    public static function verify(string $signature, string $message, string $publicKey): bool
    {
        try {
            $rawSig = Cipher::base64UrlDecode($signature);
            if (strlen($rawSig) !== SODIUM_CRYPTO_SIGN_BYTES) {
                // Also support hex signature
                if (strlen($signature) === SODIUM_CRYPTO_SIGN_BYTES * 2 && ctype_xdigit($signature)) {
                    $rawSig = (string)hex2bin($signature);
                } else {
                    return false;
                }
            }

            $rawKey = self::normalizeKey($publicKey, SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES);
            return sodium_crypto_sign_verify_detached($rawSig, $message, $rawKey);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Derives Ed25519 public key from secret key.
     */
    public static function extractPublicKey(string $secretKey): string
    {
        $rawSecret = self::normalizeKey($secretKey, SODIUM_CRYPTO_SIGN_SECRETKEYBYTES);
        $rawPublic = sodium_crypto_sign_publickey_from_secretkey($rawSecret);
        return bin2hex($rawPublic);
    }

    protected static function normalizeKey(string $key, int $expectedLength): string
    {
        if (strlen($key) === $expectedLength * 2 && ctype_xdigit($key)) {
            return (string)hex2bin($key);
        }

        if (strlen($key) === $expectedLength) {
            return $key;
        }

        $decoded = Cipher::base64UrlDecode($key);
        if (strlen($decoded) === $expectedLength) {
            return $decoded;
        }

        throw new SecurityException("Invalid Ed25519 key length. Expected {$expectedLength} bytes.");
    }
}
