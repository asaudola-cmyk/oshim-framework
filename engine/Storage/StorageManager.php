<?php
declare(strict_types=1);

namespace Oshim\Storage;

use Oshim\Storage\Drivers\StorageDriverInterface;
use Oshim\Storage\Drivers\LocalStorageDriver;
use Oshim\Storage\Drivers\S3StorageDriver;
use InvalidArgumentException;

class StorageManager
{
    /** @var array<string, StorageDriverInterface> */
    private array $disks = [];
    private string $defaultDisk = 'local';

    public function __construct(string $defaultDisk = 'local')
    {
        $this->defaultDisk = $defaultDisk;
    }

    public function disk(?string $name = null): StorageDriverInterface
    {
        $name = $name ?? $this->defaultDisk;

        if (isset($this->disks[$name])) {
            return $this->disks[$name];
        }

        return $this->disks[$name] = match ($name) {
            'local', 'public' => new LocalStorageDriver(),
            's3' => new S3StorageDriver(),
            default => throw new InvalidArgumentException("Unsupported storage disk: {$name}"),
        };
    }

    public function put(string $path, string $contents): bool
    {
        return $this->disk()->put($path, $contents);
    }

    public function get(string $path): ?string
    {
        return $this->disk()->get($path);
    }

    public function exists(string $path): bool
    {
        return $this->disk()->exists($path);
    }

    public function delete(string $path): bool
    {
        return $this->disk()->delete($path);
    }

    public function url(string $path): string
    {
        return $this->disk()->url($path);
    }

    public function presignedUrl(string $path, int $expiresInSeconds = 3600): string
    {
        return $this->disk()->presignedUrl($path, $expiresInSeconds);
    }
}
