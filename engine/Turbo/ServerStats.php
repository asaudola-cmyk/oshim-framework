<?php
declare(strict_types=1);

namespace Oshim\Turbo;

/**
 * High-performance telemetry container tracking reactor server metrics in real-time.
 */
class ServerStats
{
    private float $startedAt;
    private int $totalRequests = 0;
    private int $activeConnections = 0;
    private int $totalConnectionsAccepted = 0;
    private int $totalBytesRead = 0;
    private int $totalBytesSent = 0;
    private array $statusCodes = [];
    private int|string $workerId;

    public function __construct(int|string $workerId = 0)
    {
        $this->workerId = $workerId;
        $this->startedAt = microtime(true);
    }

    public function recordRequest(int $statusCode = 200, int $bytesRead = 0, int $bytesSent = 0): void
    {
        $this->totalRequests++;
        $this->recordStatusCode($statusCode);
        if ($bytesRead > 0) {
            $this->totalBytesRead += $bytesRead;
        }
        if ($bytesSent > 0) {
            $this->totalBytesSent += $bytesSent;
        }
    }

    public function incrementActiveConnections(): void
    {
        $this->activeConnections++;
        $this->totalConnectionsAccepted++;
    }

    public function decrementActiveConnections(): void
    {
        $this->activeConnections = max(0, $this->activeConnections - 1);
    }

    public function recordConnectionAccepted(): void
    {
        $this->totalConnectionsAccepted++;
    }

    public function recordBytesRead(int $bytes): void
    {
        if ($bytes > 0) {
            $this->totalBytesRead += $bytes;
        }
    }

    public function recordBytesSent(int $bytes): void
    {
        if ($bytes > 0) {
            $this->totalBytesSent += $bytes;
        }
    }

    public function recordStatusCode(int $code): void
    {
        $this->statusCodes[$code] = ($this->statusCodes[$code] ?? 0) + 1;
    }

    public function getUptime(): float
    {
        return max(0.0001, microtime(true) - $this->startedAt);
    }

    public function getCurrentRps(): float
    {
        $uptime = $this->getUptime();
        return round($this->totalRequests / $uptime, 2);
    }

    public function getStartedAt(): float
    {
        return $this->startedAt;
    }

    public function getTotalRequests(): int
    {
        return $this->totalRequests;
    }

    public function getActiveConnections(): int
    {
        return $this->activeConnections;
    }

    public function getTotalConnectionsAccepted(): int
    {
        return $this->totalConnectionsAccepted;
    }

    public function getTotalBytesRead(): int
    {
        return $this->totalBytesRead;
    }

    public function getTotalBytesSent(): int
    {
        return $this->totalBytesSent;
    }

    public function getStatusCodes(): array
    {
        return $this->statusCodes;
    }

    public function getWorkerId(): int|string
    {
        return $this->workerId;
    }

    public function reset(): void
    {
        $this->startedAt = microtime(true);
        $this->totalRequests = 0;
        $this->activeConnections = 0;
        $this->totalConnectionsAccepted = 0;
        $this->totalBytesRead = 0;
        $this->totalBytesSent = 0;
        $this->statusCodes = [];
    }

    public function toArray(): array
    {
        return [
            'worker_id' => $this->workerId,
            'uptime_seconds' => round($this->getUptime(), 4),
            'total_requests' => $this->totalRequests,
            'active_connections' => $this->activeConnections,
            'total_connections_accepted' => $this->totalConnectionsAccepted,
            'total_bytes_read' => $this->totalBytesRead,
            'total_bytes_sent' => $this->totalBytesSent,
            'current_rps' => $this->getCurrentRps(),
            'status_codes' => $this->statusCodes,
            'peak_memory_bytes' => memory_get_peak_usage(true),
            'current_memory_bytes' => memory_get_usage(true),
        ];
    }
}
