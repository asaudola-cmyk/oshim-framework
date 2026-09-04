<?php
declare(strict_types=1);

namespace Oshim\Cache\Drivers;

interface CacheDriverInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value, int $ttlSeconds = 0): bool;
    public function has(string $key): bool;
    public function delete(string $key): bool;
    public function clear(): bool;
    public function remember(string $key, int $ttlSeconds, callable $callback): mixed;
    public function increment(string $key, int $value = 1): int;
    public function decrement(string $key, int $value = 1): int;
}
