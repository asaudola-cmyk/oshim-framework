<?php
declare(strict_types=1);

namespace Oshim\Async;

class SharedMemoryCache
{
    private static array $inMemoryFallback = [];
    private static bool $hasShm;
    private static $shmHandle = null;

    public static function init(int $key = 0x1234, int $sizeBytes = 1048576): void
    {
        self::$hasShm = function_exists('shmop_open');
        if (self::$hasShm) {
            try {
                self::$shmHandle = @shmop_open($key, 'c', 0644, $sizeBytes);
            } catch (\Throwable) {
                self::$shmHandle = null;
            }
        }
    }

    public static function set(string $key, mixed $value, int $ttlSeconds = 3600): bool
    {
        $payload = [
            'val' => $value,
            'exp' => time() + $ttlSeconds,
        ];
        self::$inMemoryFallback[$key] = $payload;

        if (self::$shmHandle !== null && function_exists('shmop_write')) {
            try {
                $serialized = serialize(self::$inMemoryFallback);
                @shmop_write(self::$shmHandle, $serialized, 0);
            } catch (\Throwable) {
                // graceful in-memory
            }
        }

        return true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (isset(self::$inMemoryFallback[$key])) {
            $item = self::$inMemoryFallback[$key];
            if ($item['exp'] >= time()) {
                return $item['val'];
            }
            unset(self::$inMemoryFallback[$key]);
        }
        return $default;
    }

    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    public static function delete(string $key): bool
    {
        unset(self::$inMemoryFallback[$key]);
        return true;
    }

    public static function flush(): void
    {
        self::$inMemoryFallback = [];
    }
}
