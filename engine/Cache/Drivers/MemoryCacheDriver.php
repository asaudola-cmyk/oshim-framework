<?php
declare(strict_types=1);

namespace Oshim\Cache\Drivers;

class MemoryCacheDriver implements CacheDriverInterface
{
    /** @var array<string, array{value: mixed, expires_at: int|null}> */
    private array $storage = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!isset($this->storage[$key])) {
            return $default;
        }

        $item = $this->storage[$key];
        if ($item['expires_at'] !== null && time() > $item['expires_at']) {
            unset($this->storage[$key]);
            return $default;
        }

        return $item['value'];
    }

    public function set(string $key, mixed $value, int $ttlSeconds = 0): bool
    {
        $expiresAt = $ttlSeconds > 0 ? (time() + $ttlSeconds) : null;
        $this->storage[$key] = [
            'value' => $value,
            'expires_at' => $expiresAt,
        ];
        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function delete(string $key): bool
    {
        if (isset($this->storage[$key])) {
            unset($this->storage[$key]);
            return true;
        }
        return false;
    }

    public function clear(): bool
    {
        $this->storage = [];
        return true;
    }

    public function remember(string $key, int $ttlSeconds, callable $callback): mixed
    {
        $val = $this->get($key);
        if ($val !== null) {
            return $val;
        }

        $val = $callback();
        $this->set($key, $val, $ttlSeconds);
        return $val;
    }

    public function increment(string $key, int $value = 1): int
    {
        $curr = (int)($this->get($key, 0));
        $new = $curr + $value;
        $this->set($key, $new);
        return $new;
    }

    public function decrement(string $key, int $value = 1): int
    {
        return $this->increment($key, -$value);
    }
}
