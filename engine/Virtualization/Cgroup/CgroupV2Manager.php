<?php
declare(strict_types=1);

namespace Oshim\Virtualization\Cgroup;

use Oshim\Virtualization\Exceptions\CgroupException;

/**
 * Linux Cgroups v2 unified hierarchy and controller lifecycle manager.
 */
class CgroupV2Manager
{
    private string $cgroupRoot;
    private string $sliceName;

    public function __construct(string $cgroupRoot = '/sys/fs/cgroup', string $sliceName = 'oshim')
    {
        $this->cgroupRoot = rtrim($cgroupRoot, '/');
        $this->sliceName = trim($sliceName, '/');
    }

    public function getCgroupRoot(): string
    {
        return $this->cgroupRoot;
    }

    public function getOshimSlicePath(): string
    {
        return "{$this->cgroupRoot}/{$this->sliceName}";
    }

    public function getContainerSlicePath(string $containerId): string
    {
        return "{$this->cgroupRoot}/{$this->sliceName}/{$containerId}";
    }

    /**
     * Initialize OSHIM parent slice and enable subtree controllers.
     */
    public function initializeSubtree(): void
    {
        $oshimPath = $this->getOshimSlicePath();
        if (!is_dir($oshimPath)) {
            if (!@mkdir($oshimPath, 0755, true) && !is_dir($oshimPath)) {
                throw new CgroupException("Failed to create OSHIM cgroup slice: {$oshimPath}");
            }
        }

        // Enable controllers in root subtree if permitted
        $this->enableSubtreeControllers($this->cgroupRoot);
        // Enable controllers in oshim subtree
        $this->enableSubtreeControllers($oshimPath);
    }

    public function enableSubtreeControllers(string $path): void
    {
        $controllersFile = "{$path}/cgroup.controllers";
        $subtreeFile = "{$path}/cgroup.subtree_control";

        if (!file_exists($controllersFile) || !file_exists($subtreeFile)) {
            return;
        }

        $content = @file_get_contents($controllersFile);
        if ($content === false || trim($content) === '') {
            return;
        }

        $available = explode(' ', trim($content));
        $desired = ['cpu', 'memory', 'io', 'pids', 'cpuset'];
        $toEnable = array_intersect($desired, $available);

        if (!empty($toEnable)) {
            $enableStr = '+' . implode(' +', $toEnable);
            @file_put_contents($subtreeFile, $enableStr);
        }
    }

    /**
     * Create a dedicated cgroup slice for a container and apply limits.
     */
    public function createContainerSlice(string $containerId, CgroupConfig $config): void
    {
        $this->initializeSubtree();
        $slicePath = $this->getContainerSlicePath($containerId);

        if (!is_dir($slicePath)) {
            if (!@mkdir($slicePath, 0755, true) && !is_dir($slicePath)) {
                throw new CgroupException("Failed to create container cgroup slice: {$slicePath}");
            }
        }

        $this->applyLimits($containerId, $config);
    }

    /**
     * Apply CPU, memory, IO, and PID limits to the container cgroup.
     */
    public function applyLimits(string $containerId, CgroupConfig $config): void
    {
        $slicePath = $this->getContainerSlicePath($containerId);
        if (!is_dir($slicePath)) {
            throw new CgroupException("Container cgroup slice not found: {$slicePath}");
        }

        // 1. CPU Quota & Period
        if ($config->cpuCores !== null && $config->cpuCores > 0) {
            $quota = (int)($config->cpuCores * 100000);
            $quota = max(1000, $quota);
            @file_put_contents("{$slicePath}/cpu.max", "{$quota} 100000");
        } else {
            @file_put_contents("{$slicePath}/cpu.max", "max 100000");
        }
        @file_put_contents("{$slicePath}/cpu.weight", (string)$config->cpuWeight);

        // 2. Memory Limits
        if ($config->memoryMaxBytes !== null && $config->memoryMaxBytes > 0) {
            @file_put_contents("{$slicePath}/memory.max", (string)$config->memoryMaxBytes);
            $highBytes = $config->memoryHighBytes ?? (int)($config->memoryMaxBytes * 0.875);
            @file_put_contents("{$slicePath}/memory.high", (string)$highBytes);
        } else {
            @file_put_contents("{$slicePath}/memory.max", "max");
            @file_put_contents("{$slicePath}/memory.high", "max");
        }

        if ($config->memoryLowBytes !== null) {
            @file_put_contents("{$slicePath}/memory.low", (string)$config->memoryLowBytes);
        }

        if ($config->memorySwapMaxBytes !== null) {
            @file_put_contents("{$slicePath}/memory.swap.max", (string)$config->memorySwapMaxBytes);
        }

        // Enable atomic OOM group kill
        if (file_exists("{$slicePath}/memory.oom.group") || is_dir($slicePath)) {
            @file_put_contents("{$slicePath}/memory.oom.group", "1");
        }

        // 3. PIDs Limit
        if ($config->pidsMax !== null && $config->pidsMax > 0) {
            @file_put_contents("{$slicePath}/pids.max", (string)$config->pidsMax);
        } else {
            @file_put_contents("{$slicePath}/pids.max", "max");
        }

        // 4. IO Limits
        if (!empty($config->ioLimits)) {
            $ioLines = [];
            foreach ($config->ioLimits as $dev => $limits) {
                $parts = [$dev];
                if (isset($limits['rbps'])) $parts[] = "rbps={$limits['rbps']}";
                if (isset($limits['wbps'])) $parts[] = "wbps={$limits['wbps']}";
                if (isset($limits['riops'])) $parts[] = "riops={$limits['riops']}";
                if (isset($limits['wiops'])) $parts[] = "wiops={$limits['wiops']}";
                $ioLines[] = implode(' ', $parts);
            }
            if (!empty($ioLines)) {
                @file_put_contents("{$slicePath}/io.max", implode("\n", $ioLines) . "\n");
            }
        }
    }

