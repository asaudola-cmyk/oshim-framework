<?php
declare(strict_types=1);

namespace Oshim\Swarm;

use RuntimeException;

class SwarmCluster
{
    private SwarmNode $localNode;
    /** @var array<string, SwarmNode> */
    private array $peers = [];
    private string $clusterSecret;
    private DistributedStateSync $stateSync;
    private SwarmLoadBalancer $loadBalancer;
    private bool $isLeader = false;

    public function __construct(
        ?SwarmNode $localNode = null,
        string $clusterSecret = 'oshim_sovereign_swarm_secret'
    ) {
        $this->localNode = $localNode ?? new SwarmNode(
            nodeId: uniqid('node_'),
            host: '127.0.0.1',
            port: 9500,
            role: 'leader',
            cpuCores: (int)(getenv('CPU_CORES') ?: 4),
            memoryTotalMb: 4096
        );

        $this->clusterSecret = $clusterSecret;
        $this->stateSync = new DistributedStateSync();
        $this->loadBalancer = new SwarmLoadBalancer();
        $this->isLeader = ($this->localNode->role === 'leader');
    }

    public function getLocalNode(): SwarmNode
    {
        return $this->localNode;
    }

    public function setLocalNode(SwarmNode $node): void
    {
        $this->localNode = $node;
        $this->isLeader = ($node->role === 'leader');
    }

    public function isLeader(): bool
    {
        return $this->isLeader;
    }

    public function registerPeer(SwarmNode $node): void
    {
        if ($node->nodeId === $this->localNode->nodeId) {
            return;
        }
        $this->peers[$node->nodeId] = $node;
    }

    public function removePeer(string $nodeId): void
    {
        unset($this->peers[$nodeId]);
    }

    public function getPeer(string $nodeId): ?SwarmNode
    {
        return $this->peers[$nodeId] ?? null;
    }

    /**
     * @return SwarmNode[]
     */
    public function getAllNodes(): array
    {
        $all = [$this->localNode->nodeId => $this->localNode];
        foreach ($this->peers as $id => $peer) {
            $all[$id] = $peer;
        }
        return array_values($all);
    }

    public function getStateSync(): DistributedStateSync
    {
        return $this->stateSync;
    }

    public function getLoadBalancer(): SwarmLoadBalancer
    {
        return $this->loadBalancer;
    }

    /**
     * Deterministically elect cluster leader based on lowest alphabetical node ID among healthy alive nodes.
     *
     * @return SwarmNode
     */
    public function electLeader(): SwarmNode
    {
        $nodes = $this->getAllNodes();
        $healthyNodes = array_values(array_filter(
            $nodes,
            fn(SwarmNode $n) => $n->status === 'HEALTHY' && $n->isAlive()
        ));

        // If no healthy nodes, fall back to all nodes
        $pool = !empty($healthyNodes) ? $healthyNodes : $nodes;

        usort($pool, fn(SwarmNode $a, SwarmNode $b) => strcmp($a->nodeId, $b->nodeId));

        $elected = $pool[0];
        foreach ($nodes as $node) {
            $node->role = ($node->nodeId === $elected->nodeId) ? 'leader' : 'worker';
        }

        $this->isLeader = ($this->localNode->nodeId === $elected->nodeId);
        return $elected;
    }

    /**
     * Route request to best node using specified load balancing strategy.
     *
     * @param string $strategy
     * @return SwarmNode
     */
    public function routeRequest(string $strategy = 'round_robin'): SwarmNode
    {
        return $this->loadBalancer->selectNode($this->getAllNodes(), $strategy);
    }

    /**
     * Inspect all peers for heartbeat timeouts and degrade unresponsive nodes.
     *
     * @param float $timeoutSeconds
     * @return SwarmNode[] List of degraded nodes
     */
    public function checkHeartbeats(float $timeoutSeconds = 5.0): array
    {
        $degraded = [];
        $wasLeaderDegraded = false;

        foreach ($this->peers as $peer) {
            if (!$peer->isAlive($timeoutSeconds)) {
                if ($peer->status !== 'UNREACHABLE') {
                    $peer->markUnreachable();
                    $degraded[] = $peer;
                    if ($peer->role === 'leader') {
                        $wasLeaderDegraded = true;
                    }
                }
            }
        }

        if ($wasLeaderDegraded) {
            $this->electLeader();
        }

        return $degraded;
    }

