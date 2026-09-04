<?php
declare(strict_types=1);

namespace Oshim\Auth\Password;

/**
 * High-Security Password Hashing Engine using Argon2id / Bcrypt with constant-time verification.
 */
class PasswordHasher
{
    /**
     * Hash plain password using Argon2id if available, fallback to Bcrypt.
     */
    public static function make(string $value, array $options = []): string
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        $hash = password_hash($value, $algo, $options);
        
        if ($hash === false) {
            throw new \RuntimeException('Failed to hash password.');
        }

        return $hash;
    }

    /**
     * Verify plain password against hashed value.
     */
    public static function check(string $value, string $hashedValue): bool
    {
        if (strlen($hashedValue) === 0) {
            return false;
        }

        return password_verify($value, $hashedValue);
    }

    /**
     * Check if password hash needs rehash with updated cost/algorithm.
     */
    public static function needsRehash(string $hashedValue, array $options = []): bool
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        return password_needs_rehash($hashedValue, $algo, $options);
    }
}
