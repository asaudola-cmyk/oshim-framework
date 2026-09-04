<?php
declare(strict_types=1);

namespace Oshim\Virtualization\Driver;

use InvalidArgumentException;
use Oshim\Virtualization\Container;
use Oshim\Virtualization\ContainerConfig;
use Oshim\Virtualization\ContainerState;
use Oshim\Virtualization\ContainerStats;
use Oshim\Virtualization\ExecResult;
use Oshim\Virtualization\Exceptions\VirtualizationException;
use Oshim\Virtualization\Storage\SnapshotMetadata;
use RuntimeException;

/**
 * High-fidelity, deterministic in-memory & mock-filesystem virtualization driver for non-root environments.
 */
class MockVirtualizationDriver implements VirtualizationDriverInterface
{
    private string $mockBasePath;
    /** @var array<string, Container> */
    private array $containers = [];
    /** @var array<string, array<string, mixed>> */
    private array $instancesData = [];
    /** @var array<string, array<string, mixed>> */
    private array $cgroups = [];
    /** @var array<string, array<string, mixed>> */
    private array $snapshots = [];
    /** @var array<string, string> */
    private array $faultInjections = [];
    /** @var list<array{action: string, id: string, data: array<mixed>, timestamp: float}> */
    private array $executionLogs = [];

    public function __construct(?string $mockBasePath = null)
    {
        $this->mockBasePath = $mockBasePath ?? sys_get_temp_dir() . '/oshim_mock_' . getmypid();
        if (!is_dir($this->mockBasePath)) {
            @mkdir($this->mockBasePath, 0777, true);
        }
    }

    public function reset(): void
    {
        $this->containers = [];
        $this->instancesData = [];
        $this->cgroups = [];
        $this->snapshots = [];
        $this->faultInjections = [];
        $this->executionLogs = [];
    }

    public function getMockBasePath(): string
    {
        return $this->mockBasePath;
    }

    // --- Typed DTO Methods ---

    public function create(ContainerConfig $config): Container
    {
        $this->checkFault('create');
        $id = $config->getId();

        if (isset($this->containers[$id])) {
            throw new VirtualizationException("Container with ID '{$id}' already exists");
        }

        $instDir = "{$this->mockBasePath}/{$id}";
        @mkdir("{$instDir}/diff", 0777, true);
        @mkdir("{$instDir}/work", 0777, true);
        @mkdir("{$instDir}/merged", 0777, true);

        $container = new Container(
            id: $id,
            name: $config->getName(),
            config: $config,
            state: ContainerState::CREATED,
            pid: null,
            rootfsPath: "{$instDir}/merged",
            diffPath: "{$instDir}/diff",
            workPath: "{$instDir}/work",
            cgroupPath: "/sys/fs/cgroup/oshim/{$id}",
            tapDevice: $config->getTapDevice() ?? ('tap_' . substr(md5($id), 0, 8)),
            ipAddress: $config->getIpAddress() ?? '10.42.0.10',
            macAddress: $config->getMacAddress() ?? '52:54:00:12:34:56'
        );

        $this->containers[$id] = $container;

        $this->instancesData[$id] = [
            'id'                  => $id,
            'instance_id'         => $id,
            'name'                => $config->getName(),
            'hostname'            => $config->getName(),
            'state'               => ContainerState::CREATED,
            'status'              => ContainerState::CREATED,
            'vcpu'                => $config->getVcpu(),
            'cpu_limit'           => $config->getVcpu(),
            'ram_mb'              => (int)($config->getMemoryLimitBytes() / 1024 / 1024),
            'memory_limit_bytes'  => $config->getMemoryLimitBytes(),
            'disk_gb'             => (int)($config->getDiskLimitBytes() / 1024 / 1024 / 1024),
            'disk_limit_bytes'    => $config->getDiskLimitBytes(),
            'os'                  => $config->getImage(),
            'image'               => $config->getImage(),
            'ip_address'          => $container->getIpAddress(),
            'ipv4'                => $container->getIpAddress(),
            'mac_address'         => $container->getMacAddress(),
            'tap_dev'             => $container->getTapDevice(),
            'tap_device'          => $container->getTapDevice(),
            'pid'                 => null,
            'rx_bytes'            => 1024,
            'tx_bytes'            => 512,
            'cpu_time_usec'       => 0,
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
            'started_at'          => null,
            'stopped_at'          => null,
        ];

        $this->cgroups[$id] = [
            'cpu.max'    => ((int)($config->getVcpu() * 100000)) . ' 100000',
            'memory.max' => (string)$config->getMemoryLimitBytes(),
            'io.max'     => 'rbps=52428800 wbps=52428800',
            'pids.max'   => (string)$config->getPidsLimit(),
        ];

        $this->logAction('create', $id, $config->toArray());
        return $container;
    }

