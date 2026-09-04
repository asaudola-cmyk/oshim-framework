<?php
declare(strict_types=1);

namespace Oshim\Storage\Drivers;

class LocalStorageDriver implements StorageDriverInterface
{
    private string $root;
    private string $baseUrl;

    public function __construct(?string $root = null, string $baseUrl = '/storage')
    {
        $this->root = $root ?? (dirname(__DIR__, 3) . '/storage/app/public');
        $this->baseUrl = rtrim($baseUrl, '/');
        if (!is_dir($this->root)) {
            @mkdir($this->root, 0755, true);
        }
    }

    public function put(string $path, string $contents): bool
    {
        $fullPath = $this->getFullPath($path);
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return (bool)file_put_contents($fullPath, $contents, LOCK_EX);
    }

    public function get(string $path): ?string
    {
        $fullPath = $this->getFullPath($path);
        if (!is_file($fullPath)) {
            return null;
        }
        $c = file_get_contents($fullPath);
        return $c !== false ? $c : null;
    }

    public function exists(string $path): bool
    {
        return is_file($this->getFullPath($path));
    }

    public function delete(string $path): bool
    {
        $fullPath = $this->getFullPath($path);
        if (is_file($fullPath)) {
            return @unlink($fullPath);
        }
        return false;
    }

    public function size(string $path): int
    {
        $fullPath = $this->getFullPath($path);
        return is_file($fullPath) ? (int)filesize($fullPath) : 0;
    }

    public function url(string $path): string
    {
        return $this->baseUrl . '/' . ltrim($path, '/');
    }

    public function presignedUrl(string $path, int $expiresInSeconds = 3600): string
    {
        $expires = time() + $expiresInSeconds;
        $signature = hash_hmac('sha256', "{$path}:{$expires}", 'oshim_local_storage_secret');
        return $this->url($path) . "?expires={$expires}&sig={$signature}";
    }

    private function getFullPath(string $path): string
    {
        return $this->root . '/' . ltrim($path, '/');
    }
}
