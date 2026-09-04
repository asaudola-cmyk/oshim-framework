<?php
declare(strict_types=1);

namespace Oshim\Ai\Canvas;

use InvalidArgumentException;
use Oshim\Ai\Canvas\Nodes\ConditionalBranchNode;
use Oshim\Ai\Canvas\Nodes\LlmInferenceNode;
use Oshim\Ai\Canvas\Nodes\PromptNode;
use Oshim\Ai\Canvas\Nodes\ResponseSynthesizerNode;
use Oshim\Ai\Canvas\Nodes\ToolExecutionNode;
use Oshim\Ai\Canvas\Nodes\VectorRagSearchNode;

/**
 * Two-way serializer converting between NodeGraph instances, JSON schemas, and standalone PHP AgentGraph code.
 */
class GraphSerializer
{
    public const SCHEMA_VERSION = '1.0';

    /** @var array<string, class-string<NodeInterface>> */
    private static array $nodeTypeRegistry = [
        'prompt' => PromptNode::class,
        'llm_inference' => LlmInferenceNode::class,
        'vector_rag' => VectorRagSearchNode::class,
        'tool_execution' => ToolExecutionNode::class,
        'conditional_branch' => ConditionalBranchNode::class,
        'response_synthesizer' => ResponseSynthesizerNode::class,
    ];

    /** @var list<string> */
    private static array $validationErrors = [];

    /**
     * Register a custom node type mapping.
     *
     * @param string $type
     * @param class-string<NodeInterface> $class
     */
    public static function registerNodeType(string $type, string $class): void
    {
        self::$nodeTypeRegistry[$type] = $class;
    }

    public static function getValidationErrors(): array
    {
        return self::$validationErrors;
    }

    /**
     * Serialize a NodeGraph instance to JSON.
     */
    public static function toJson(NodeGraph $graph, bool $pretty = true): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $json = json_encode(self::toArray($graph), $flags);
        if ($json === false) {
            throw new InvalidArgumentException("Failed to serialize NodeGraph to JSON: " . json_last_error_msg());
        }

