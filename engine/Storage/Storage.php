<?php
declare(strict_types=1);

namespace Oshim\Storage;

use Oshim\Storage\Drivers\StorageDriverInterface;

class Storage
{
    private static ?StorageManager $manager = null;

    public static function getManager(): StorageManager
    {
        if (self::$manager === null) {
            self::$manager = new StorageManager();
        }
        return self::$manager;
    }

    public static function disk(?string $name = null): StorageDriverInterface
    {
        return self::getManager()->disk($name);
    }

    public static function put(string $path, string $contents): bool
    {
        return self::getManager()->put($path, $contents);
    }

    public static function get(string $path): ?string
    {
        return self::getManager()->get($path);
    }

    public static function exists(string $path): bool
    {
        return self::getManager()->exists($path);
    }

    public static function delete(string $path): bool
    {
        return self::getManager()->delete($path);
    }

    public static function url(string $path): string
    {
        return self::getManager()->url($path);
    }

    public static function presignedUrl(string $path, int $expiresInSeconds = 3600): string
    {
        return self::getManager()->presignedUrl($path, $expiresInSeconds);
    }
}
