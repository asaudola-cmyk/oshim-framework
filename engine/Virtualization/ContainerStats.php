<?php
declare(strict_types=1);

namespace Oshim\Virtualization;

/**
 * Real-time telemetry and resource consumption statistics for a container.
 */
final class ContainerStats
{
    public function __construct(
        public readonly string $instanceId,
        public readonly string $state = ContainerState::STOPPED,
        public readonly float $cpuUsagePct = 0.0,
        public readonly int $cpuTimeUsec = 0,
        public readonly int $cpuUserUsec = 0,
        public readonly int $cpuSystemUsec = 0,
        public readonly int $memoryUsageBytes = 0,
        public readonly int $memoryLimitBytes = 0,
        public readonly float $memoryUsagePct = 0.0,
        public readonly int $memoryAnonBytes = 0,
        public readonly int $memoryFileBytes = 0,
        public readonly int $memoryOomCount = 0,
        public readonly int $diskReadBytes = 0,
        public readonly int $diskWriteBytes = 0,
        public readonly int $diskReadIops = 0,
        public readonly int $diskWriteIops = 0,
        public readonly int $diskReadBytesSec = 0,
        public readonly int $diskWriteBytesSec = 0,
        public readonly int $netRxBytes = 0,
        public readonly int $netTxBytes = 0,
        public readonly int $netRxPackets = 0,
        public readonly int $netTxPackets = 0,
        public readonly int $netRxBytesSec = 0,
        public readonly int $netTxBytesSec = 0,
        public readonly int $pidsCount = 0,
        public readonly bool $isFrozen = false,
        public readonly float $timestamp = 0.0
    ) {}

    public function getInstanceId(): string { return $this->instanceId; }
    public function getState(): string { return $this->state; }
    public function getCpuUsagePct(): float { return $this->cpuUsagePct; }
    public function getCpuTimeUsec(): int { return $this->cpuTimeUsec; }
    public function getMemoryUsageBytes(): int { return $this->memoryUsageBytes; }
    public function getMemoryLimitBytes(): int { return $this->memoryLimitBytes; }
    public function getMemoryUsagePct(): float { return $this->memoryUsagePct; }
    public function getDiskReadBytes(): int { return $this->diskReadBytes; }
    public function getDiskWriteBytes(): int { return $this->diskWriteBytes; }
    public function getNetRxBytes(): int { return $this->netRxBytes; }
    public function getNetTxBytes(): int { return $this->netTxBytes; }
    public function getPidsCount(): int { return $this->pidsCount; }
    public function isFrozen(): bool { return $this->isFrozen; }
    public function getTimestamp(): float { return $this->timestamp; }

    /**
     * Export telemetry to associative array with dual modern and legacy key bindings.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'instance_id'          => $this->instanceId,
            'state'                => $this->state,
            'status'               => $this->state,
            'cpu_usage_pct'        => round($this->cpuUsagePct, 2),
            'cpu_usage_percent'    => round($this->cpuUsagePct, 2),
            'cpu_time_usec'        => $this->cpuTimeUsec,
            'cpu_usage_usec'       => $this->cpuTimeUsec,
            'cpu_user_usec'        => $this->cpuUserUsec,
            'cpu_system_usec'      => $this->cpuSystemUsec,
            'ram_used_bytes'       => $this->memoryUsageBytes,
            'memory_used_bytes'    => $this->memoryUsageBytes,
            'memory_usage_bytes'   => $this->memoryUsageBytes,
            'ram_total_bytes'      => $this->memoryLimitBytes,
            'memory_limit_bytes'   => $this->memoryLimitBytes,
            'memory_usage_pct'     => round($this->memoryUsagePct, 2),
            'memory_usage_percent' => round($this->memoryUsagePct, 2),
            'memory_anon_bytes'    => $this->memoryAnonBytes,
            'memory_file_bytes'    => $this->memoryFileBytes,
            'memory_oom_count'     => $this->memoryOomCount,
            'disk_read_bytes'      => $this->diskReadBytes,
            'disk_write_bytes'     => $this->diskWriteBytes,
            'disk_read_iops'       => $this->diskReadIops,
            'disk_write_iops'      => $this->diskWriteIops,
            'disk_read_bytes_sec'  => $this->diskReadBytesSec,
            'disk_write_bytes_sec' => $this->diskWriteBytesSec,
            'net_rx_bytes'         => $this->netRxBytes,
            'net_tx_bytes'         => $this->netTxBytes,
            'network_rx_bytes'     => $this->netRxBytes,
            'network_tx_bytes'     => $this->netTxBytes,
            'net_rx_packets'       => $this->netRxPackets,
            'net_tx_packets'       => $this->netTxPackets,
            'net_rx_bytes_sec'     => $this->netRxBytesSec,
            'net_tx_bytes_sec'     => $this->netTxBytesSec,
            'pids_count'           => $this->pidsCount,
            'pids_current'         => $this->pidsCount,
            'is_frozen'            => $this->isFrozen,
            'timestamp'            => $this->timestamp > 0 ? $this->timestamp : microtime(true),
        ];
    }
}