        return $json;
    }

    /**
     * Deserialize JSON into a NodeGraph instance.
     */
    public static function fromJson(string $json): NodeGraph
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new InvalidArgumentException("Invalid JSON schema for NodeGraph: " . json_last_error_msg());
        }

        return self::fromArray($data);
    }

    /**
     * Convert a NodeGraph to structured array representation.
     */
    public static function toArray(NodeGraph $graph): array
    {
        return $graph->toArray();
    }

    /**
     * Reconstitute a NodeGraph from array representation.
     */
    public static function fromArray(array $data): NodeGraph
    {
        if (!self::validateSchema($data)) {
            throw new InvalidArgumentException(
                "Invalid NodeGraph array schema: " . implode('; ', self::$validationErrors)
            );
        }

        $name = (string)($data['name'] ?? 'AiCanvasGraph');
        $version = (string)($data['version'] ?? self::SCHEMA_VERSION);
        $graph = new NodeGraph($name, $version);

        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $graph->setMetadata($data['metadata']);
        }

        // 1. Instantiate Nodes
        $nodesData = (array)($data['nodes'] ?? []);
        foreach ($nodesData as $nData) {
            if (!is_array($nData)) {
                continue;
            }
            $type = (string)($nData['type'] ?? 'prompt');
            $nodeClass = self::$nodeTypeRegistry[$type] ?? null;

            if ($nodeClass !== null && method_exists($nodeClass, 'fromArray')) {
                $node = $nodeClass::fromArray($nData);
            } else {
                // Fallback default node
                $node = PromptNode::fromArray($nData);
            }

            $graph->addNode($node);
        }

        // 2. Set Entry Point
        if (isset($data['entry_point']) && is_string($data['entry_point']) && !empty($data['entry_point'])) {
            if ($graph->hasNode($data['entry_point'])) {
                $graph->setEntryPoint($data['entry_point']);
            }
        }

        // 3. Reconnect Edges
        $edgesData = (array)($data['edges'] ?? []);
        foreach ($edgesData as $eData) {
            if (!is_array($eData)) {
                continue;
            }
            $from = (string)($eData['from_node'] ?? $eData['from'] ?? '');
            $fromPort = (string)($eData['from_port'] ?? 'output');
            $to = (string)($eData['to_node'] ?? $eData['to'] ?? '');
            $toPort = (string)($eData['to_port'] ?? 'input');
            $condition = isset($eData['condition']) ? (string)$eData['condition'] : null;

            if (!empty($from) && !empty($to)) {
                $graph->addEdge($from, $fromPort, $to, $toPort, $condition);
            }
        }

        return $graph;
    }

    /**
     * Validate graph array schema.
     */
    public static function validateSchema(array $data): bool
    {
        self::$validationErrors = [];

        if (!isset($data['nodes']) || !is_array($data['nodes'])) {
            self::$validationErrors[] = "Missing or invalid 'nodes' array in schema.";
        }

        if (isset($data['nodes']) && is_array($data['nodes'])) {
            $seenIds = [];
            foreach ($data['nodes'] as $idx => $node) {
                if (!is_array($node)) {
                    self::$validationErrors[] = "Node at index {$idx} is not an object/array.";
                    continue;
                }
                if (empty($node['id'])) {
                    self::$validationErrors[] = "Node at index {$idx} is missing required 'id'.";
                } else {
                    $id = (string)$node['id'];
                    if (isset($seenIds[$id])) {
                        self::$validationErrors[] = "Duplicate node ID '{$id}' detected.";
                    }
                    $seenIds[$id] = true;
                }
                if (empty($node['type'])) {
                    self::$validationErrors[] = "Node at index {$idx} is missing required 'type'.";
                }
            }
        }

        if (isset($data['edges']) && is_array($data['edges'])) {
            foreach ($data['edges'] as $idx => $edge) {
                if (!is_array($edge)) {
                    self::$validationErrors[] = "Edge at index {$idx} is not an array.";
                    continue;
                }
                $from = $edge['from_node'] ?? $edge['from'] ?? null;
                $to = $edge['to_node'] ?? $edge['to'] ?? null;
                if (empty($from) || empty($to)) {
                    self::$validationErrors[] = "Edge at index {$idx} missing 'from' or 'to' reference.";
                }
            }
        }

        return empty(self::$validationErrors);
    }

    /**
     * Export NodeGraph definition as standalone pure PHP class definition.
     */
    public static function exportToPhpDefinition(
        NodeGraph $graph,
        string $className = 'AiWorkflowGraph',
        string $namespace = 'App\\Ai\\Graphs'
    ): string {
        $nodes = $graph->getNodes();
        $edges = $graph->getEdges();
        $entryPoint = $graph->getEntryPoint() ?? (array_key_first($nodes) ?: 'node_start');

        $code = "<?php\n";
        $code .= "declare(strict_types=1);\n\n";
        $code .= "namespace {$namespace};\n\n";
        $code .= "use Oshim\\Ai\\Agents\\AgentGraph;\n";
        $code .= "use Oshim\\Ai\\Agents\\AgentState;\n";
        $code .= "use Oshim\\Ai\\OshimAi;\n";
        $code .= "use Oshim\\Ai\\Canvas\\NodeGraph;\n";
        $code .= "use Oshim\\Ai\\Canvas\\GraphSerializer;\n\n";
        $code .= "/**\n";
        $code .= " * Autogenerated Sovereign AI Workflow Graph Definition\n";
        $code .= " * Name: " . addslashes($graph->getName()) . "\n";
        $code .= " * Version: " . addslashes($graph->getVersion()) . "\n";
        $code .= " * Generated: " . date('Y-m-d H:i:s') . "\n";
        $code .= " */\n";
        $code .= "class {$className}\n";
        $code .= "{\n";
        $code .= "    public static function create(): AgentGraph\n";
        $code .= "    {\n";
        $code .= "        \$graph = new AgentGraph();\n\n";

        // Add node closures
        foreach ($nodes as $id => $node) {
            $type = $node->getType();
            $title = addslashes($node->getTitle());
            $configExport = var_export($node->getConfig(), true);
            $configIndented = str_replace("\n", "\n            ", $configExport);

            $code .= "        // Node: {$title} ({$type})\n";
            $code .= "        \$graph->addNode('{$id}', function (AgentState \$state) {\n";

            switch ($type) {
                case 'prompt':
                    $template = addslashes((string)$node->getConfigValue('template', '{{query}}'));
                    $code .= "            \$query = (string)\$state->get('query', \$state->get('user_query', ''));\n";
                    $code .= "            \$template = '{$template}';\n";
                    $code .= "            \$prompt = str_replace(['{{query}}', '{{user_query}}'], \$query, \$template);\n";
                    $code .= "            return ['prompt' => \$prompt, 'user_query' => \$query];\n";
                    break;

                case 'llm_inference':
                    $model = addslashes((string)$node->getConfigValue('model', 'oshim-sovereign-7b'));
                    $temp = (float)$node->getConfigValue('temperature', 0.7);
                    $code .= "            \$prompt = (string)\$state->get('prompt', '');\n";
                    $code .= "            \$engine = OshimAi::model('{$model}', {$temp});\n";
                    $code .= "            \$res = \$engine->generate(\$prompt);\n";
                    $code .= "            return ['reply' => \$res['reply'], 'model' => '{$model}'];\n";
                    break;

                case 'conditional_branch':
                    $code .= "            \$rules = {$configIndented};\n";
                    $code .= "            \$val = \$state->get('result', \$state->get('confidence', true));\n";
                    $code .= "            return ['selected_branch' => (\$val ? 'true' : 'false')];\n";
                    break;

                case 'response_synthesizer':
                    $fmt = addslashes((string)$node->getConfigValue('format', 'markdown'));
                    $code .= "            \$reply = (string)\$state->get('reply', '');\n";
                    $code .= "            \$context = (string)\$state->get('rag_context', '');\n";
                    $code .= "            return ['final_response' => \"# Response\\n\\n{\$reply}\\n\\n---\\n{\$context}\", 'format' => '{$fmt}'];\n";
                    break;

                default:
                    $code .= "            return ['output' => \$state->all()];\n";
                    break;
            }

            $code .= "        });\n\n";
        }

        // Set entry point
        $code .= "        \$graph->setEntryPoint('{$entryPoint}');\n\n";

        // Add edges
        foreach ($edges as $edge) {
            $from = $edge['from_node'];
            $to = $edge['to_node'];
            $cond = $edge['condition'];
            if ($cond !== null) {
                $code .= "        // Conditional edge\n";
                $code .= "        \$graph->addConditionalEdge('{$from}', fn(AgentState \$s) => (\$s->get('selected_branch') === '{$cond}') ? '{$to}' : AgentGraph::END);\n";
            } else {
                $code .= "        \$graph->addEdge('{$from}', '{$to}');\n";
            }
        }

        $code .= "\n        return \$graph;\n";
        $code .= "    }\n";
        $code .= "}\n";

        return $code;
    }
}
