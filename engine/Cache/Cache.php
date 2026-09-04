<?php
declare(strict_types=1);

namespace Oshim\Cache;

class Cache
{
    private static ?CacheManager $manager = null;

    public static function getManager(): CacheManager
    {
        if (self::$manager === null) {
            self::$manager = new CacheManager();
        }
        return self::$manager;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::getManager()->get($key, $default);
    }

    public static function set(string $key, mixed $value, int $ttlSeconds = 0): bool
    {
        return self::getManager()->set($key, $value, $ttlSeconds);
    }

    public static function has(string $key): bool
    {
        return self::getManager()->has($key);
    }

    public static function delete(string $key): bool
    {
        return self::getManager()->delete($key);
    }

    public static function clear(): bool
    {
        return self::getManager()->clear();
    }

    public static function remember(string $key, int $ttlSeconds, callable $callback): mixed
    {
        return self::getManager()->remember($key, $ttlSeconds, $callback);
    }

    public static function increment(string $key, int $value = 1): int
    {
        return self::getManager()->increment($key, $value);
    }

    public static function decrement(string $key, int $value = 1): int
    {
        return self::getManager()->decrement($key, $value);
    }
}
