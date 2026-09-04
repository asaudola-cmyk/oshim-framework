<?php
declare(strict_types=1);

namespace Oshim\Virtualization\Syscall;

/**
 * Interface contract for raw Linux kernel system calls.
 */
interface SyscallInterface
{
    /**
     * Disassociate parts of the process execution context (Namespaces).
     */
    public function unshare(int $flags): int;

    /**
     * Reassociate thread with a namespace.
     */
    public function setns(int $fd, int $nstype): int;

    /**
     * Mount a filesystem.
     */
    public function mount(?string $source, string $target, ?string $filesystemType, int $flags, mixed $data = null): int;

    /**
     * Unmount a filesystem.
     */
    public function umount2(string $target, int $flags): int;

    /**
     * Change the root mount and move previous root mount.
     */
    public function pivotRoot(string $newRoot, string $putOld): int;

    /**
     * Change root directory.
     */
    public function chroot(string $path): int;

    /**
     * Change working directory.
     */
    public function chdir(string $path): int;

    /**
     * Set system hostname.
     */
    public function setHostname(string $name): int;

    /**
     * Control device parameters.
     */
    public function ioctl(int $fd, int $request, mixed $arg = null): int;

    /**
     * Synchronize a filesystem containing the file referred to by fd.
     */
    public function syncfs(int $fd): int;

    /**
     * Open and possibly create a file.
     */
    public function open(string $pathname, int $flags, int $mode = 0): int;

    /**
     * Close a file descriptor.
     */
    public function close(int $fd): int;

    /**
     * Get process ID.
     */
    public function getPid(): int;

    /**
     * Get effective user ID.
     */
    public function getEuid(): int;

    /**
     * Get the last error code (errno).
     */
    public function getLastError(): int;

    /**
     * Get human-readable error description for an errno code.
     */
    public function getErrorString(int $errno): string;
}
