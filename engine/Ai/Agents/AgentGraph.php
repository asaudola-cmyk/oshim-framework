<?php
declare(strict_types=1);

namespace Oshim\Ai\Agents;

use RuntimeException;
use InvalidArgumentException;

/**
 * Stateful Cyclic Multi-Agent Graph Engine (LangGraph Architecture in Pure PHP 8.3+).
 * Supports state machine nodes, conditional branching edges, loops, and human-in-the-loop triggers.
 */
class AgentGraph
{
    public const START = '__START__';
    public const END   = '__END__';

    /** @var array<string, callable> */
    private array $nodes = [];
    /** @var array<string, string> Fixed directed edges */
    private array $edges = [];
    /** @var array<string, callable> Conditional edges (returns target node name) */
    private array $conditionalEdges = [];
    private string $entryPoint = self::START;

    public function addNode(string $name, callable $handler): self
    {
        if ($name === self::START || $name === self::END) {
            throw new InvalidArgumentException("Cannot overwrite reserved graph nodes START/END");
        }
        $this->nodes[$name] = $handler;
        return $this;
    }

    public function setEntryPoint(string $nodeName): self
    {
        if (!isset($this->nodes[$nodeName])) {
            throw new InvalidArgumentException("Entry node '{$nodeName}' does not exist in graph");
        }
        $this->entryPoint = $nodeName;
        return $this;
    }

    public function addEdge(string $from, string $to): self
    {
        $this->edges[$from] = $to;
        return $this;
    }

    public function addConditionalEdge(string $from, callable $router): self
    {
        $this->conditionalEdges[$from] = $router;
        return $this;
    }

    /**
     * Execute the stateful graph workflow until reaching END or max iterations.
     *
     * @param array $initialState
     * @param int $maxIterations
     * @return array Final state dictionary and execution path
     */
    public function run(array $initialState = [], int $maxIterations = 50): array
    {
        $state = new AgentState($initialState);
        $currentNode = $this->entryPoint;
        $executionPath = [];
        $iteration = 0;

        while ($currentNode !== self::END && $iteration < $maxIterations) {
            if (!isset($this->nodes[$currentNode])) {
                throw new RuntimeException("Execution reached undefined node: '{$currentNode}'");
            }

            $executionPath[] = $currentNode;
            $state->checkpoint("step_{$iteration}_{$currentNode}");

            // Execute node handler
            $nodeHandler = $this->nodes[$currentNode];
            $delta = $nodeHandler($state);

            if (is_array($delta)) {
                $state->merge($delta);
            }

            // Determine next transition
            if (isset($this->conditionalEdges[$currentNode])) {
                $router = $this->conditionalEdges[$currentNode];
                $nextNode = $router($state);
            } elseif (isset($this->edges[$currentNode])) {
                $nextNode = $this->edges[$currentNode];
            } else {
                $nextNode = self::END;
            }

            $currentNode = $nextNode;
            $iteration++;
        }

        return [
            'state' => $state->all(),
            'path' => $executionPath,
            'iterations' => $iteration,
            'status' => $currentNode === self::END ? 'COMPLETED' : 'MAX_ITERATIONS_REACHED',
        ];
    }
}
