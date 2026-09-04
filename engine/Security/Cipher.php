<?php
declare(strict_types=1);

namespace Oshim\Security;

use Oshim\Security\Exceptions\DecryptionException;
use Oshim\Security\Exceptions\SecurityException;

/**
 * AES-256-GCM Authenticated Encryption with Associated Data (AEAD).
 */
final class Cipher
{
    public const CIPHER_ALGO = 'aes-256-gcm';
    public const IV_LENGTH = 12;  // 96-bit nonce
    public const TAG_LENGTH = 16; // 128-bit authentication tag
    public const KEY_LENGTH = 32; // 256-bit key

    /**
     * Generate a cryptographically secure 32-byte (256-bit) encryption key as hex string.
     */
    public static function generateKey(): string
    {
        return bin2hex(random_bytes(self::KEY_LENGTH));
    }

    /**
     * Normalize key to 32 bytes.
     */
    public static function normalizeKey(string $key): string
    {
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if ($decoded !== false && strlen($decoded) === self::KEY_LENGTH) {
                return $decoded;
            }
        }

        if (strlen($key) === 64 && ctype_xdigit($key)) {
            return (string)hex2bin($key);
        }

        if (strlen($key) === self::KEY_LENGTH) {
            return $key;
        }

        // Derive 32-byte key via SHA-256 if arbitrary string is passed
        return hash('sha256', $key, true);
    }

    /**
     * Encrypt plaintext using AES-256-GCM AEAD.
     *
     * @param string $plaintext Data to encrypt.
     * @param string $key Master encryption key.
     * @param string $aad Additional Authenticated Data bound to the ciphertext.
     * @return string Base64Url-encoded payload (IV . Tag . Ciphertext).
     */
    public static function encrypt(string $plaintext, string $key, string $aad = ''): string
    {
        $binaryKey = self::normalizeKey($key);
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER_ALGO,
            $binaryKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad,
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new SecurityException('Encryption failed with AES-256-GCM.');
        }

        $payload = $iv . $tag . $ciphertext;
        return self::base64UrlEncode($payload);
    }

    /**
     * Decrypt AES-256-GCM payload.
     *
     * @param string $payload Base64Url-encoded string (IV . Tag . Ciphertext).
     * @param string $key Master encryption key.
     * @param string $aad Additional Authenticated Data.
     * @throws DecryptionException On invalid format, bad key, or tampered tag.
     * @return string Decrypted plaintext.
     */
    public static function decrypt(string $payload, string $key, string $aad = ''): string
    {
        $binaryKey = self::normalizeKey($key);
        $binary = self::base64UrlDecode($payload);

        $minLength = self::IV_LENGTH + self::TAG_LENGTH;
        if (strlen($binary) < $minLength) {
            throw new DecryptionException('Invalid encrypted payload structure.');
        }

        $iv = substr($binary, 0, self::IV_LENGTH);
        $tag = substr($binary, self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($binary, $minLength);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER_ALGO,
            $binaryKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad
        );

        if ($plaintext === false) {
            throw new DecryptionException('Decryption failed: authentication tag mismatch or corrupted ciphertext.');
        }

        return $plaintext;
    }

    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder !== 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return (string)base64_decode(strtr($data, '-_', '+/'));
    }
}