    public function start(string $id): bool
    {
        $this->checkFault('start');
        $container = $this->getContainer($id);
        if ($container === null) {
            throw new VirtualizationException("Container '{$id}' not found");
        }

        if ($container->isRunning()) {
            throw new VirtualizationException("Container '{$id}' is already running");
        }

        $mockPid = mt_rand(10000, 60000);
        $container->setState(ContainerState::RUNNING);
        $container->setPid($mockPid);

        $this->instancesData[$id]['state'] = ContainerState::RUNNING;
        $this->instancesData[$id]['status'] = ContainerState::RUNNING;
        $this->instancesData[$id]['pid'] = $mockPid;
        $this->instancesData[$id]['started_at'] = date('Y-m-d H:i:s');
        $this->instancesData[$id]['updated_at'] = date('Y-m-d H:i:s');

        $this->logAction('start', $id, ['pid' => $mockPid]);
        return true;
    }

    public function stop(string $id, int $timeout = 10, bool $force = false): bool
    {
        $this->checkFault('stop');
        $container = $this->getContainer($id);
        if ($container === null) {
            throw new VirtualizationException("Container '{$id}' not found");
        }

        if ($container->isStopped() && !$force) {
            throw new VirtualizationException("Container '{$id}' is already stopped");
        }

        $container->setState(ContainerState::STOPPED);
        $container->setPid(null);

        $this->instancesData[$id]['state'] = ContainerState::STOPPED;
        $this->instancesData[$id]['status'] = ContainerState::STOPPED;
        $this->instancesData[$id]['pid'] = null;
        $this->instancesData[$id]['stopped_at'] = date('Y-m-d H:i:s');
        $this->instancesData[$id]['updated_at'] = date('Y-m-d H:i:s');

        $this->logAction('stop', $id, ['force' => $force]);
        return true;
    }

    public function pause(string $id): bool
    {
        $this->checkFault('pause');
        $container = $this->getContainer($id);
        if ($container === null) {
            throw new VirtualizationException("Container '{$id}' not found");
        }

        if (!$container->isRunning()) {
            throw new VirtualizationException("Cannot pause container '{$id}' in state {$container->getState()}");
        }

        $container->setState(ContainerState::PAUSED);
        $this->instancesData[$id]['state'] = ContainerState::PAUSED;
        $this->instancesData[$id]['status'] = ContainerState::PAUSED;
        $this->instancesData[$id]['updated_at'] = date('Y-m-d H:i:s');

        $this->logAction('pause', $id);
        return true;
    }

    public function resume(string $id): bool
    {
        $this->checkFault('resume');
        $container = $this->getContainer($id);
        if ($container === null) {
            throw new VirtualizationException("Container '{$id}' not found");
        }

        if (!$container->isPaused() && $container->getState() !== 'SUSPENDED') {
            throw new VirtualizationException("Cannot resume container '{$id}' in state {$container->getState()}");
        }

        $container->setState(ContainerState::RUNNING);
        $this->instancesData[$id]['state'] = ContainerState::RUNNING;
        $this->instancesData[$id]['status'] = ContainerState::RUNNING;
        $this->instancesData[$id]['updated_at'] = date('Y-m-d H:i:s');

        $this->logAction('resume', $id);
        return true;
    }

