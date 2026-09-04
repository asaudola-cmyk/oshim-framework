<?php
declare(strict_types=1);

namespace Oshim\Cache\Drivers;

class FileCacheDriver implements CacheDriverInterface
{
    private string $cacheDir;

    public function __construct(?string $cacheDir = null)
    {
        $this->cacheDir = $cacheDir ?? (dirname(__DIR__, 3) . '/storage/framework/cache');
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }

    private function getFilePath(string $key): string
    {
        $hash = sha1($key);
        return $this->cacheDir . '/' . $hash . '.cache';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->getFilePath($key);
        if (!is_file($file)) {
            return $default;
        }

        $content = @file_get_contents($file);
        if ($content === false) {
            return $default;
        }

        $payload = @unserialize($content);
        if (!is_array($payload) || !array_key_exists('expires_at', $payload)) {
            return $default;
        }

        if ($payload['expires_at'] !== null && time() > $payload['expires_at']) {
            @unlink($file);
            return $default;
        }

        return $payload['value'] ?? $default;
    }

    public function set(string $key, mixed $value, int $ttlSeconds = 0): bool
    {
        $file = $this->getFilePath($key);
        $expiresAt = $ttlSeconds > 0 ? (time() + $ttlSeconds) : null;

        $payload = serialize([
            'key' => $key,
            'value' => $value,
            'expires_at' => $expiresAt,
        ]);

        return (bool)@file_put_contents($file, $payload, LOCK_EX);
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);
        if (is_file($file)) {
            return @unlink($file);
        }
        return false;
    }

    public function clear(): bool
    {
        $files = glob($this->cacheDir . '/*.cache') ?: [];
        foreach ($files as $file) {
            @unlink($file);
        }
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
