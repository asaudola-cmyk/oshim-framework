<?php
declare(strict_types=1);

namespace Oshim\Swarm;

class SwarmNode
{
    public function __construct(
        public string $nodeId,
        public string $host,
        public int $port,
        public string $role = 'worker', // 'leader' | 'worker' | 'candidate'
        public int $cpuCores = 1,
        public int $memoryTotalMb = 1024,
        public int $memoryUsedMb = 0,
        public string $status = 'HEALTHY', // 'HEALTHY' | 'DRAINING' | 'UNREACHABLE'
        public float $lastHeartbeat = 0.0,
        public int $activeConnections = 0,
        public array $tags = [],
        public int $weight = 100
    ) {
        if ($this->lastHeartbeat <= 0.0) {
            $this->lastHeartbeat = microtime(true);
        }
    }

    public function isAlive(float $timeoutSeconds = 5.0): bool
    {
        if ($this->status === 'UNREACHABLE') {
            return false;
        }

        return (microtime(true) - $this->lastHeartbeat) <= $timeoutSeconds;
    }

    public function recordHeartbeat(int $memUsedMb = 0, int $activeConns = 0): void
    {
        $this->lastHeartbeat = microtime(true);
        $this->memoryUsedMb = $memUsedMb;
        $this->activeConnections = $activeConns;
        $this->status = 'HEALTHY';
    }

    public function markUnreachable(): void
    {
        $this->status = 'UNREACHABLE';
    }

    public function markDraining(): void
    {
        $this->status = 'DRAINING';
    }

    public function getEndpoint(): string
    {
        return "{$this->host}:{$this->port}";
    }

    public function toArray(): array
    {
        return [
            'node_id' => $this->nodeId,
            'host' => $this->host,
            'port' => $this->port,
            'role' => $this->role,
            'cpu_cores' => $this->cpuCores,
            'memory_total_mb' => $this->memoryTotalMb,
            'memory_used_mb' => $this->memoryUsedMb,
            'status' => $this->status,
            'last_heartbeat' => $this->lastHeartbeat,
            'active_connections' => $this->activeConnections,
            'tags' => $this->tags,
            'weight' => $this->weight,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            nodeId: (string)($data['node_id'] ?? uniqid('node_')),
            host: (string)($data['host'] ?? '127.0.0.1'),
            port: (int)($data['port'] ?? 9500),
            role: (string)($data['role'] ?? 'worker'),
            cpuCores: (int)($data['cpu_cores'] ?? 1),
            memoryTotalMb: (int)($data['memory_total_mb'] ?? 1024),
            memoryUsedMb: (int)($data['memory_used_mb'] ?? 0),
            status: (string)($data['status'] ?? 'HEALTHY'),
            lastHeartbeat: (float)($data['last_heartbeat'] ?? microtime(true)),
            activeConnections: (int)($data['active_connections'] ?? 0),
            tags: (array)($data['tags'] ?? []),
            weight: (int)($data['weight'] ?? 100)
        );
    }
}