    public function restart(string $id, int $timeout = 10): bool
    {
        $this->stop($id, $timeout, true);
        return $this->start($id);
    }

    public function destroy(string $id): bool
    {
        $this->checkFault('destroy');
        $container = $this->getContainer($id);
        if ($container === null) {
            throw new VirtualizationException("Container '{$id}' not found");
        }

        if ($container->isRunning()) {
            $this->stop($id, 5, true);
        }

        $instDir = "{$this->mockBasePath}/{$id}";
        $this->recursiveDelete($instDir);

        unset($this->containers[$id], $this->instancesData[$id], $this->cgroups[$id]);
        $this->snapshots = array_filter($this->snapshots, fn($s) => ($s['instance_id'] ?? '') !== $id);

        $this->logAction('destroy', $id);
        return true;
    }

    public function stats(string $id): ContainerStats
    {
        $this->checkFault('stats');
        $container = $this->getContainer($id);
        if ($container === null) {
            throw new VirtualizationException("Container '{$id}' not found");
        }

        $inst = &$this->instancesData[$id];
        $isRunning = $container->isRunning();

        if (!$isRunning) {
            return new ContainerStats(
                instanceId: $id,
                state: $container->getState(),
                cpuUsagePct: 0.0,
                cpuTimeUsec: (int)($inst['cpu_time_usec'] ?? 0),
                memoryUsageBytes: 0,
                memoryLimitBytes: $container->getConfig()->getMemoryLimitBytes(),
                memoryUsagePct: 0.0,
                pidsCount: 0,
                isFrozen: $container->isPaused(),
                timestamp: microtime(true)
            );
        }

        $inst['rx_bytes'] = ($inst['rx_bytes'] ?? 1024) + mt_rand(1024, 4096);
        $inst['tx_bytes'] = ($inst['tx_bytes'] ?? 512) + mt_rand(512, 2048);
        $inst['cpu_time_usec'] = ($inst['cpu_time_usec'] ?? 0) + 50000;

        $memLimit = $container->getConfig()->getMemoryLimitBytes();
        $memUsed = (int)($memLimit * (0.20 + 0.10 * sin(time())));
        $cpuPct = round(min(100.0, max(2.5, 18.0 + 8.0 * cos(time()))), 2);

        return new ContainerStats(
            instanceId: $id,
            state: ContainerState::RUNNING,
            cpuUsagePct: $cpuPct,
            cpuTimeUsec: (int)$inst['cpu_time_usec'],
            cpuUserUsec: (int)($inst['cpu_time_usec'] * 0.7),
            cpuSystemUsec: (int)($inst['cpu_time_usec'] * 0.3),
            memoryUsageBytes: $memUsed,
            memoryLimitBytes: $memLimit,
            memoryUsagePct: round(($memUsed / $memLimit) * 100.0, 2),
            memoryAnonBytes: (int)($memUsed * 0.8),
            memoryFileBytes: (int)($memUsed * 0.2),
            memoryOomCount: 0,
            diskReadBytes: 10485760,
            diskWriteBytes: 5242880,
            diskReadIops: 120,
            diskWriteIops: 60,
            diskReadBytesSec: 1048576,
            diskWriteBytesSec: 524288,
            netRxBytes: (int)$inst['rx_bytes'],
            netTxBytes: (int)$inst['tx_bytes'],
            netRxPackets: (int)($inst['rx_bytes'] / 64),
            netTxPackets: (int)($inst['tx_bytes'] / 64),
            netRxBytesSec: 204800,
            netTxBytesSec: 102400,
            pidsCount: 18,
            isFrozen: false,
            timestamp: microtime(true)
        );
    }

