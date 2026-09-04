<?php
declare(strict_types=1);

namespace Oshim\Virtualization;

use Oshim\Virtualization\Driver\MockVirtualizationDriver;
use Oshim\Virtualization\Driver\NativeLinuxDriver;
use Oshim\Virtualization\Driver\VirtualizationDriverInterface;
use RuntimeException;

/**
 * Capability detector and driver factory for the virtualization subsystem.
 */
class VirtualizationEnvironment
{
    /**
     * Determine if the host platform meets all prerequisites for bare-metal Linux virtualization:
     * - Linux OS
     * - Root user UID (posix_geteuid() === 0)
     * - PHP FFI extension enabled
     * - Unified Cgroups v2 filesystem accessible
     * - Virtual TUN/TAP character device present
     */
    public static function isBareMetalSupported(): bool
    {
        return PHP_OS_FAMILY === 'Linux'
            && function_exists('posix_geteuid')
            && posix_geteuid() === 0
            && class_exists('FFI')
            && file_exists('/sys/fs/cgroup')
            && file_exists('/dev/net/tun');
    }

    /**
     * Resolve and instantiate the appropriate virtualization driver based on capability detection or preference.
     */
    public static function resolveDriver(string $preference = 'auto'): VirtualizationDriverInterface
    {
        $preference = strtolower(trim($preference));

        if ($preference === 'mock') {
            return new MockVirtualizationDriver();
        }

        if ($preference === 'native') {
            if (!self::isBareMetalSupported()) {
                throw new RuntimeException("NativeLinuxDriver requested but host does not satisfy bare-metal requirements (root UID, FFI, /sys/fs/cgroup, /dev/net/tun).");
            }
            return new NativeLinuxDriver();
        }

        // 'auto' mode: check environment variable override
        if (getenv('OSHIM_VIRT_DRIVER') === 'mock') {
            return new MockVirtualizationDriver();
        }

        if (self::isBareMetalSupported()) {
            return new NativeLinuxDriver();
        }

        return new MockVirtualizationDriver();
    }

    /**
     * Alias for resolveDriver.
     */
    public static function createDriver(string $preference = 'auto'): VirtualizationDriverInterface
    {
        return self::resolveDriver($preference);
    }
}
