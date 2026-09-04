<?php
declare(strict_types=1);

namespace Oshim\Security\Totp;

use InvalidArgumentException;

/**
 * RFC 6238 Time-based One-Time Password (TOTP) 2FA Engine with Base32 support.
 */
class TotpEngine
{
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a cryptographically secure Base32 secret.
     */
    public static function generateSecret(int $byteLength = 20): string
    {
        $bytes = random_bytes($byteLength);
        return self::base32Encode($bytes);
    }

    /**
     * Generate TOTP code for a given timestamp.
     *
     * @param string $secret Base32 encoded secret
     * @param int|null $timestamp Unix epoch seconds (defaults to current time)
     * @param int $digits Length of code (typically 6)
     * @param int $period Time step in seconds (RFC 6238 default = 30)
     * @param string $algo Hash algorithm ('sha1', 'sha256', 'sha512')
     */
    public static function generateCode(
        string $secret,
        ?int $timestamp = null,
        int $digits = 6,
        int $period = 30,
        string $algo = 'sha1'
    ): string {
        $timestamp = $timestamp ?? time();
        $timeCounter = (int)floor($timestamp / $period);

        // 8-byte big-endian binary counter
        $packedTime = pack('J', $timeCounter);

        $secretBytes = self::base32Decode($secret);
        $hash = hash_hmac($algo, $packedTime, $secretBytes, true);

        // Dynamic truncation
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $binary = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        $otp = $binary % (10 ** $digits);
        return str_pad((string)$otp, $digits, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a user-provided code within an acceptable drift window (+/- $window time steps).
     */
    public static function verify(
        string $secret,
        string $code,
        int $window = 1,
        ?int $timestamp = null,
        int $digits = 6,
        int $period = 30,
        string $algo = 'sha1'
    ): bool {
        $timestamp = $timestamp ?? time();
        $code = trim($code);

        for ($i = -$window; $i <= $window; $i++) {
            $t = $timestamp + ($i * $period);
            $expected = self::generateCode($secret, $t, $digits, $period, $algo);
            if (hash_equals($expected, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate Google Authenticator compatible provisioning URI.
     */
    public static function getProvisioningUri(
        string $secret,
        string $accountName,
        string $issuer = 'OSHIM Cloud',
        int $digits = 6,
        int $period = 30
    ): string {
        $encodedIssuer = rawurlencode($issuer);
        $encodedAccount = rawurlencode($accountName);

        return "otpauth://totp/{$encodedIssuer}:{$encodedAccount}?secret={$secret}&issuer={$encodedIssuer}&period={$period}&digits={$digits}";
    }

    public static function base32Encode(string $data): string
    {
        if ($data === '') {
            return '';
        }

        $binary = '';
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $binary .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
        }

        $paddedLen = ceil(strlen($binary) / 5) * 5;
        $binary = str_pad($binary, (int)$paddedLen, '0', STR_PAD_RIGHT);

        $encoded = '';
        $chunks = str_split($binary, 5);
        foreach ($chunks as $chunk) {
            $encoded .= self::BASE32_CHARS[bindec($chunk)];
        }

        return $encoded;
    }

    public static function base32Decode(string $base32): string
    {
        $base32 = strtoupper(rtrim($base32, '='));
        if ($base32 === '') {
            return '';
        }

        $binary = '';
        $len = strlen($base32);
        for ($i = 0; $i < $len; $i++) {
            $pos = strpos(self::BASE32_CHARS, $base32[$i]);
            if ($pos === false) {
                throw new InvalidArgumentException("Invalid Base32 character: {$base32[$i]}");
            }
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $data = '';
        $chunks = str_split($binary, 8);
        foreach ($chunks as $chunk) {
            if (strlen($chunk) === 8) {
                $data .= chr(bindec($chunk));
            }
        }

        return $data;
    }
}
