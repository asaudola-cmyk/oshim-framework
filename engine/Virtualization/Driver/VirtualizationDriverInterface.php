<?php
declare(strict_types=1);

namespace Oshim\Virtualization\Driver;

use Oshim\Virtualization\Container;
use Oshim\Virtualization\ContainerConfig;
use Oshim\Virtualization\ContainerStats;
use Oshim\Virtualization\ExecResult;
use Oshim\Virtualization\Storage\SnapshotMetadata;

/**
 * Unified interface contract for virtualization drivers (Native Linux Kernel & Mock Simulation).
 */
interface VirtualizationDriverInterface
{
    // --- Modern Typed DTO Lifecycle Methods ---

    /**
     * Create a new container from typed configuration.
     */
    public function create(ContainerConfig $config): Container;

    /**
     * Start a created or stopped container.
     */
    public function start(string $id): bool;

    /**
     * Gracefully stop a running container.
     */
    public function stop(string $id, int $timeout = 10, bool $force = false): bool;

    /**
     * Pause execution of a running container via Cgroups v2 freezer.
     */
    public function pause(string $id): bool;

    /**
     * Resume execution of a paused container.
     */
    public function resume(string $id): bool;

    /**
     * Restart a container (stop + start).
     */
    public function restart(string $id, int $timeout = 10): bool;

    /**
     * Destroy a container and clean up its cgroups, network, and storage.
     */
    public function destroy(string $id): bool;

    /**
     * Retrieve live telemetry and hardware metrics for a container.
     */
    public function stats(string $id): ContainerStats;

    /**
     * Execute a command inside the isolated container namespaces.
     *
     * @param list<string> $command
     * @param array<string, string> $env
     */
    public function exec(string $id, array $command, array $env = [], int $timeout = 30): ExecResult;

    /**
     * Create a point-in-time snapshot of the container's storage diff.
     */
    public function createSnapshot(string $id, string $snapshotName): string;

    /**
     * Rollback a container to a previously saved snapshot.
     */
    public function rollbackSnapshot(string $id, string $snapshotId): bool;

    /**
     * Delete an existing snapshot.
     */
    public function deleteSnapshot(string $id, string $snapshotId): bool;

    /**
     * List all snapshots for a given container.
     *
     * @return list<SnapshotMetadata|array<string, mixed>>
     */
    public function listSnapshots(string $id): array;

    /**
     * Find a container by ID.
     */
    public function getContainer(string $id): ?Container;

    /**
     * List all registered containers.
     *
     * @return list<Container>
     */
    public function listContainers(): array;

    // --- Legacy / Facade Bridge Methods (for PROJECT.md & Portal compatibility) ---

    /**
     * Create container from associative spec array and return instance ID.
     *
     * @param array<string, mixed> $spec
     */
    public function createInstance(array $spec): string;

    public function startInstance(string $instanceId): bool;

    public function stopInstance(string $instanceId, bool $force = false): bool;

    public function restartInstance(string $instanceId): bool;

    public function suspendInstance(string $instanceId): bool;

    public function resumeInstance(string $instanceId): bool;

    public function destroyInstance(string $instanceId): bool;

    /**
     * @return array<string, mixed>|null
     */
    public function getInstance(string $instanceId): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listInstances(): array;

    /**
     * @return array<string, mixed>
     */
    public function getInstanceStats(string $instanceId): array;
}
