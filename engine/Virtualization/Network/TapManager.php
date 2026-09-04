<?php
declare(strict_types=1);

namespace Oshim\Virtualization\Network;

use FFI;
use Oshim\Virtualization\Exceptions\NetworkException;
use Oshim\Virtualization\Syscall\LinuxConstants;
use Oshim\Virtualization\Syscall\LinuxSyscall;
use Throwable;

/**
 * TAP/TUN virtual network interface creator via /dev/net/tun ioctls.
 */
class TapManager
{
    /**
     * Create a new persistent or transient TAP device on the host.
     *
     * @return int File descriptor of the opened TAP device
     */
    public static function createTapDevice(string $devName): int
    {
        try {
            $ffi = LinuxSyscall::getFFI();
        } catch (Throwable $e) {
            throw new NetworkException("Failed to access libc FFI for TAP creation: " . $e->getMessage(), 0, $e);
        }

        $fd = $ffi->open('/dev/net/tun', 2 /* O_RDWR */);
        if ($fd < 0) {
            $errnoPtr = $ffi->__errno_location();
            $errno = $errnoPtr[0];
            throw new NetworkException("Failed to open /dev/net/tun: " . FFI::string($ffi->strerror($errno)) . " (errno={$errno})");
        }

        $ifr = $ffi->new('struct ifreq');
        FFI::memset($ifr, 0, FFI::sizeof($ifr));

        $nameLen = min(strlen($devName), 15);
        for ($i = 0; $i < $nameLen; $i++) {
            $ifr->ifr_name[$i] = $devName[$i];
        }
        $ifr->ifr_flags = LinuxConstants::IFF_TAP | LinuxConstants::IFF_NO_PI;

        $res = $ffi->ioctl($fd, LinuxConstants::TUNSETIFF, FFI::addr($ifr));
        if ($res < 0) {
            $errnoPtr = $ffi->__errno_location();
            $errno = $errnoPtr[0];
            $ffi->close($fd);
            throw new NetworkException("TUNSETIFF ioctl failed for interface '{$devName}': " . FFI::string($ffi->strerror($errno)) . " (errno={$errno})");
        }

        return (int)$fd;
    }

    /**
     * Close a TAP device file descriptor.
     */
    public static function closeTapDevice(int $fd): void
    {
        if ($fd >= 0 && class_exists('FFI')) {
            try {
                LinuxSyscall::getFFI()->close($fd);
            } catch (Throwable) {
                // Ignore close errors on cleanup
            }
        }
    }
}
