<?php
declare(strict_types=1);

namespace Oshim\Virtualization\Storage;

use Oshim\Virtualization\ContainerConfig;
use Oshim\Virtualization\Exceptions\StorageException;
use Oshim\Virtualization\Syscall\LinuxConstants;
use Oshim\Virtualization\Syscall\LinuxSyscall;
use Oshim\Virtualization\Syscall\SyscallInterface;

/**
 * Multi-layer OverlayFS Copy-on-Write storage manager.
 */
class OverlayFsManager
{
    private string $storageRoot;
    private string $imagesPath;
    private string $instancesPath;
    private string $snapshotsPath;
    private SyscallInterface $syscall;

    public function __construct(string $storageRoot = '/var/lib/oshim', ?SyscallInterface $syscall = null)
    {
        $this->storageRoot = rtrim($storageRoot, '/');
        $this->imagesPath = "{$this->storageRoot}/images";
        $this->instancesPath = "{$this->storageRoot}/instances";
        $this->snapshotsPath = "{$this->storageRoot}/snapshots";
        $this->syscall = $syscall ?? new LinuxSyscall();
    }

    public function getStorageRoot(): string
    {
        return $this->storageRoot;
    }

    public function getImagesPath(): string
    {
        return $this->imagesPath;
    }

    public function getInstancesPath(): string
    {
        return $this->instancesPath;
    }

    public function getSnapshotsPath(): string
    {
        return $this->snapshotsPath;
    }

