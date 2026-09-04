<?php
declare(strict_types=1);

namespace Oshim\Cache;

use Oshim\Cache\Drivers\CacheDriverInterface;
use Oshim\Cache\Drivers\MemoryCacheDriver;
use Oshim\Cache\Drivers\FileCacheDriver;
use InvalidArgumentException;

class CacheManager
{
    /** @var array<string, CacheDriverInterface> */
    private array $drivers = [];
    private string $defaultDriver = 'file';

    public function __construct(string $defaultDriver = 'file')
    {
        $this->defaultDriver = $defaultDriver;
    }

    public function driver(?string $name = null): CacheDriverInterface
    {
        $name = $name ?? $this->defaultDriver;

        if (isset($this->drivers[$name])) {
            return $this->drivers[$name];
        }

                return $this->drivers[$name] = match ($name) {
            'memory', 'array' => new MemoryCacheDriver(),
            'file' => new FileCacheDriver(),
            'apcu' => new \Oshim\Cache\Drivers\ApcuCacheDriver(),
            default => throw new InvalidArgumentException("Unsupported cache driver: {$name}"),
        };
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->driver()->get($key, $default);
    }

    public function set(string $key, mixed $value, int $ttlSeconds = 0): bool
    {
        return $this->driver()->set($key, $value, $ttlSeconds);
    }

    public function has(string $key): bool
    {
        return $this->driver()->has($key);
    }

    public function delete(string $key): bool
    {
        return $this->driver()->delete($key);
    }

    public function clear(): bool
    {
        return $this->driver()->clear();
    }

    public function remember(string $key, int $ttlSeconds, callable $callback): mixed
    {
        return $this->driver()->remember($key, $ttlSeconds, $callback);
    }

    public function increment(string $key, int $value = 1): int
    {
        return $this->driver()->increment($key, $value);
    }

    public function decrement(string $key, int $value = 1): int
    {
        return $this->driver()->decrement($key, $value);
    }
}