    /**
     * Handle incoming Swarm protocol raw message frame and generate authenticated response.
     *
     * @param string $rawMessage
     * @return string Authenticated JSON frame response
     */
    public function handleMessage(string $rawMessage): string
    {
        $decoded = SwarmProtocol::decode($rawMessage, $this->clusterSecret);
        $type = $decoded['type'] ?? '';
        $payload = (array)($decoded['payload'] ?? []);

        return match ($type) {
            SwarmProtocol::TYPE_HANDSHAKE => SwarmProtocol::encode(
                SwarmProtocol::TYPE_HANDSHAKE,
                [
                    'status' => 'OK',
                    'node' => $this->localNode->toArray(),
                    'is_leader' => $this->isLeader,
                    'cluster_summary' => $this->getClusterSummary(),
                ],
                $this->clusterSecret
            ),

            SwarmProtocol::TYPE_JOIN => (function () use ($payload) {
                if (isset($payload['node']) && is_array($payload['node'])) {
                    $joiningNode = SwarmNode::fromArray($payload['node']);
                    $this->registerPeer($joiningNode);
                }
                $leader = $this->electLeader();
                return SwarmProtocol::encode(
                    SwarmProtocol::TYPE_JOIN_ACK,
                    [
                        'status' => 'ACCEPTED',
                        'leader' => $leader->toArray(),
                        'nodes' => array_map(fn(SwarmNode $n) => $n->toArray(), $this->getAllNodes()),
                        'state' => $this->stateSync->getAll(),
                        'versions' => $this->stateSync->getVersions(),
                    ],
                    $this->clusterSecret
                );
            })(),

            SwarmProtocol::TYPE_HEARTBEAT => (function () use ($payload) {
                $nodeId = (string)($payload['node_id'] ?? '');
                $peer = $this->getPeer($nodeId);
                if ($peer !== null) {
                    $peer->recordHeartbeat(
                        (int)($payload['memory_used_mb'] ?? 0),
                        (int)($payload['active_connections'] ?? 0)
                    );
                } elseif ($nodeId === $this->localNode->nodeId) {
                    $this->localNode->recordHeartbeat(
                        (int)($payload['memory_used_mb'] ?? 0),
                        (int)($payload['active_connections'] ?? 0)
                    );
                }
                return SwarmProtocol::encode(
                    SwarmProtocol::TYPE_HEARTBEAT_ACK,
                    [
                        'status' => 'OK',
                        'node_id' => $this->localNode->nodeId,
                        'timestamp' => microtime(true),
                    ],
                    $this->clusterSecret
                );
            })(),

            SwarmProtocol::TYPE_STATE_SYNC => (function () use ($payload) {
                $synced = $this->stateSync->mergeDelta(
                    (array)($payload['store'] ?? []),
                    (array)($payload['versions'] ?? [])
                );
                return SwarmProtocol::encode(
                    SwarmProtocol::TYPE_STATE_SYNC,
                    [
                        'status' => 'OK',
                        'synced_keys' => $synced,
                    ],
                    $this->clusterSecret
                );
            })(),

            SwarmProtocol::TYPE_LEAVE => (function () use ($payload) {
                $nodeId = (string)($payload['node_id'] ?? '');
                $peer = $this->getPeer($nodeId);
                $wasLeader = ($peer !== null && $peer->role === 'leader');
                $this->removePeer($nodeId);
                if ($wasLeader) {
                    $this->electLeader();
                }
                return SwarmProtocol::encode(
                    SwarmProtocol::TYPE_LEAVE,
                    [
                        'status' => 'OK',
                        'node_id' => $nodeId,
                    ],
                    $this->clusterSecret
                );
            })(),

            SwarmProtocol::TYPE_ELECT_LEADER => (function () {
                $leader = $this->electLeader();
                return SwarmProtocol::encode(
                    SwarmProtocol::TYPE_ELECT_LEADER,
                    [
                        'status' => 'OK',
                        'leader' => $leader->toArray(),
                    ],
                    $this->clusterSecret
                );
            })(),

            SwarmProtocol::TYPE_TASK_DISPATCH => (function () use ($payload) {
                $taskId = (string)($payload['task_id'] ?? uniqid('task_'));
                $action = (string)($payload['action'] ?? 'execute');
                return SwarmProtocol::encode(
                    SwarmProtocol::TYPE_TASK_RESULT,
                    [
                        'task_id' => $taskId,
                        'status' => 'COMPLETED',
                        'result' => [
                            'executed_by' => $this->localNode->nodeId,
                            'action' => $action,
                            'timestamp' => microtime(true),
                        ],
                    ],
                    $this->clusterSecret
                );
            })(),

            default => SwarmProtocol::encode(
                'ACK',
                [
                    'status' => 'UNKNOWN_TYPE',
                    'type' => $type,
                ],
                $this->clusterSecret
            ),
        };
    }

    /**
     * Get real-time cluster health summary, metrics, and node topology.
     *
     * @return array<string, mixed>
     */
    public function getClusterSummary(): array
    {
        $nodes = $this->getAllNodes();
        $totalCores = array_sum(array_map(fn(SwarmNode $n) => $n->cpuCores, $nodes));
        $totalMemMb = array_sum(array_map(fn(SwarmNode $n) => $n->memoryTotalMb, $nodes));
        $usedMemMb = array_sum(array_map(fn(SwarmNode $n) => $n->memoryUsedMb, $nodes));
        $healthyCount = count(array_filter(
            $nodes,
            fn(SwarmNode $n) => $n->status === 'HEALTHY' && $n->isAlive()
        ));

        return [
            'cluster_status' => $healthyCount > 0 ? 'HEALTHY' : 'DEGRADED',
            'local_node_id' => $this->localNode->nodeId,
            'is_leader' => $this->isLeader,
            'total_nodes' => count($nodes),
            'healthy_nodes' => $healthyCount,
            'cluster_cpu_cores' => $totalCores,
            'cluster_memory_total_mb' => $totalMemMb,
            'cluster_memory_used_mb' => $usedMemMb,
            'nodes' => array_map(fn(SwarmNode $n) => $n->toArray(), $nodes),
            'state_keys_count' => count($this->stateSync->getAll()),
        ];
    }
}
