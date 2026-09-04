<?php
declare(strict_types=1);

namespace Oshim\Swarm;

use RuntimeException;

class SwarmLoadBalancer
{
    private int $roundRobinIndex = 0;

    /**
     * Select best node based on strategy.
     * Strategies: 'round_robin', 'least_conn', 'weighted_cpu'
     *
     * @param SwarmNode[] $nodes
     * @param string $strategy
     * @return SwarmNode
     * @throws RuntimeException
     */
    public function selectNode(array $nodes, string $strategy = 'round_robin'): SwarmNode
    {
        $healthyNodes = array_values(array_filter(
            $nodes,
            fn(SwarmNode $n) => $n->status === 'HEALTHY' && $n->isAlive()
        ));

        if (empty($healthyNodes)) {
            throw new RuntimeException("No healthy Swarm nodes available for routing");
        }

        return match ($strategy) {
            'least_conn' => $this->selectLeastConnections($healthyNodes),
            'weighted_cpu' => $this->selectWeightedCpu($healthyNodes),
            default => $this->selectRoundRobin($healthyNodes),
        };
    }

    /**
     * @param SwarmNode[] $nodes
     * @return SwarmNode
     */
    private function selectRoundRobin(array $nodes): SwarmNode
    {
        $count = count($nodes);
        $node = $nodes[$this->roundRobinIndex % $count];
        $this->roundRobinIndex++;
        return $node;
    }

    /**
     * @param SwarmNode[] $nodes
     * @return SwarmNode
     */
    private function selectLeastConnections(array $nodes): SwarmNode
    {
        usort($nodes, fn(SwarmNode $a, SwarmNode $b) => $a->activeConnections <=> $b->activeConnections);
        return $nodes[0];
    }

    /**
     * @param SwarmNode[] $nodes
     * @return SwarmNode
     */
    private function selectWeightedCpu(array $nodes): SwarmNode
    {
        // Score = (memoryUsed / memoryTotal) / max(1, cpuCores * (weight / 100))
        // Lower score represents higher available relative capacity
        usort($nodes, function (SwarmNode $a, SwarmNode $b) {
            $memRatioA = $a->memoryTotalMb > 0 ? ($a->memoryUsedMb / $a->memoryTotalMb) : 1.0;
            $memRatioB = $b->memoryTotalMb > 0 ? ($b->memoryUsedMb / $b->memoryTotalMb) : 1.0;

            $capacityA = max(1.0, (float)$a->cpuCores * ((float)max(1, $a->weight) / 100.0));
            $capacityB = max(1.0, (float)$b->cpuCores * ((float)max(1, $b->weight) / 100.0));

            $scoreA = $memRatioA / $capacityA;
            $scoreB = $memRatioB / $capacityB;

            if (abs($scoreA - $scoreB) < 0.00001) {
                return $a->activeConnections <=> $b->activeConnections;
            }

            return $scoreA <=> $scoreB;
        });

        return $nodes[0];
    }
}
