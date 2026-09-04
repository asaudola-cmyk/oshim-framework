<?php
declare(strict_types=1);

namespace Oshim\Ai\Canvas;

use InvalidArgumentException;
use JsonSerializable;
use RuntimeException;
use Throwable;
use Oshim\Ai\Agents\AgentGraph;
use Oshim\Ai\Agents\AgentState;

/**
 * NodeGraph: Graph container managing visual nodes, port-to-port directed edges,
 * cycle detection, dependency resolution, topological execution, and AgentGraph bridging.
 */
class NodeGraph implements JsonSerializable
{
    private string $name = 'AiCanvasGraph';
    private string $version = '1.0';
    private ?string $entryPoint = null;

    /** @var array<string, NodeInterface> */
    private array $nodes = [];

    /**
     * @var list<array{
     *     from_node: string,
     *     from_port: string,
     *     to_node: string,
     *     to_port: string,
     *     condition: ?string
     * }>
     */
    private array $edges = [];

    /** @var list<array<string, mixed>> Execution trace telemetry */
    private array $executionTrace = [];

    /** @var array<string, mixed> Metadata attributes */
    private array $metadata = [];

    public function __construct(string $name = 'AiCanvasGraph', string $version = '1.0')
    {
        $this->name = $name;
        $this->version = $version;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): self
    {
        $this->version = $version;
        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function addNode(NodeInterface $node): self
    {
        $this->nodes[$node->getId()] = $node;
        if ($this->entryPoint === null) {
            $this->entryPoint = $node->getId();
        }
        return $this;
    }

    public function removeNode(string $nodeId): self
    {
        unset($this->nodes[$nodeId]);

        // Remove connected edges
        $this->edges = array_values(array_filter(
            $this->edges,
            fn(array $e) => $e['from_node'] !== $nodeId && $e['to_node'] !== $nodeId
        ));

        if ($this->entryPoint === $nodeId) {
            $this->entryPoint = !empty($this->nodes) ? array_key_first($this->nodes) : null;
        }

        return $this;
    }

    public function getNode(string $nodeId): ?NodeInterface
    {
        return $this->nodes[$nodeId] ?? null;
    }

    public function hasNode(string $nodeId): bool
    {
        return isset($this->nodes[$nodeId]);
    }

    /**
     * @return array<string, NodeInterface>
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    public function setEntryPoint(string $nodeId): self
    {
        if (!isset($this->nodes[$nodeId])) {
            throw new InvalidArgumentException("Entry node '{$nodeId}' does not exist in graph.");
        }
        $this->entryPoint = $nodeId;
        return $this;
    }

    public function getEntryPoint(): ?string
    {
        return $this->entryPoint;
    }

    /**
     * Add a directed edge between nodes/ports.
     * Supports both full signature and shorthand addEdge($fromNode, $toNode).
     */
    public function addEdge(
        string $fromNodeId,
        string $fromPort = 'output',
        ?string $toNodeId = null,
        ?string $toPort = 'input',
        ?string $condition = null
    ): self {
        // Handle shorthand addEdge('nodeA', 'nodeB')
        if ($toNodeId === null || ($toNodeId === 'input' && !isset($this->nodes['input']))) {
            $actualToNode = $fromPort;
            $actualFromPort = 'output';
            $actualToPort = 'input';
        } else {
            $actualToNode = $toNodeId;
            $actualFromPort = $fromPort;
            $actualToPort = $toPort ?? 'input';
        }

        if (!isset($this->nodes[$fromNodeId])) {
            throw new InvalidArgumentException("Source node '{$fromNodeId}' does not exist in graph.");
        }
        if (!isset($this->nodes[$actualToNode]) && $actualToNode !== '__END__') {
            throw new InvalidArgumentException("Target node '{$actualToNode}' does not exist in graph.");
        }

        // Avoid duplicate identical edges
        foreach ($this->edges as $e) {
            if (
                $e['from_node'] === $fromNodeId &&
                $e['from_port'] === $actualFromPort &&
                $e['to_node'] === $actualToNode &&
                $e['to_port'] === $actualToPort &&
                $e['condition'] === $condition
            ) {
                return $this;
            }
        }

        $this->edges[] = [
            'from_node' => $fromNodeId,
            'from_port' => $actualFromPort,
            'to_node' => $actualToNode,
            'to_port' => $actualToPort,
            'condition' => $condition,
        ];

        return $this;
    }

    public function removeEdge(string $fromNodeId, ?string $toNodeId = null): self
    {
        $this->edges = array_values(array_filter(
            $this->edges,
            function (array $e) use ($fromNodeId, $toNodeId) {
                if ($e['from_node'] !== $fromNodeId) {
                    return true;
                }
                return $toNodeId !== null && $e['to_node'] !== $toNodeId;
            }
        ));
        return $this;
    }

    /**
     * @return list<array{from_node: string, from_port: string, to_node: string, to_port: string, condition: ?string}>
     */
    public function getEdges(): array
    {
        return $this->edges;
    }

    /**
     * Detect if the directed graph contains cycles using DFS coloring.
     */
    public function hasCycle(): bool
    {
        // 0 = unvisited (white), 1 = visiting (gray), 2 = visited (black)
        $visited = [];
        foreach (array_keys($this->nodes) as $id) {
            $visited[$id] = 0;
        }

        $adjacency = $this->buildAdjacencyList();

        $dfs = function (string $nodeId) use (&$dfs, &$visited, $adjacency): bool {
            $visited[$nodeId] = 1; // Mark in progress

            foreach ($adjacency[$nodeId] ?? [] as $neighbor) {
                if (!isset($this->nodes[$neighbor])) {
                    continue;
                }
                if ($visited[$neighbor] === 1) {
                    return true; // Cycle detected
                }
                if ($visited[$neighbor] === 0 && $dfs($neighbor)) {
                    return true;
                }
            }

            $visited[$nodeId] = 2; // Mark completed
            return false;
        };

        foreach (array_keys($this->nodes) as $id) {
            if ($visited[$id] === 0) {
                if ($dfs($id)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Compute topological ordering of nodes using Kahn's Algorithm.
     * Throws RuntimeException if graph contains cycles.
     *
     * @return list<string> Ordered list of node IDs
     */
    public function getTopologicalOrder(): array
    {
        $inDegree = [];
        $adjacency = [];
        foreach (array_keys($this->nodes) as $id) {
            $inDegree[$id] = 0;
            $adjacency[$id] = [];
        }

        foreach ($this->edges as $edge) {
            $from = $edge['from_node'];
            $to = $edge['to_node'];
            if (isset($this->nodes[$from]) && isset($this->nodes[$to])) {
                $adjacency[$from][] = $to;
                $inDegree[$to]++;
            }
        }

        $queue = [];
        // Prioritize explicit entry point if in-degree is 0
        if ($this->entryPoint !== null && ($inDegree[$this->entryPoint] ?? 0) === 0) {
            $queue[] = $this->entryPoint;
        }
        foreach ($inDegree as $id => $deg) {
            if ($deg === 0 && !in_array($id, $queue, true)) {
                $queue[] = $id;
            }
        }

        $order = [];
        while (!empty($queue)) {
            $curr = array_shift($queue);
            $order[] = $curr;

            foreach ($adjacency[$curr] as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        if (count($order) !== count($this->nodes)) {
            throw new RuntimeException("Graph contains cycles; cannot compute strict linear topological sort.");
        }

        return $order;
    }

    /**
     * Build adjacency map [nodeId => [neighborNodeIds...]]
     *
     * @return array<string, list<string>>
     */
    private function buildAdjacencyList(): array
    {
        $adj = [];
        foreach (array_keys($this->nodes) as $id) {
            $adj[$id] = [];
        }
        foreach ($this->edges as $edge) {
            $from = $edge['from_node'];
            $to = $edge['to_node'];
            if (isset($this->nodes[$from])) {
                $adj[$from][] = $to;
            }
        }
        return $adj;
    }

    /**
     * Execute the node graph workflow.
     *
     * @param array<string, mixed> $initialContext Initial inputs / variables
     * @param int $maxIterations Maximum execution steps to prevent infinite loops
     * @return array{
     *     status: string,
     *     outputs: array<string, mixed>,
     *     state: array<string, mixed>,
     *     execution_path: list<string>,
     *     steps_executed: int,
     *     duration_ms: float,
     *     trace: list<array<string, mixed>>
     * }
     */
    public function execute(array $initialContext = [], int $maxIterations = 50): array
    {
        $startTime = microtime(true);
        $this->executionTrace = [];

        if (empty($this->nodes)) {
            return [
                'status' => 'EMPTY_GRAPH',
                'outputs' => [],
                'state' => $initialContext,
                'execution_path' => [],
                'steps_executed' => 0,
                'duration_ms' => 0.0,
                'trace' => [],
            ];
        }

        $currentNodeId = $this->entryPoint ?? array_key_first($this->nodes);
        $state = $initialContext;
        $executionPath = [];
        $step = 0;
        $finalOutputs = [];

        while ($currentNodeId !== null && $currentNodeId !== '__END__' && $step < $maxIterations) {
            if (!isset($this->nodes[$currentNodeId])) {
                break;
            }

            $node = $this->nodes[$currentNodeId];
            $executionPath[] = $currentNodeId;

            // Prepare inputs for current node from state and inbound edge mappings
            $nodeContext = $state;

            // Execute node
            $nodeOutput = $node->execute($nodeContext);
            $lastExec = $node->getLastExecution();

            $this->executionTrace[] = [
                'step' => $step,
                'node_id' => $currentNodeId,
                'node_type' => $node->getType(),
                'node_title' => $node->getTitle(),
                'inputs' => $nodeContext,
                'outputs' => $nodeOutput,
                'duration_ms' => $lastExec['duration_ms'] ?? 0.0,
                'status' => $lastExec['status'] ?? 'COMPLETED',
                'timestamp' => microtime(true),
            ];

            // Merge node outputs into global state
            $state = array_merge($state, $nodeOutput);
            $finalOutputs = $nodeOutput;

            // Determine next node
            $nextNodeId = $this->resolveNextNode($currentNodeId, $nodeOutput, $state);
            $currentNodeId = $nextNodeId;
            $step++;
        }

        $elapsed = max(0.0001, microtime(true) - $startTime);
        $status = ($currentNodeId === null || $currentNodeId === '__END__') ? 'COMPLETED' : 'MAX_ITERATIONS_REACHED';

        return [
            'status' => $status,
            'outputs' => $finalOutputs,
            'state' => $state,
            'execution_path' => $executionPath,
            'steps_executed' => $step,
            'duration_ms' => round($elapsed * 1000, 3),
            'trace' => $this->executionTrace,
        ];
    }

    /**
     * Resolve the next node transition based on edges and conditional outputs.
     */
    private function resolveNextNode(string $currentId, array $nodeOutput, array $state): ?string
    {
        $outEdges = [];
        foreach ($this->edges as $edge) {
            if ($edge['from_node'] === $currentId) {
                $outEdges[] = $edge;
            }
        }

        if (empty($outEdges)) {
            return null; // Terminal node reached
        }

        // If node output selected a specific branch (e.g. ConditionalBranchNode)
        if (isset($nodeOutput['selected_branch'])) {
            $selected = (string)$nodeOutput['selected_branch'];

            // Match edge where condition or target matches
            foreach ($outEdges as $edge) {
                if (
                    $edge['to_node'] === $selected ||
                    $edge['condition'] === $selected ||
                    ($edge['from_port'] === 'true_branch' && $selected === 'true') ||
                    ($edge['from_port'] === 'false_branch' && $selected === 'false')
                ) {
                    return $edge['to_node'];
                }
            }

            // Direct node ID returned by branch
            if (isset($this->nodes[$selected])) {
                return $selected;
            }
        }

        // Standard edge routing: take first matching outgoing edge
        return $outEdges[0]['to_node'] ?? null;
    }

    /**
     * Convert this visual NodeGraph into a sovereign executable AgentGraph.
     */
    public function toAgentGraph(): AgentGraph
    {
        $agentGraph = new AgentGraph();

        // 1. Add all nodes
        foreach ($this->nodes as $id => $node) {
            $agentGraph->addNode($id, function (AgentState $state) use ($node) {
                $ctx = $state->all();
                $outputs = $node->execute($ctx);
                return $outputs;
            });
        }

        // 2. Set entry point
        if ($this->entryPoint !== null && isset($this->nodes[$this->entryPoint])) {
            $agentGraph->setEntryPoint($this->entryPoint);
        }

        // 3. Map edges and conditional routers
        $nodeEdges = [];
        foreach ($this->edges as $edge) {
            $from = $edge['from_node'];
            $nodeEdges[$from][] = $edge;
        }

        foreach ($this->nodes as $id => $node) {
            $edges = $nodeEdges[$id] ?? [];
            if (empty($edges)) {
                continue;
            }

            if ($node->getType() === 'conditional_branch' || count($edges) > 1) {
                // Register conditional edge router
                $agentGraph->addConditionalEdge($id, function (AgentState $state) use ($node, $edges, $id) {
                    $selectedBranch = (string)$state->get('selected_branch', '');
                    foreach ($edges as $e) {
                        if (
                            $e['to_node'] === $selectedBranch ||
                            $e['condition'] === $selectedBranch ||
                            ($e['from_port'] === 'true_branch' && $selectedBranch === 'true') ||
                            ($e['from_port'] === 'false_branch' && $selectedBranch === 'false')
                        ) {
                            return $e['to_node'];
                        }
                    }
                    if (!empty($selectedBranch) && $selectedBranch !== 'default') {
                        return $selectedBranch;
                    }
                    return $edges[0]['to_node'] ?? AgentGraph::END;
                });
            } else {
                // Fixed directed edge
                $agentGraph->addEdge($id, $edges[0]['to_node']);
            }
        }

        return $agentGraph;
    }

    public function getExecutionTrace(): array
    {
        return $this->executionTrace;
    }

    public function exportJson(bool $pretty = true): string
    {
        return GraphSerializer::toJson($this, $pretty);
    }

    public function importJson(string $json): self
    {
        $imported = GraphSerializer::fromJson($json);
        $this->name = $imported->getName();
        $this->version = $imported->getVersion();
        $this->metadata = $imported->getMetadata();
        $this->nodes = $imported->getNodes();
        $this->edges = $imported->getEdges();
        $this->entryPoint = $imported->getEntryPoint();

        return $this;
    }

    public function toArray(): array
    {
        $nodeData = [];
        foreach ($this->nodes as $id => $node) {
            $nodeData[] = $node->toArray();
        }

        return [
            'name' => $this->name,
            'version' => $this->version,
            'entry_point' => $this->entryPoint,
            'metadata' => $this->metadata,
            'nodes' => $nodeData,
            'edges' => $this->edges,
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