    public function exec(string $id, array $command, array $env = [], int $timeout = 30): ExecResult
    {
        $this->checkFault('exec');
        $container = $this->getContainer($id);
        if ($container === null) {
            throw new VirtualizationException("Container '{$id}' not found");
        }

        $cmdStr = implode(' ', $command);
        $start = microtime(true);

        // Built-in deterministic responses
        if (str_starts_with($cmdStr, 'echo ')) {
            $msg = substr($cmdStr, 5);
            return new ExecResult(0, $msg . "\n", '', (microtime(true) - $start) * 1000);
        }
        if ($cmdStr === 'hostname') {
            return new ExecResult(0, $container->getName() . "\n", '', (microtime(true) - $start) * 1000);
        }
        if ($cmdStr === 'whoami') {
            return new ExecResult(0, "root\n", '', (microtime(true) - $start) * 1000);
        }
        if ($cmdStr === 'uptime') {
            return new ExecResult(0, " 14:25:00 up 2 days, 1 user, load average: 0.12, 0.08, 0.05\n", '', (microtime(true) - $start) * 1000);
        }
        if ($cmdStr === 'uname -a') {
            return new ExecResult(0, "Linux {$container->getName()} 6.8.0-generic #1 SMP x86_64 GNU/Linux\n", '', (microtime(true) - $start) * 1000);
        }

        return new ExecResult(0, "Mock executed: {$cmdStr}\n", '', (microtime(true) - $start) * 1000);
    }

    public function createSnapshot(string $id, string $snapshotName): string
    {
        $this->checkFault('createSnapshot');
        $container = $this->getContainer($id);
        if ($container === null) {
            throw new VirtualizationException("Container '{$id}' not found");
        }

        $snapId = 'snap_' . $id . '_' . time() . '_' . substr(md5($snapshotName), 0, 6);
        $srcDiff = "{$this->mockBasePath}/{$id}/diff";
        $snapTarget = "{$this->mockBasePath}/snapshots/{$snapId}";

        @mkdir($snapTarget, 0777, true);
        $this->recursiveCopy($srcDiff, $snapTarget);

        $snapMeta = [
            'id'            => $snapId,
            'snapshot_id'   => $snapId,
            'instance_id'   => $id,
            'name'          => $snapshotName,
            'snapshot_name' => $snapshotName,
            'state'         => $container->getState(),
            'path'          => $snapTarget,
            'layer_path'    => $snapTarget,
            'size_bytes'    => 2048,
            'created_at'    => time(),
            'layer_stack'   => [$snapTarget],
        ];

        $this->snapshots[$snapId] = $snapMeta;
        $this->logAction('createSnapshot', $id, ['snapshot_id' => $snapId, 'name' => $snapshotName]);

        return $snapId;
    }

    public function rollbackSnapshot(string $id, string $snapshotId): bool
    {
        $this->checkFault('rollbackSnapshot');
        $container = $this->getContainer($id);
        if ($container === null) {
            throw new VirtualizationException("Container '{$id}' not found");
        }

        if (!isset($this->snapshots[$snapshotId]) || ($this->snapshots[$snapshotId]['instance_id'] ?? '') !== $id) {
            throw new VirtualizationException("Snapshot '{$snapshotId}' does not exist for instance '{$id}'");
        }

        $snap = $this->snapshots[$snapshotId];
        $instDiff = "{$this->mockBasePath}/{$id}/diff";

        $this->recursiveDelete($instDiff);
        @mkdir($instDiff, 0777, true);
        $this->recursiveCopy($snap['path'], $instDiff);

        if (isset($snap['state'])) {
            $container->setState($snap['state']);
            $this->instancesData[$id]['state'] = $snap['state'];
            $this->instancesData[$id]['status'] = $snap['state'];
        }

        $this->logAction('rollbackSnapshot', $id, ['snapshot_id' => $snapshotId]);
        return true;
    }

    public function deleteSnapshot(string $id, string $snapshotId): bool
    {
        $this->checkFault('deleteSnapshot');
        if (!isset($this->snapshots[$snapshotId])) {
            throw new VirtualizationException("Snapshot '{$snapshotId}' not found");
        }

        $snap = $this->snapshots[$snapshotId];
        $this->recursiveDelete($snap['path']);
        unset($this->snapshots[$snapshotId]);

        $this->logAction('deleteSnapshot', $id, ['snapshot_id' => $snapshotId]);
        return true;
    }

