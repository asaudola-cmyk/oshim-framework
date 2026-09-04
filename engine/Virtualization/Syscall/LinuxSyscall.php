<?php
declare(strict_types=1);

namespace Oshim\Virtualization\Syscall;

use FFI;
use Oshim\Virtualization\Exceptions\SyscallException;
use Throwable;

/**
 * Direct Linux kernel syscall engine leveraging libc.so.6 bindings via PHP FFI.
 */
class LinuxSyscall implements SyscallInterface
{
    private static ?FFI $ffi = null;

    /**
     * Get or initialize the libc FFI instance.
     */
    public static function getFFI(): FFI
    {
        if (self::$ffi === null) {
            $cdef = "
                long syscall(long number, ...);
                int unshare(int flags);
                int setns(int fd, int nstype);
                int mount(const char *source, const char *target, const char *filesystemtype, unsigned long mountflags, const void *data);
                int umount2(const char *target, int flags);
                int chroot(const char *path);
                int chdir(const char *path);
                int sethostname(const char *name, size_t len);
                int ioctl(int fd, unsigned long request, ...);
                int open(const char *pathname, int flags, ...);
                int close(int fd);
                int syncfs(int fd);
                int getpid(void);
                int geteuid(void);
                int *__errno_location(void);
                char *strerror(int errnum);

                struct ifreq {
                    char ifr_name[16];
                    short ifr_flags;
                    char ifr_padding[22];
                };
            ";

            try {
                self::$ffi = FFI::cdef($cdef, 'libc.so.6');
            } catch (Throwable $e) {
                throw new SyscallException("Failed to initialize libc FFI: " . $e->getMessage(), 'ffi_init', 0, [], $e);
            }
        }

        return self::$ffi;
    }

    /**
     * Check if FFI and libc are available on this system.
     */
    public static function isAvailable(): bool
    {
        if (!class_exists('FFI') || PHP_OS_FAMILY !== 'Linux') {
            return false;
        }

        try {
            self::getFFI();
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Reset FFI instance (useful for testing).
     */
    public static function reset(): void
    {
        self::$ffi = null;
    }

    public function getLastError(): int
    {
        $errnoPtr = self::getFFI()->__errno_location();
        return $errnoPtr[0];
    }

    public function getErrorString(int $errno): string
    {
        $str = self::getFFI()->strerror($errno);
        return $str !== null ? FFI::string($str) : "Unknown error ({$errno})";
    }

    /**
     * Verify syscall result and throw a rich SyscallException on failure (< 0).
     *
     * @param int|int-mask-of<int> $result
     * @param array<string, mixed> $context
     */
    public function checkResult(int $result, string $syscall, array $context = []): void
    {
        if ($result < 0) {
            $errno = $this->getLastError();
            $errorStr = $this->getErrorString($errno);
            $diagnostic = self::resolveDiagnosticMessage($errno, $syscall);

            throw new SyscallException(
                message: "Syscall '{$syscall}' failed: {$errorStr} (errno={$errno}). {$diagnostic}",
                syscall: $syscall,
                errno: $errno,
                context: $context
            );
        }
    }

    public static function resolveDiagnosticMessage(int $errno, string $syscall): string
    {
        return match ($errno) {
            1  => "Operation not permitted (EPERM). Requires root UID (posix_geteuid() === 0) or CAP_SYS_ADMIN/CAP_NET_ADMIN capabilities.",
            2  => "No such file or directory (ENOENT). The specified path or mount target does not exist.",
            3  => "No such process (ESRCH). Target process PID is not running or has already exited.",
            12 => "Cannot allocate memory (ENOMEM). Insufficient kernel memory for namespace or cgroup allocation.",
            13 => "Permission denied (EACCES). Filesystem permissions prevent access.",
            16 => "Device or resource busy (EBUSY). Mountpoint or directory is currently in use by active processes.",
            22 => "Invalid argument (EINVAL). Unsupported mount flags, invalid clone mask, or invalid target for pivot_root.",
            24 => "Too many open files (EMFILE). File descriptor limit reached.",
            28 => "No space left on device (ENOSPC). Storage partition is full.",
            38 => "Function not implemented (ENOSYS). Syscall is not supported by current kernel architecture.",
            default => "Refer to Linux man pages for {$syscall}(2) and errno {$errno}.",
        };
    }

    public function unshare(int $flags): int
    {
        $res = self::getFFI()->unshare($flags);
        return (int)$res;
    }

    public function setns(int $fd, int $nstype): int
    {
        $res = self::getFFI()->setns($fd, $nstype);
        return (int)$res;
    }

    public function mount(?string $source, string $target, ?string $filesystemType, int $flags, mixed $data = null): int
    {
        $ffi = self::getFFI();
        $srcC = $source !== null ? $source : null;
        $fsC = $filesystemType !== null ? $filesystemType : null;
        $dataC = is_string($data) ? $data : null;

        $res = $ffi->mount($srcC, $target, $fsC, $flags, $dataC);
        return (int)$res;
    }

    public function umount2(string $target, int $flags): int
    {
        $res = self::getFFI()->umount2($target, $flags);
        return (int)$res;
    }

    public function pivotRoot(string $newRoot, string $putOld): int
    {
        $syscallNum = LinuxConstants::getSyscallPivotRoot();
        $res = self::getFFI()->syscall($syscallNum, $newRoot, $putOld);
        return (int)$res;
    }

    public function chroot(string $path): int
    {
        $res = self::getFFI()->chroot($path);
        return (int)$res;
    }

    public function chdir(string $path): int
    {
        $res = self::getFFI()->chdir($path);
        return (int)$res;
    }

    public function setHostname(string $name): int
    {
        $res = self::getFFI()->sethostname($name, strlen($name));
        return (int)$res;
    }

    public function ioctl(int $fd, int $request, mixed $arg = null): int
    {
        $ffi = self::getFFI();
        if ($arg === null) {
            $res = $ffi->ioctl($fd, $request);
        } else {
            $res = $ffi->ioctl($fd, $request, $arg);
        }
        return (int)$res;
    }

    public function syncfs(int $fd): int
    {
        $res = self::getFFI()->syncfs($fd);
        return (int)$res;
    }

    public function open(string $pathname, int $flags, int $mode = 0): int
    {
        $ffi = self::getFFI();
        $res = $ffi->open($pathname, $flags, $mode);
        return (int)$res;
    }

    public function close(int $fd): int
    {
        $res = self::getFFI()->close($fd);
        return (int)$res;
    }

    public function getPid(): int
    {
        return (int)self::getFFI()->getpid();
    }

    public function getEuid(): int
    {
        return (int)self::getFFI()->geteuid();
    }
}
