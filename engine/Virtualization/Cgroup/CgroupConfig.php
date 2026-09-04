<?php
declare(strict_types=1);

namespace Oshim\Virtualization\Cgroup;

/**
 * Cgroups v2 resource quotas and limit configuration.
 */
final class CgroupConfig
{
    public function __construct(
        public readonly ?float $cpuCores = null,        // e.g. 2.0
        public readonly int $cpuWeight = 100,           // 1..10000
        public readonly ?int $memoryMaxBytes = null,    // e.g. 2147483648 (2GB)
        public readonly ?int $memoryHighBytes = null,   // Soft throttle limit
        public readonly ?int $memoryLowBytes = null,    // Guaranteed reservation
        public readonly ?int $memorySwapMaxBytes = 0,   // Swap cap (0 = disabled)
        public readonly ?int $pidsMax = 512,            // Max process/thread count
        /** @var array<string, array<string, int>> */
        public readonly array $ioLimits = []            // ['8:0' => ['rbps' => 52428800, 'wbps' => 52428800]]
    ) {}

    public static function fromArray(array $data): self
    {
        $cpuCores = null;
        if (isset($data['cpu_cores'])) {
            $cpuCores = (float)$data['cpu_cores'];
        } elseif (isset($data['cpu_limit'])) {
            $cpuCores = (float)$data['cpu_limit'];
        } elseif (isset($data['vcpu'])) {
            $cpuCores = (float)$data['vcpu'];
        }

        $cpuWeight = (int)($data['cpu_weight'] ?? 100);

        $memoryMaxBytes = null;
        if (isset($data['memory_max_bytes'])) {
            $memoryMaxBytes = (int)$data['memory_max_bytes'];
        } elseif (isset($data['memory_limit_bytes'])) {
            $memoryMaxBytes = (int)$data['memory_limit_bytes'];
        } elseif (isset($data['ram_mb'])) {
            $memoryMaxBytes = (int)$data['ram_mb'] * 1024 * 1024;
        }

        $memoryHighBytes = isset($data['memory_high_bytes'])
            ? (int)$data['memory_high_bytes']
            : ($memoryMaxBytes !== null ? (int)($memoryMaxBytes * 0.875) : null);

        $memoryLowBytes = isset($data['memory_low_bytes']) ? (int)$data['memory_low_bytes'] : null;
        $memorySwapMaxBytes = isset($data['memory_swap_max_bytes']) ? (int)$data['memory_swap_max_bytes'] : 0;
        $pidsMax = isset($data['pids_max']) ? (int)$data['pids_max'] : (isset($data['pids_limit']) ? (int)$data['pids_limit'] : 512);
        $ioLimits = (array)($data['io_limits'] ?? []);

        return new self(
            cpuCores: $cpuCores,
            cpuWeight: $cpuWeight,
            memoryMaxBytes: $memoryMaxBytes,
            memoryHighBytes: $memoryHighBytes,
            memoryLowBytes: $memoryLowBytes,
            memorySwapMaxBytes: $memorySwapMaxBytes,
            pidsMax: $pidsMax,
            ioLimits: $ioLimits
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'cpu_cores'              => $this->cpuCores,
            'cpu_weight'             => $this->cpuWeight,
            'memory_max_bytes'       => $this->memoryMaxBytes,
            'memory_high_bytes'      => $this->memoryHighBytes,
            'memory_low_bytes'       => $this->memoryLowBytes,
            'memory_swap_max_bytes'  => $this->memorySwapMaxBytes,
            'pids_max'               => $this->pidsMax,
            'io_limits'              => $this->ioLimits,
        ];
    }
}
