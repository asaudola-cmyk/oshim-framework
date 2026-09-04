<?php
declare(strict_types=1);

namespace Oshim\Virtualization\Syscall;

/**
 * Standard Linux Kernel constants for namespaces, syscalls, mounts, and ioctl operations.
 */
final class LinuxConstants
{
    // --- Linux Namespace Clone Flags ---
    public const CLONE_NEWNS     = 0x00020000; // Mount namespace (private filesystem mounts)
    public const CLONE_NEWCGROUP = 0x02000000; // Cgroup namespace (virtualized /proc/cgroups)
    public const CLONE_NEWUTS    = 0x04000000; // UTS namespace (isolated hostname & domain)
    public const CLONE_NEWIPC    = 0x08000000; // IPC namespace (isolated System V IPC & POSIX MQ)
    public const CLONE_NEWUSER   = 0x10000000; // User namespace (rootless UID/GID mapping)
    public const CLONE_NEWPID    = 0x20000000; // Process ID namespace (PID 1 container init)
    public const CLONE_NEWNET    = 0x40000000; // Network namespace (isolated routing & interfaces)

    // --- Syscall Numbers for x86_64 Architecture ---
    public const SYS_X86_64_READ       = 0;
    public const SYS_X86_64_WRITE      = 1;
    public const SYS_X86_64_OPEN       = 2;
    public const SYS_X86_64_CLOSE      = 3;
    public const SYS_X86_64_IOCTL      = 16;
    public const SYS_X86_64_CLONE      = 56;
    public const SYS_X86_64_PIVOT_ROOT = 155;
    public const SYS_X86_64_CHROOT     = 161;
    public const SYS_X86_64_MOUNT      = 165;
    public const SYS_X86_64_UMOUNT2    = 166;
    public const SYS_X86_64_UNSHARE    = 272;
    public const SYS_X86_64_SETNS      = 308;
    public const SYS_X86_64_SYNCFS     = 306;

    // --- Syscall Numbers for aarch64 (ARM64) Architecture ---
    public const SYS_AARCH64_IOCTL      = 29;
    public const SYS_AARCH64_UMOUNT2    = 39;
    public const SYS_AARCH64_MOUNT      = 40;
    public const SYS_AARCH64_UNSHARE    = 97;
    public const SYS_AARCH64_PIVOT_ROOT = 217;
    public const SYS_AARCH64_CLONE      = 220;
    public const SYS_AARCH64_SYNCFS     = 267;
    public const SYS_AARCH64_SETNS      = 268;

    // --- Linux Mount Flags ---
    public const MS_RDONLY   = 1;          // Read-only filesystem
    public const MS_NOSUID   = 2;          // Ignore suid and sgid bits
    public const MS_NODEV    = 4;          // Disallow access to device special files
    public const MS_NOEXEC   = 8;          // Disallow program execution
    public const MS_REMOUNT  = 32;         // Alter flags of existing mount
    public const MS_BIND     = 4096;       // Bind mount (0x1000)
    public const MS_REC      = 16384;      // Recursive mount (0x4000)
    public const MS_PRIVATE  = 1 << 18;    // 262144: Do not propagate mounts
    public const MS_SLAVE    = 1 << 19;    // 524288: Receive slave propagation
    public const MS_SHARED   = 1 << 20;    // 1048576: Propagate mount events

    // --- Linux Umount2 Flags ---
    public const MNT_FORCE       = 1;      // Force unmount even if busy
    public const MNT_DETACH      = 2;      // Lazy unmount (disconnect filesystem from tree)
    public const MNT_EXPIRE      = 4;      // Mark for expiration
    public const UMOUNT_NOFOLLOW = 8;      // Don't follow symlinks

    // --- TAP/TUN ioctl Requests ---
    public const TUNSETIFF = 0x400454ca;
    public const IFF_TUN   = 0x0001;
    public const IFF_TAP   = 0x0002;
    public const IFF_NO_PI = 0x1000;

    // --- Linux Bridge ioctl Requests ---
    public const SIOCBRADDBR = 0x89a0;     // Add bridge
    public const SIOCBRDELBR = 0x89a1;     // Delete bridge
    public const SIOCBRADDIF = 0x89a2;     // Add interface to bridge
    public const SIOCBRDELIF = 0x89a3;     // Delete interface from bridge

    /**
     * Get architecture-specific syscall number for pivot_root.
     */
    public static function getSyscallPivotRoot(): int
    {
        $arch = php_uname('m');
        return match ($arch) {
            'aarch64', 'arm64' => self::SYS_AARCH64_PIVOT_ROOT,
            default => self::SYS_X86_64_PIVOT_ROOT,
        };
    }

    /**
     * Build namespace bitmask from an array of namespace names.
     *
     * @param list<string> $namespaces
     */
    public static function buildNamespaceFlags(array $namespaces): int
    {
        $flagMap = [
            'mount'  => self::CLONE_NEWNS,
            'uts'    => self::CLONE_NEWUTS,
            'ipc'    => self::CLONE_NEWIPC,
            'user'   => self::CLONE_NEWUSER,
            'pid'    => self::CLONE_NEWPID,
            'net'    => self::CLONE_NEWNET,
            'cgroup' => self::CLONE_NEWCGROUP,
        ];

        $flags = 0;
        foreach ($namespaces as $ns) {
            $nsLower = strtolower(trim((string)$ns));
            if (isset($flagMap[$nsLower])) {
                $flags |= $flagMap[$nsLower];
            }
        }

        return $flags;
    }
}
