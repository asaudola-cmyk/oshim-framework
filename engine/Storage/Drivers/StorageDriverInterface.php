<?php
declare(strict_types=1);

namespace Oshim\Storage\Drivers;

interface StorageDriverInterface
{
    public function put(string $path, string $contents): bool;
    public function get(string $path): ?string;
    public function exists(string $path): bool;
    public function delete(string $path): bool;
    public function size(string $path): int;
    public function url(string $path): string;
    public function presignedUrl(string $path, int $expiresInSeconds = 3600): string;
}