    public function initializeStorage(): void
    {
        foreach ([$this->imagesPath, $this->instancesPath, $this->snapshotsPath] as $dir) {
            if (!is_dir($dir)) {
                if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
                    throw new StorageException("Failed to initialize storage directory: {$dir}");
                }
            }
        }
    }

    public function getInstancePath(string $instanceId): string
    {
        return "{$this->instancesPath}/{$instanceId}";
    }

    /**
     * Prepare instance storage directories (diff/upper, work, merged/rootfs).
     *
     * @return array{upper: string, work: string, merged: string, lowers: list<string>}
     */
    public function prepareInstanceStorage(string $instanceId, string $baseImage = 'ubuntu-24.04-base'): array
    {
        $this->initializeStorage();
        $instDir = $this->getInstancePath($instanceId);

        $upper = "{$instDir}/upper";
        $work = "{$instDir}/work";
        $merged = "{$instDir}/merged";

        foreach ([$upper, $work, $merged] as $d) {
            if (!is_dir($d)) {
                if (!@mkdir($d, 0755, true) && !is_dir($d)) {
                    throw new StorageException("Failed to create container directory: {$d}");
                }
            }
        }

        $baseImageDir = "{$this->imagesPath}/{$baseImage}/rootfs";
        if (!is_dir($baseImageDir)) {
            @mkdir($baseImageDir, 0755, true);
        }

        $metadata = [
            'instance_id' => $instanceId,
            'base_image'  => $baseImage,
            'lower_dirs'  => [$baseImageDir],
            'created_at'  => time(),
        ];
        file_put_contents("{$instDir}/metadata.json", json_encode($metadata, JSON_PRETTY_PRINT));

        return [
            'upper'   => $upper,
            'work'    => $work,
            'merged'  => $merged,
            'lowers'  => [$baseImageDir],
        ];
    }

    /**
     * Build the OverlayFS mount option string.
     *
     * @param list<string> $lowerDirs
     */
    public function buildMountOptions(string $upper, string $work, array $lowerDirs): string
    {
        if (empty($lowerDirs)) {
            throw new StorageException("Cannot compose OverlayFS options: lowerdir chain is empty.");
        }

        $lowerdirStr = implode(':', $lowerDirs);
        return "lowerdir={$lowerdirStr},upperdir={$upper},workdir={$work}";
    }

    /**
     * Mount OverlayFS for an instance.
     *
     * @param list<string>|null $customLowerDirs
     */
    public function mountOverlay(string $instanceId, ?array $customLowerDirs = null): string
    {
        $instDir = $this->getInstancePath($instanceId);
        $upper = "{$instDir}/upper";
        $work = "{$instDir}/work";
        $merged = "{$instDir}/merged";

        $metaFile = "{$instDir}/metadata.json";
        $meta = file_exists($metaFile) ? json_decode((string)file_get_contents($metaFile), true) : [];
        $lowerDirs = $customLowerDirs ?? ($meta['lower_dirs'] ?? []);

        if (empty($lowerDirs)) {
            throw new StorageException("No lower layers defined for instance {$instanceId}");
        }

        $mountOpts = $this->buildMountOptions($upper, $work, $lowerDirs);

        $res = $this->syscall->mount('overlay', $merged, 'overlay', 0, $mountOpts);
        if ($res !== 0) {
            $errno = $this->syscall->getLastError();
            $errStr = $this->syscall->getErrorString($errno);
            throw new StorageException("OverlayFS mount failed for instance {$instanceId}: {$errStr} (errno={$errno})");
        }

        return $merged;
    }

    /**
     * Unmount OverlayFS for an instance.
     */
    public function unmountOverlay(string $instanceId, bool $force = false): void
    {
        $merged = "{$this->getInstancePath($instanceId)}/merged";
        if (!$this->isMounted($instanceId)) {
            return;
        }

        $flags = $force ? LinuxConstants::MNT_DETACH : 0;
        $res = $this->syscall->umount2($merged, $flags);
        if ($res !== 0) {
            $errno = $this->syscall->getLastError();
            $errStr = $this->syscall->getErrorString($errno);
            throw new StorageException("Failed to unmount OverlayFS at {$merged}: {$errStr} (errno={$errno})");
        }
    }

    /**
     * Check whether the merged rootfs is currently mounted.
     */
    public function isMounted(string $instanceId): bool
    {
        $merged = "{$this->getInstancePath($instanceId)}/merged";
        $mounts = @file_get_contents('/proc/mounts');
        if ($mounts === false) {
            return false;
        }

        return str_contains($mounts, $merged);
    }

    /**
     * Inject host configurations into container rootfs (/etc/hostname, hosts, resolv.conf, ssh).
     */
    public function injectConfigurations(string $rootfsPath, ContainerConfig $config): void
    {
        $etcDir = "{$rootfsPath}/etc";
        if (!is_dir($etcDir)) {
            @mkdir($etcDir, 0755, true);
        }

        // 1. /etc/hostname
        @file_put_contents("{$etcDir}/hostname", $config->getName() . "\n");

        // 2. /etc/hosts
        $hostsContent = "127.0.0.1\tlocalhost\n::1\tlocalhost ip6-localhost ip6-loopback\n";
        if ($config->getIpAddress() !== null) {
            $hostsContent .= "{$config->getIpAddress()}\t{$config->getName()}\n";
        }
        @file_put_contents("{$etcDir}/hosts", $hostsContent);

        // 3. /etc/resolv.conf
        $resolvContent = '';
        foreach ($config->getDnsServers() as $dns) {
            $resolvContent .= "nameserver {$dns}\n";
        }
        @file_put_contents("{$etcDir}/resolv.conf", $resolvContent ?: "nameserver 1.1.1.1\nnameserver 8.8.8.8\n");

        // 4. SSH authorized keys
        $sshKeys = $config->getSshAuthorizedKeys();
        if (!empty($sshKeys)) {
            $sshDir = "{$rootfsPath}/root/.ssh";
            if (!is_dir($sshDir)) {
                @mkdir($sshDir, 0700, true);
            }
            @file_put_contents("{$sshDir}/authorized_keys", implode("\n", $sshKeys) . "\n");
            @chmod("{$sshDir}/authorized_keys", 0600);
        }
    }

    /**
     * Destroy storage for an instance.
     */
    public function destroyInstanceStorage(string $instanceId): void
    {
        if ($this->isMounted($instanceId)) {
            $this->unmountOverlay($instanceId, true);
        }

        $instDir = $this->getInstancePath($instanceId);
        if (is_dir($instDir)) {
            $this->recursiveRmdir($instDir);
        }
    }

    public function recursiveRmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = @scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = "{$dir}/{$item}";
            if (is_dir($path) && !is_link($path)) {
                $this->recursiveRmdir($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }

    public function copyDirectory(string $src, string $dst): void
    {
        if (!is_dir($src)) {
            return;
        }

        @mkdir($dst, 0755, true);
        $items = @scandir($src);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $s = "{$src}/{$item}";
            $d = "{$dst}/{$item}";
            if (is_dir($s) && !is_link($s)) {
                $this->copyDirectory($s, $d);
            } else {
                @copy($s, $d);
            }
        }
    }

    public function getDirectorySize(string $dir): int
    {
        $size = 0;
        if (!is_dir($dir)) {
            return $size;
        }

        $items = @scandir($dir);
        if ($items === false) {
            return $size;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = "{$dir}/{$item}";
            if (is_dir($path) && !is_link($path)) {
                $size += $this->getDirectorySize($path);
            } else {
                $size += (int)@filesize($path);
            }
        }

        return $size;
    }
}
