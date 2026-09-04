<?php
declare(strict_types=1);

namespace Oshim\Tests\Harness;

use RuntimeException;

/**
 * Deterministic rootless virtualization and telemetry simulation driver.
 */
class VirtualizationMockDriver
{
    private array $instances = [];
    private array $cgroups = [];
    private array $snapshots = [];
    private array $faultInjections = [];
    private array $executionLogs = [];

    public function __construct()
    {
        $this->reset();
    }

    public function reset(): void
    {
        $this->instances = [];
        $this->cgroups = [];
        $this->snapshots = [];
        $this->faultInjections = [];
        $this->executionLogs = [];
    }

    public function createInstance(array $spec): string
    {
        $this->checkFault('createInstance');

        $id = $spec['id'] ?? ('vm-' . bin2hex(random_bytes(6)));
        $name = $spec['name'] ?? ('instance-' . substr($id, 3));
        $vcpu = (int)($spec['vcpu'] ?? 1);
        $ramMb = (int)($spec['ram_mb'] ?? 1024);
        $diskGb = (int)($spec['disk_gb'] ?? 20);
        $os = $spec['os'] ?? 'ubuntu-22.04';
        $ipv4 = $spec['ipv4'] ?? ('10.0.0.' . (count($this->instances) + 2));
        $ipv6 = $spec['ipv6'] ?? ('fd00::' . bin2hex(random_bytes(2)));
        $tapDev = $spec['tap_dev'] ?? ('tap_' . substr($id, 3));

        $now = date('Y-m-d H:i:s');
        $this->instances[$id] = [
            'id' => $id,
            'name' => $name,
            'state' => 'STOPPED',
            'vcpu' => $vcpu,
            'ram_mb' => $ramMb,
            'disk_gb' => $diskGb,
            'os' => $os,
            'ipv4' => $ipv4,
            'ipv6' => $ipv6,
            'tap_dev' => $tapDev,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $this->cgroups[$id] = [
            'cpu.max' => ($vcpu * 100000) . ' 100000',
            'memory.max' => ($ramMb * 1024 * 1024),
            'io.max' => 'rbps=104857600 wbps=52428800',
            'pids.max' => 1000,
        ];

        $this->logAction('createInstance', $id, $spec);
        return $id;
    }

    public function startInstance(string $instanceId): bool
    {
        $this->checkFault('startInstance');
        if (!isset($this->instances[$instanceId])) {
            throw new RuntimeException("Instance not found: {$instanceId}");
        }

        $this->instances[$instanceId]['state'] = 'RUNNING';
        $this->instances[$instanceId]['updated_at'] = date('Y-m-d H:i:s');
        $this->logAction('startInstance', $instanceId);
        return true;
    }

    public function stopInstance(string $instanceId, bool $force = false): bool
    {
        $this->checkFault('stopInstance');
        if (!isset($this->instances[$instanceId])) {
            throw new RuntimeException("Instance not found: {$instanceId}");
        }

        $this->instances[$instanceId]['state'] = 'STOPPED';
        $this->instances[$instanceId]['updated_at'] = date('Y-m-d H:i:s');
        $this->logAction('stopInstance', $instanceId, ['force' => $force]);
        return true;
    }

    public function restartInstance(string $instanceId): bool
    {
        $this->stopInstance($instanceId, true);
        return $this->startInstance($instanceId);
    }

    public function suspendInstance(string $instanceId): bool
    {
        $this->checkFault('suspendInstance');
        if (!isset($this->instances[$instanceId])) {
            throw new RuntimeException("Instance not found: {$instanceId}");
        }

        $this->instances[$instanceId]['state'] = 'SUSPENDED';
        $this->instances[$instanceId]['updated_at'] = date('Y-m-d H:i:s');
        $this->logAction('suspendInstance', $instanceId);
        return true;
    }

    public function resumeInstance(string $instanceId): bool
    {
        $this->checkFault('resumeInstance');
        if (!isset($this->instances[$instanceId])) {
            throw new RuntimeException("Instance not found: {$instanceId}");
        }

        $this->instances[$instanceId]['state'] = 'RUNNING';
        $this->instances[$instanceId]['updated_at'] = date('Y-m-d H:i:s');
        $this->logAction('resumeInstance', $instanceId);
        return true;
    }

    public function destroyInstance(string $instanceId): bool
    {
        $this->checkFault('destroyInstance');
        if (!isset($this->instances[$instanceId])) {
            throw new RuntimeException("Instance not found: {$instanceId}");
        }

        unset($this->instances[$instanceId], $this->cgroups[$instanceId]);
        $this->snapshots = array_filter($this->snapshots, fn($s) => $s['instance_id'] !== $instanceId);
        $this->logAction('destroyInstance', $instanceId);
        return true;
    }

    public function getInstance(string $instanceId): ?array
    {
        return $this->instances[$instanceId] ?? null;
    }

    public function listInstances(): array
    {
        return array_values($this->instances);
    }

    public function getInstanceStats(string $instanceId): array
    {
        if (!isset($this->instances[$instanceId])) {
            throw new RuntimeException("Instance not found: {$instanceId}");
        }

        $inst = $this->instances[$instanceId];
        $isRunning = $inst['state'] === 'RUNNING';

        return [
            'instance_id' => $instanceId,
            'state' => $inst['state'],
            'cpu_usage_pct' => $isRunning ? 18.75 : 0.0,
            'ram_used_bytes' => $isRunning ? (int)($inst['ram_mb'] * 1024 * 1024 * 0.35) : 0,
            'ram_total_bytes' => (int)($inst['ram_mb'] * 1024 * 1024),
            'disk_read_bytes_sec' => $isRunning ? 1048576 : 0,
            'disk_write_bytes_sec' => $isRunning ? 524288 : 0,
            'net_rx_bytes_sec' => $isRunning ? 204800 : 0,
            'net_tx_bytes_sec' => $isRunning ? 102400 : 0,
            'pids_count' => $isRunning ? 42 : 0,
        ];
    }

    public function setCgroupLimits(string $instanceId, array $limits): bool
    {
        $this->checkFault('setCgroupLimits');
        if (!isset($this->instances[$instanceId])) {
            throw new RuntimeException("Instance not found: {$instanceId}");
        }

        $this->cgroups[$instanceId] = array_merge($this->cgroups[$instanceId] ?? [], $limits);
        return true;
    }

    public function getCgroupLimits(string $instanceId): array
    {
        return $this->cgroups[$instanceId] ?? [];
    }

    public function createSnapshot(string $instanceId, string $snapshotName): string
    {
        $this->checkFault('createSnapshot');
        if (!isset($this->instances[$instanceId])) {
            throw new RuntimeException("Instance not found: {$instanceId}");
        }

        $snapId = 'snap-' . bin2hex(random_bytes(4));
        $this->snapshots[$snapId] = [
            'id' => $snapId,
            'instance_id' => $instanceId,
            'name' => $snapshotName,
            'state' => $this->instances[$instanceId]['state'],
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $this->logAction('createSnapshot', $instanceId, ['snapshot_id' => $snapId, 'name' => $snapshotName]);
        return $snapId;
    }

    public function rollbackSnapshot(string $instanceId, string $snapshotId): bool
    {
        $this->checkFault('rollbackSnapshot');
        if (!isset($this->snapshots[$snapshotId]) || $this->snapshots[$snapshotId]['instance_id'] !== $instanceId) {
            throw new RuntimeException("Snapshot not found for instance: {$snapshotId}");
        }

        $this->instances[$instanceId]['state'] = $this->snapshots[$snapshotId]['state'];
        $this->logAction('rollbackSnapshot', $instanceId, ['snapshot_id' => $snapshotId]);
        return true;
    }

    public function deleteSnapshot(string $snapshotId): bool
    {
        $this->checkFault('deleteSnapshot');
        if (!isset($this->snapshots[$snapshotId])) {
            throw new RuntimeException("Snapshot not found: {$snapshotId}");
        }

        unset($this->snapshots[$snapshotId]);
        return true;
    }

    public function listSnapshots(string $instanceId): array
    {
        return array_values(array_filter($this->snapshots, fn($s) => $s['instance_id'] === $instanceId));
    }

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

    private function logAction(string $action, string $instanceId, array $data = []): void
    {
        $this->executionLogs[] = [
            'action' => $action,
            'instance_id' => $instanceId,
            'data' => $data,
            'timestamp' => microtime(true),
        ];
    }
}
