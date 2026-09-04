<?php
declare(strict_types=1);

namespace Oshim\Virtualization\Storage;

use Oshim\Virtualization\Exceptions\StorageException;

/**
 * Storage quota calculator and disk capacity management.
 */
class StorageQuotaManager
{
    private OverlayFsManager $overlayManager;

    public function __construct(?OverlayFsManager $overlayManager = null)
    {
        $this->overlayManager = $overlayManager ?? new OverlayFsManager();
    }

    /**
     * Calculate current storage consumption of a container's writable diff layer.
     */
    public function getContainerDiskUsage(string $instanceId): int
    {
        $instDir = $this->overlayManager->getInstancePath($instanceId);
        $upperDir = "{$instDir}/upper";
        return $this->overlayManager->getDirectorySize($upperDir);
    }

    /**
     * Check whether an instance has exceeded its configured storage limit.
     */
    public function checkQuota(string $instanceId, int $limitBytes): bool
    {
        if ($limitBytes <= 0) {
            return true;
        }

        $used = $this->getContainerDiskUsage($instanceId);
        if ($used > $limitBytes) {
            throw new StorageException("Container {$instanceId} exceeded disk quota: {$used} bytes used of {$limitBytes} limit.");
        }

        return true;
    }

    /**
     * Get disk usage statistics for an instance.
     *
     * @return array{used_bytes: int, limit_bytes: int, usage_pct: float, is_exceeded: bool}
     */
    public function getQuotaStats(string $instanceId, int $limitBytes): array
    {
        $used = $this->getContainerDiskUsage($instanceId);
        $pct = $limitBytes > 0 ? min(100.0, round(($used / $limitBytes) * 100.0, 2)) : 0.0;

        return [
            'used_bytes'  => $used,
            'limit_bytes' => $limitBytes,
            'usage_pct'   => $pct,
            'is_exceeded' => $limitBytes > 0 && $used > $limitBytes,
        ];
    }
}
