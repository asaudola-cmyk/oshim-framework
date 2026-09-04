<?php
declare(strict_types=1);

namespace Oshim\Tests\Harness;

class SuperAdminClusterController
{
    private array $nodes = [];

    public function registerNode(string $nodeId, string $region, int $totalCpuCores, int $totalRamGb): void
    {
        $this->nodes[$nodeId] = [
            'id' => $nodeId,
            'region' => $region,
            'cpu_cores' => $totalCpuCores,
            'ram_gb' => $totalRamGb,
            'status' => 'ONLINE',
            'instances' => [],
        ];
    }

    public function allocateInstance(string $nodeId, string $instanceId, int $vcpu, int $ramMb): bool
    {
        if (!isset($this->nodes[$nodeId]) || $this->nodes[$nodeId]['status'] !== 'ONLINE') {
            return false;
        }

        $this->nodes[$nodeId]['instances'][$instanceId] = [
            'id' => $instanceId,
            'vcpu' => $vcpu,
            'ram_mb' => $ramMb,
        ];
        return true;
    }

    public function drainNode(string $nodeId): array
    {
        if (!isset($this->nodes[$nodeId])) {
            return [];
        }

        $this->nodes[$nodeId]['status'] = 'DRAINING';
        $instancesToMigrate = array_keys($this->nodes[$nodeId]['instances']);
        return $instancesToMigrate;
    }

    public function getNode(string $nodeId): ?array
    {
        return $this->nodes[$nodeId] ?? null;
    }
}
