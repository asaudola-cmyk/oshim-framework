<?php
declare(strict_types=1);

namespace Oshim\Virtualization\Cgroup;

/**
 * Parsed real-time Cgroups v2 resource metrics and events.
 */
final class CgroupTelemetry
{
    public function __construct(
        public readonly float $cpuUsagePercent = 0.0,
        public readonly int $cpuUsageUsec = 0,
        public readonly int $cpuUserUsec = 0,
        public readonly int $cpuSystemUsec = 0,
        public readonly int $cpuNrThrottled = 0,
        public readonly int $cpuThrottledUsec = 0,
        public readonly int $memoryCurrentBytes = 0,
        public readonly int $memoryMaxBytes = 0,
        public readonly float $memoryUsagePercent = 0.0,
        public readonly int $memoryAnonBytes = 0,
        public readonly int $memoryFileBytes = 0,
        public readonly int $memoryOomCount = 0,
        public readonly int $pidsCurrent = 0,
        public readonly int $pidsMax = 0,
        public readonly int $ioReadBytes = 0,
        public readonly int $ioWriteBytes = 0,
        public readonly int $ioReadOps = 0,
        public readonly int $ioWriteOps = 0,
        public readonly bool $isFrozen = false,
        public readonly bool $isPopulated = false,
        public readonly float $timestamp = 0.0
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'cpu_usage_pct'       => round($this->cpuUsagePercent, 2),
            'cpu_usage_usec'      => $this->cpuUsageUsec,
            'cpu_user_usec'       => $this->cpuUserUsec,
            'cpu_system_usec'     => $this->cpuSystemUsec,
            'cpu_nr_throttled'    => $this->cpuNrThrottled,
            'cpu_throttled_usec'  => $this->cpuThrottledUsec,
            'memory_usage_bytes'  => $this->memoryCurrentBytes,
            'memory_limit_bytes'  => $this->memoryMaxBytes,
            'memory_usage_pct'    => round($this->memoryUsagePercent, 2),
            'memory_anon_bytes'   => $this->memoryAnonBytes,
            'memory_file_bytes'   => $this->memoryFileBytes,
            'memory_oom_count'    => $this->memoryOomCount,
            'pids_count'          => $this->pidsCurrent,
            'pids_max'            => $this->pidsMax,
            'io_read_bytes'       => $this->ioReadBytes,
            'io_write_bytes'      => $this->ioWriteBytes,
            'io_read_ops'         => $this->ioReadOps,
            'io_write_ops'        => $this->ioWriteOps,
            'is_frozen'           => $this->isFrozen,
            'is_populated'        => $this->isPopulated,
            'timestamp'           => $this->timestamp > 0 ? $this->timestamp : microtime(true),
        ];
    }
}
