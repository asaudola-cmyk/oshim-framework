<?php
declare(strict_types=1);

namespace Oshim\Kernel;

use Oshim\Kernel\Contracts\KernelDriverInterface;
use Oshim\Kernel\Drivers\LinuxKernelDriver;
use Oshim\Kernel\Drivers\DarwinKernelDriver;
use Oshim\Kernel\Drivers\BsdKernelDriver;
use Oshim\Kernel\Drivers\WindowsKernelDriver;
use Oshim\Kernel\Drivers\GenericPortableDriver;

class UniversalKernel
{
    private static ?KernelDriverInterface $activeDriver = null;

    public static function getOsFamily(): string
    {
        return PHP_OS_FAMILY;
    }

    public static function getDriver(): KernelDriverInterface
    {
        if (self::$activeDriver !== null) {
            return self::$activeDriver;
        }

        $family = self::getOsFamily();

        if ($family === 'Linux') {
            $driver = new LinuxKernelDriver();
            if ($driver->isAvailable()) {
                return self::$activeDriver = $driver;
            }
        } elseif ($family === 'Darwin') {
            $driver = new DarwinKernelDriver();
            if ($driver->isAvailable()) {
                return self::$activeDriver = $driver;
            }
        } elseif ($family === 'BSD') {
            $driver = new BsdKernelDriver();
            if ($driver->isAvailable()) {
                return self::$activeDriver = $driver;
            }
        } elseif ($family === 'Windows') {
            $driver = new WindowsKernelDriver();
            if ($driver->isAvailable()) {
                return self::$activeDriver = $driver;
            }
        }

        return self::$activeDriver = new GenericPortableDriver();
    }

    public static function setDriver(KernelDriverInterface $driver): void
    {
        self::$activeDriver = $driver;
    }

    public static function resetDriver(): void
    {
        self::$activeDriver = null;
    }

    public static function info(): array
    {
        $driver = self::getDriver();
        return [
            'os_family' => self::getOsFamily(),
            'php_uname' => php_uname(),
            'driver' => $driver->getDriverName(),
            'supported_os' => $driver->getSupportedOs(),
            'ffi_enabled' => extension_loaded('ffi'),
            'fibers_enabled' => class_exists(\Fiber::class),
            'metrics' => $driver->getSystemMetrics(),
        ];
    }
}