    /**
     * Attach a process to the container's cgroup.
     */
    public function attachProcess(string $containerId, int $pid): void
    {
        $slicePath = $this->getContainerSlicePath($containerId);
        $procsFile = "{$slicePath}/cgroup.procs";

        if (!is_dir($slicePath)) {
            throw new CgroupException("Container cgroup slice not found: {$slicePath}");
        }

        $res = @file_put_contents($procsFile, (string)$pid);
        if ($res === false) {
            throw new CgroupException("Failed to attach PID {$pid} to cgroup {$slicePath}");
        }
    }

    /**
     * Get all active PIDs enrolled in the container cgroup.
     *
     * @return list<int>
     */
    public function getActivePids(string $containerId): array
    {
        $procsFile = "{$this->getContainerSlicePath($containerId)}/cgroup.procs";
        if (!file_exists($procsFile)) {
            return [];
        }

        $lines = @file($procsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return $lines ? array_values(array_map('intval', $lines)) : [];
    }

    /**
     * Freeze all processes in the container cgroup (pause).
     */
    public function freeze(string $containerId): void
    {
        $freezeFile = "{$this->getContainerSlicePath($containerId)}/cgroup.freeze";
        @file_put_contents($freezeFile, "1");
    }

    /**
     * Unfreeze all processes in the container cgroup (resume).
     */
    public function unfreeze(string $containerId): void
    {
        $freezeFile = "{$this->getContainerSlicePath($containerId)}/cgroup.freeze";
        @file_put_contents($freezeFile, "0");
    }

    /**
     * Kill all processes in the container cgroup.
     */
    public function killAll(string $containerId): void
    {
        $slicePath = $this->getContainerSlicePath($containerId);
        if (!is_dir($slicePath)) {
            return;
        }

        // Modern atomic kill on Linux 5.14+
        if (file_exists("{$slicePath}/cgroup.kill")) {
            @file_put_contents("{$slicePath}/cgroup.kill", "1");
            return;
        }

        // Fallback: Freeze -> SIGKILL -> Unfreeze
        $this->freeze($containerId);
        $pids = $this->getActivePids($containerId);
        foreach ($pids as $pid) {
            if ($pid > 1 && function_exists('posix_kill')) {
                @posix_kill($pid, 9 /* SIGKILL */);
            }
        }
        $this->unfreeze($containerId);
    }

    /**
     * Destroy a container cgroup slice after terminating all processes.
     */
    public function destroyContainerSlice(string $containerId, int $timeoutSeconds = 5): void
    {
        $slicePath = $this->getContainerSlicePath($containerId);
        if (!is_dir($slicePath)) {
            return;
        }

        $this->killAll($containerId);

        $deadline = microtime(true) + $timeoutSeconds;
        while (microtime(true) < $deadline) {
            $pids = $this->getActivePids($containerId);
            if (empty($pids)) {
                break;
            }
            usleep(50000); // 50ms
        }

        if (@rmdir($slicePath)) {
            return;
        }

        // On non-cgroup2fs (test or mock filesystems), recursively remove files before rmdir
        $this->cleanupDirectory($slicePath);
        @rmdir($slicePath);
    }

    private function cleanupDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = @scandir($dir);
        if ($items) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $path = "{$dir}/{$item}";
                if (is_dir($path) && !is_link($path)) {
                    $this->cleanupDirectory($path);
                    @rmdir($path);
                } else {
                    @unlink($path);
                }
            }
        }
    }

    /**
     * Read and compute real-time telemetry from the cgroup files.
     */
    public function getTelemetry(string $containerId, ?CgroupTelemetry $previous = null, ?float $elapsedSeconds = null): CgroupTelemetry
    {
        $slicePath = $this->getContainerSlicePath($containerId);
        $now = microtime(true);

        // 1. CPU Stat
        $cpuStat = $this->parseKeyValueFile("{$slicePath}/cpu.stat");
        $usageUsec = (int)($cpuStat['usage_usec'] ?? 0);
        $userUsec = (int)($cpuStat['user_usec'] ?? 0);
        $systemUsec = (int)($cpuStat['system_usec'] ?? 0);
        $nrThrottled = (int)($cpuStat['nr_throttled'] ?? 0);
        $throttledUsec = (int)($cpuStat['throttled_usec'] ?? 0);

        $cpuPercent = 0.0;
        if ($previous !== null && $elapsedSeconds !== null && $elapsedSeconds > 0) {
            $deltaUsage = $usageUsec - $previous->cpuUsageUsec;
            if ($deltaUsage > 0) {
                $cpuPercent = ($deltaUsage / ($elapsedSeconds * 1000000.0)) * 100.0;
            }
            $cpuPercent = max(0.0, $cpuPercent);
        }

        // 2. Memory
        $memCurrentStr = @file_get_contents("{$slicePath}/memory.current");
        $memCurrent = ($memCurrentStr !== false && trim($memCurrentStr) !== '') ? (int)trim($memCurrentStr) : 0;

        $memMaxStr = @file_get_contents("{$slicePath}/memory.max");
        $memMaxTrim = ($memMaxStr !== false) ? trim($memMaxStr) : '';
        $memMax = ($memMaxTrim === 'max' || $memMaxTrim === '') ? 0 : (int)$memMaxTrim;
        $memPercent = ($memMax > 0) ? ($memCurrent / $memMax) * 100.0 : 0.0;

        $memStat = $this->parseKeyValueFile("{$slicePath}/memory.stat");
        $memEvents = $this->parseKeyValueFile("{$slicePath}/memory.events");
        $oomCount = (int)($memEvents['oom_kill'] ?? $memEvents['oom'] ?? 0);

        // 3. PIDs
        $pidsCurrentStr = @file_get_contents("{$slicePath}/pids.current");
        $pidsCurrent = ($pidsCurrentStr !== false && trim($pidsCurrentStr) !== '') ? (int)trim($pidsCurrentStr) : count($this->getActivePids($containerId));

        $pidsMaxStr = @file_get_contents("{$slicePath}/pids.max");
        $pidsMaxTrim = ($pidsMaxStr !== false) ? trim($pidsMaxStr) : '';
        $pidsMax = ($pidsMaxTrim === 'max' || $pidsMaxTrim === '') ? 0 : (int)$pidsMaxTrim;

        // 4. IO
        $ioStat = $this->parseIoStatFile("{$slicePath}/io.stat");

        // 5. Cgroup Events & Freeze
        $cgEvents = $this->parseKeyValueFile("{$slicePath}/cgroup.events");
        $isPopulated = ((int)($cgEvents['populated'] ?? ($pidsCurrent > 0 ? 1 : 0))) === 1;
        $isFrozen = ((int)($cgEvents['frozen'] ?? 0)) === 1;

        return new CgroupTelemetry(
            cpuUsagePercent: $cpuPercent,
            cpuUsageUsec: $usageUsec,
            cpuUserUsec: $userUsec,
            cpuSystemUsec: $systemUsec,
            cpuNrThrottled: $nrThrottled,
            cpuThrottledUsec: $throttledUsec,
            memoryCurrentBytes: $memCurrent,
            memoryMaxBytes: $memMax,
            memoryUsagePercent: $memPercent,
            memoryAnonBytes: (int)($memStat['anon'] ?? 0),
            memoryFileBytes: (int)($memStat['file'] ?? 0),
            memoryOomCount: $oomCount,
            pidsCurrent: $pidsCurrent,
            pidsMax: $pidsMax,
            ioReadBytes: $ioStat['rbytes'],
            ioWriteBytes: $ioStat['wbytes'],
            ioReadOps: $ioStat['rios'],
            ioWriteOps: $ioStat['wios'],
            isFrozen: $isFrozen,
            isPopulated: $isPopulated,
            timestamp: $now
        );
    }

    public function parseKeyValueFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $result = [];
        $lines = @file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines) {
            foreach ($lines as $line) {
                $parts = preg_split('/\s+/', trim($line), 2);
                if ($parts !== false && count($parts) === 2) {
                    $result[$parts[0]] = $parts[1];
                }
            }
        }

        return $result;
    }

    public function parseIoStatFile(string $filePath): array
    {
        $result = ['rbytes' => 0, 'wbytes' => 0, 'rios' => 0, 'wios' => 0];
        if (!file_exists($filePath)) {
            return $result;
        }

        $lines = @file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines) {
            foreach ($lines as $line) {
                $tokens = explode(' ', trim($line));
                foreach ($tokens as $token) {
                    if (str_contains($token, '=')) {
                        [$k, $v] = explode('=', $token, 2);
                        if (isset($result[$k])) {
                            $result[$k] += (int)$v;
                        }
                    }
                }
            }
        }

        return $result;
    }
}
