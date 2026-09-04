<?php
declare(strict_types=1);

namespace Oshim\Virtualization\Syscall;

/**
 * Deterministic Syscall simulator for testing and rootless execution.
 */
class MockSyscall implements SyscallInterface
{
    private int $lastError = 0;
    /** @var array<int, string> */
    private array $customErrorStrings = [];
    /** @var array<string, int> */
    private array $forcedResults = [];
    /** @var list<array{syscall: string, args: array<mixed>, time: float}> */
    private array $calls = [];
    private string $hostname = 'localhost';
    private string $cwd = '/';
    private string $root = '/';
    /** @var array<string, array{source: ?string, type: ?string, flags: int, data: mixed}> */
    private array $mounts = [];

    public function reset(): void
    {
        $this->lastError = 0;
        $this->customErrorStrings = [];
        $this->forcedResults = [];
        $this->calls = [];
        $this->hostname = 'localhost';
        $this->cwd = '/';
        $this->root = '/';
        $this->mounts = [];
    }

    public function setLastError(int $errno): void
    {
        $this->lastError = $errno;
    }

    public function forceResult(string $syscall, int $result, int $errno = 0): void
    {
        $this->forcedResults[$syscall] = $result;
        if ($errno > 0) {
            $this->lastError = $errno;
        }
    }

    /**
     * @return list<array{syscall: string, args: array<mixed>, time: float}>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    private function record(string $syscall, array $args): int
    {
        $this->calls[] = [
            'syscall' => $syscall,
            'args'    => $args,
            'time'    => microtime(true),
        ];

        if (isset($this->forcedResults[$syscall])) {
            return $this->forcedResults[$syscall];
        }

        return 0;
    }

    public function unshare(int $flags): int
    {
        return $this->record('unshare', ['flags' => $flags]);
    }

    public function setns(int $fd, int $nstype): int
    {
        return $this->record('setns', ['fd' => $fd, 'nstype' => $nstype]);
    }

    public function mount(?string $source, string $target, ?string $filesystemType, int $flags, mixed $data = null): int
    {
        $res = $this->record('mount', [
            'source'         => $source,
            'target'         => $target,
            'filesystemType' => $filesystemType,
            'flags'          => $flags,
            'data'           => $data,
        ]);

        if ($res === 0) {
            $this->mounts[$target] = [
                'source' => $source,
                'type'   => $filesystemType,
                'flags'  => $flags,
                'data'   => $data,
            ];
        }

        return $res;
    }

    public function umount2(string $target, int $flags): int
    {
        $res = $this->record('umount2', ['target' => $target, 'flags' => $flags]);
        if ($res === 0) {
            unset($this->mounts[$target]);
        }
        return $res;
    }

    public function pivotRoot(string $newRoot, string $putOld): int
    {
        if (!str_starts_with($newRoot, '/') || !str_starts_with($putOld, '/')) {
            $this->lastError = 22; // EINVAL
            return -1;
        }

        $res = $this->record('pivot_root', ['new_root' => $newRoot, 'put_old' => $putOld]);
        if ($res === 0) {
            $this->root = $newRoot;
        }
        return $res;
    }

    public function chroot(string $path): int
    {
        $res = $this->record('chroot', ['path' => $path]);
        if ($res === 0) {
            $this->root = $path;
        }
        return $res;
    }

    public function chdir(string $path): int
    {
        $res = $this->record('chdir', ['path' => $path]);
        if ($res === 0) {
            $this->cwd = $path;
        }
        return $res;
    }

    public function setHostname(string $name): int
    {
        $res = $this->record('sethostname', ['name' => $name]);
        if ($res === 0) {
            $this->hostname = $name;
        }
        return $res;
    }

    public function ioctl(int $fd, int $request, mixed $arg = null): int
    {
        return $this->record('ioctl', ['fd' => $fd, 'request' => $request, 'arg' => $arg]);
    }

    public function syncfs(int $fd): int
    {
        return $this->record('syncfs', ['fd' => $fd]);
    }

    public function open(string $pathname, int $flags, int $mode = 0): int
    {
        return $this->record('open', ['pathname' => $pathname, 'flags' => $flags, 'mode' => $mode]) ?: 10;
    }

    public function close(int $fd): int
    {
        return $this->record('close', ['fd' => $fd]);
    }

    public function getPid(): int
    {
        return 10001;
    }

    public function getEuid(): int
    {
        return 0;
    }

    public function getLastError(): int
    {
        return $this->lastError;
    }

    public function getErrorString(int $errno): string
    {
        return match ($errno) {
            0  => 'Success',
            1  => 'Operation not permitted',
            2  => 'No such file or directory',
            3  => 'No such process',
            12 => 'Cannot allocate memory',
            13 => 'Permission denied',
            16 => 'Device or resource busy',
            22 => 'Invalid argument',
            24 => 'Too many open files',
            28 => 'No space left on device',
            38 => 'Function not implemented',
            default => "Unknown error ({$errno})",
        };
    }
}
