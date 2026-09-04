<?php
declare(strict_types=1);

namespace Oshim\Virtualization\Storage;

use Oshim\Virtualization\Exceptions\SnapshotException;

/**
 * Copy-on-Write Snapshot manager for container rootfs diff layers.
 */
class SnapshotManager
{
    private string $storageRoot;
    private OverlayFsManager $overlayManager;

    public function __construct(string $storageRoot = '/var/lib/oshim', ?OverlayFsManager $overlayManager = null)
    {
        $this->storageRoot = rtrim($storageRoot, '/');
        $this->overlayManager = $overlayManager ?? new OverlayFsManager($this->storageRoot);
    }

    public function getOverlayManager(): OverlayFsManager
    {
        return $this->overlayManager;
    }

    /**
     * Create a point-in-time snapshot of the container's writable upper layer.
     */
    public function createSnapshot(string $instanceId, string $snapshotName, string $description = ''): SnapshotMetadata
    {
        $snapId = 'snap_' . $instanceId . '_' . time() . '_' . bin2hex(random_bytes(3));
        $instDir = $this->overlayManager->getInstancePath($instanceId);
        $upperDir = "{$instDir}/upper";
        $workDir = "{$instDir}/work";

        $snapDir = "{$this->storageRoot}/snapshots/{$instanceId}/{$snapId}";
        $snapLayer = "{$snapDir}/layer";

        if (!is_dir($snapLayer)) {
            if (!@mkdir($snapLayer, 0755, true) && !is_dir($snapLayer)) {
                throw new SnapshotException("Failed to create snapshot directory: {$snapDir}");
            }
        }

        $wasMounted = $this->overlayManager->isMounted($instanceId);
        if ($wasMounted) {
            $this->overlayManager->unmountOverlay($instanceId, true);
        }

        // Copy current upper layer to snapshot layer
        $this->overlayManager->copyDirectory($upperDir, $snapLayer);

        // Reset upper and work directories for new writes
        $this->overlayManager->recursiveRmdir($upperDir);
        $this->overlayManager->recursiveRmdir($workDir);
        @mkdir($upperDir, 0755, true);
        @mkdir($workDir, 0755, true);

        // Update instance lower layer stack
        $metaFile = "{$instDir}/metadata.json";
        $meta = file_exists($metaFile) ? json_decode((string)file_get_contents($metaFile), true) : [];
        $lowers = (array)($meta['lower_dirs'] ?? []);
        array_unshift($lowers, $snapLayer);
        $meta['lower_dirs'] = array_values(array_unique($lowers));
        $meta['active_snapshot_id'] = $snapId;
        file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT));

        if ($wasMounted) {
            $this->overlayManager->mountOverlay($instanceId, $meta['lower_dirs']);
        }

        $size = $this->overlayManager->getDirectorySize($snapLayer);

        $metadata = new SnapshotMetadata(
            id: $snapId,
            instanceId: $instanceId,
            name: $snapshotName,
            description: $description,
            layerPath: $snapLayer,
            sizeBytes: $size,
            createdAt: time(),
            layerStack: $meta['lower_dirs']
        );

        file_put_contents("{$snapDir}/snapshot.json", json_encode($metadata->toArray(), JSON_PRETTY_PRINT));

        return $metadata;
    }

    /**
     * Rollback a container to a previously saved snapshot.
     */
    public function rollbackSnapshot(string $instanceId, string $snapshotId): bool
    {
        $snapMeta = $this->getSnapshot($instanceId, $snapshotId);
        if ($snapMeta === null) {
            throw new SnapshotException("Snapshot '{$snapshotId}' not found for instance '{$instanceId}'");
        }

        $targetLayers = $snapMeta->layerStack;
        $instDir = $this->overlayManager->getInstancePath($instanceId);
        $upperDir = "{$instDir}/upper";
        $workDir = "{$instDir}/work";

        $wasMounted = $this->overlayManager->isMounted($instanceId);
        if ($wasMounted) {
            $this->overlayManager->unmountOverlay($instanceId, true);
        }

        // Purge current uncommitted changes
        $this->overlayManager->recursiveRmdir($upperDir);
        $this->overlayManager->recursiveRmdir($workDir);
        @mkdir($upperDir, 0755, true);
        @mkdir($workDir, 0755, true);

        // Update instance metadata
        $metaFile = "{$instDir}/metadata.json";
        $meta = file_exists($metaFile) ? json_decode((string)file_get_contents($metaFile), true) : [];
        $meta['lower_dirs'] = $targetLayers;
        $meta['active_snapshot_id'] = $snapshotId;
        file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT));

        if ($wasMounted) {
            $this->overlayManager->mountOverlay($instanceId, $targetLayers);
        }

        return true;
    }

    /**
     * Delete an existing snapshot.
     */
    public function deleteSnapshot(string $instanceId, string $snapshotId): bool
    {
        $snapDir = "{$this->storageRoot}/snapshots/{$instanceId}/{$snapshotId}";
        if (!is_dir($snapDir)) {
            // Check across all containers if instanceId is unknown
            $foundDir = null;
            $allSnaps = glob("{$this->storageRoot}/snapshots/*/{$snapshotId}");
            if (!empty($allSnaps)) {
                $foundDir = $allSnaps[0];
            }

            if ($foundDir === null || !is_dir($foundDir)) {
                throw new SnapshotException("Snapshot '{$snapshotId}' does not exist.");
            }
            $snapDir = $foundDir;
        }

        $this->overlayManager->recursiveRmdir($snapDir);
        return true;
    }

    /**
     * List all snapshots for a given container.
     *
     * @return list<SnapshotMetadata>
     */
    public function listSnapshots(string $instanceId): array
    {
        $instanceSnapsDir = "{$this->storageRoot}/snapshots/{$instanceId}";
        if (!is_dir($instanceSnapsDir)) {
            return [];
        }

        $snapshots = [];
        $entries = @scandir($instanceSnapsDir);
        if ($entries) {
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $metaFile = "{$instanceSnapsDir}/{$entry}/snapshot.json";
                if (file_exists($metaFile)) {
                    $data = json_decode((string)file_get_contents($metaFile), true);
                    if (is_array($data)) {
                        $snapshots[] = SnapshotMetadata::fromArray($data);
                    }
                }
            }
        }

        usort($snapshots, function (SnapshotMetadata $a, SnapshotMetadata $b) {
            if ($b->createdAt !== $a->createdAt) {
                return $b->createdAt <=> $a->createdAt;
            }
            return strcmp($b->id, $a->id);
        });
        return array_values($snapshots);
    }

    /**
     * Find a snapshot by ID.
     */
    public function getSnapshot(string $instanceId, string $snapshotId): ?SnapshotMetadata
    {
        $metaFile = "{$this->storageRoot}/snapshots/{$instanceId}/{$snapshotId}/snapshot.json";
        if (!file_exists($metaFile)) {
            // Search across all containers
            $matches = glob("{$this->storageRoot}/snapshots/*/{$snapshotId}/snapshot.json");
            if (!empty($matches)) {
                $metaFile = $matches[0];
            } else {
                return null;
            }
        }

        $data = json_decode((string)file_get_contents($metaFile), true);
        return is_array($data) ? SnapshotMetadata::fromArray($data) : null;
    }
}