    public function listSnapshots(string $id): array
    {
        return array_values(array_filter($this->snapshots, fn($s) => ($s['instance_id'] ?? '') === $id));
    }

    public function getContainer(string $id): ?Container
    {
        return $this->containers[$id] ?? null;
    }

    public function listContainers(): array
    {
        return array_values($this->containers);
    }

    // --- Legacy / Facade Bridge Methods ---

    public function createInstance(array $spec): string
    {
        $config = ContainerConfig::fromArray($spec);
        $container = $this->create($config);
        return $container->getId();
    }

    public function startInstance(string $instanceId): bool
    {
        return $this->start($instanceId);
    }

    public function stopInstance(string $instanceId, bool $force = false): bool
    {
        return $this->stop($instanceId, 10, $force);
    }

    public function restartInstance(string $instanceId): bool
    {
        return $this->restart($instanceId);
    }

    public function suspendInstance(string $instanceId): bool
    {
        $this->checkFault('suspendInstance');
        $container = $this->getContainer($instanceId);
        if ($container === null) {
            throw new RuntimeException("Instance not found: {$instanceId}");
        }

        $container->setState(ContainerState::SUSPENDED);
        $this->instancesData[$instanceId]['state'] = ContainerState::SUSPENDED;
        $this->instancesData[$instanceId]['status'] = ContainerState::SUSPENDED;
        $this->instancesData[$instanceId]['updated_at'] = date('Y-m-d H:i:s');

        $this->logAction('suspendInstance', $instanceId);
        return true;
    }

    public function resumeInstance(string $instanceId): bool
    {
        return $this->resume($instanceId);
    }

    public function destroyInstance(string $instanceId): bool
    {
        return $this->destroy($instanceId);
    }

    public function getInstance(string $instanceId): ?array
    {
        return $this->instancesData[$instanceId] ?? null;
    }

    public function listInstances(): array
    {
        return array_values($this->instancesData);
    }

    public function getInstanceStats(string $instanceId): array
    {
        return $this->stats($instanceId)->toArray();
    }

    public function setCgroupLimits(string $instanceId, array $limits): bool
    {
        $this->checkFault('setCgroupLimits');
        if (!isset($this->instancesData[$instanceId])) {
            throw new RuntimeException("Instance not found: {$instanceId}");
        }

        $this->cgroups[$instanceId] = array_merge($this->cgroups[$instanceId] ?? [], $limits);
        return true;
    }

    public function getCgroupLimits(string $instanceId): array
    {
        return $this->cgroups[$instanceId] ?? [];
    }

    // --- Fault Injection & Inspection Helpers ---

    public function injectFault(string $operation, string $errorMessage): void
    {
        $this->faultInjections[$operation] = $errorMessage;
    }

    public function clearFaults(): void
    {
        $this->faultInjections = [];
    }

    public function getExecutionLogs(): array
    {
        return $this->executionLogs;
    }

    private function checkFault(string $operation): void
    {
        if (isset($this->faultInjections[$operation])) {
            $msg = $this->faultInjections[$operation];
            unset($this->faultInjections[$operation]);
            throw new RuntimeException($msg);
        }
    }

    private function logAction(string $action, string $id, array $data = []): void
    {
        $this->executionLogs[] = [
            'action'    => $action,
            'id'        => $id,
            'data'      => $data,
            'timestamp' => microtime(true),
        ];
    }

    private function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = @scandir($dir);
        if ($files) {
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                $path = "{$dir}/{$file}";
                is_dir($path) && !is_link($path) ? $this->recursiveDelete($path) : @unlink($path);
            }
        }
        @rmdir($dir);
    }

    private function recursiveCopy(string $src, string $dst): void
    {
        if (!is_dir($src)) return;
        @mkdir($dst, 0777, true);
        $files = @scandir($src);
        if ($files) {
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                $s = "{$src}/{$file}";
                $d = "{$dst}/{$file}";
                is_dir($s) && !is_link($s) ? $this->recursiveCopy($s, $d) : @copy($s, $d);
            }
        }
    }
}
