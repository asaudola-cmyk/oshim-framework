<?php
declare(strict_types=1);

namespace Oshim\Security;

use Oshim\Security\Exceptions\SecurityException;

/**
 * Argon2id Password Hashing Engine.
 */
final class PasswordHasher
{
    public const MEMORY_COST = 65536; // 64 MB
    public const TIME_COST = 4;       // 4 Iterations
    public const THREADS = 1;         // 1 Thread

    public static function hash(string $password, array $options = []): string
    {
        $opts = array_merge([
            'memory_cost' => self::MEMORY_COST,
            'time_cost'   => self::TIME_COST,
            'threads'     => self::THREADS,
        ], $options);

        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        $hash = password_hash($password, $algo, $opts);

        if ($hash === false) {
            throw new SecurityException("Failed to generate password hash.");
        }

        return $hash;
    }

    public static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function needsRehash(string $hash, array $options = []): bool
    {
        $opts = array_merge([
            'memory_cost' => self::MEMORY_COST,
            'time_cost'   => self::TIME_COST,
            'threads'     => self::THREADS,
        ], $options);

        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        return password_needs_rehash($hash, $algo, $opts);
    }
}
