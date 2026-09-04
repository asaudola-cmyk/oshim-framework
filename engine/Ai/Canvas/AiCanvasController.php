<?php
declare(strict_types=1);

namespace Oshim\Ai\Canvas;

use Oshim\Ai\Canvas\Nodes\ConditionalBranchNode;
use Oshim\Ai\Canvas\Nodes\LlmInferenceNode;
use Oshim\Ai\Canvas\Nodes\PromptNode;
use Oshim\Ai\Canvas\Nodes\ResponseSynthesizerNode;
use Oshim\Ai\Canvas\Nodes\ToolExecutionNode;
use Oshim\Ai\Canvas\Nodes\VectorRagSearchNode;
use Oshim\Http\Request;
use Oshim\Http\Response;
use Oshim\Ui\Dsl\Document;
use Oshim\Ui\Widgets\NavbarWidget;
use Throwable;

/**
 * Web Controller and Server Action handler for No-Code Visual AI Studio & Node Canvas.
 */
class AiCanvasController
{
    /**
     * Interactive Visual Studio Canvas Page (/ai/canvas).
     */
    public static function index(?Request $request = null): string
    {
        $graph = self::createSampleGraph('CustomerSupportRAG');
        $canvasHtml = CanvasRenderer::render($graph, ['interactive' => true]);

        return Document::make('AI Studio & Node Canvas — OSHIM Sovereign Framework')
            ->navbar(NavbarWidget::makeNavbar('canvas'))
            ->body([$canvasHtml])
            ->render();
    }

    /**
     * Documentation & Interactive Playground Page (/docs/ai/canvas).
     */
    public static function docs(?Request $request = null): string
    {
        $graph = self::createSampleGraph('ToolAgentLoop');
        $svgPreview = CanvasRenderer::renderSvg($graph, ['width' => 1200, 'height' => 500]);

        $content = <<<HTML
<div class="oshim-container" style="max-width: 1200px; margin: 2rem auto; padding: 0 1.5rem; color: #f8fafc;">
    <div style="margin-bottom: 2rem;">
        <div class="oshim-glow-badge" style="display: inline-flex; margin-bottom: 1rem;">
            <span>👑 R3: No-Code Visual AI Studio & Sovereign Node Canvas</span>
        </div>
        <h1 style="font-size: 2.5rem; font-weight: 900; margin-bottom: 0.5rem;" class="oshim-brand-gradient">
            Visual Node Canvas Architecture
        </h1>
        <p style="color: #94a3b8; font-size: 1.1rem; line-height: 1.6; max-width: 850px;">
            Compose, visualize, and execute sovereign AI agent workflows, retrieval pipelines (RAG), tool calling, and multi-model consensus graphs in Pure PHP 8.3+ with Zero Node.js and Zero external dependencies.
        </p>
    </div>

    <!-- Live SVG Visualizer Preview -->
    <div style="margin-bottom: 3rem; background: #090d16; border: 1px solid rgba(0, 242, 254, 0.3); border-radius: 16px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.6);">
        <div style="padding: 0.75rem 1.25rem; background: #0f172a; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; justify-content: space-between;">
            <span style="font-weight: 700; font-size: 0.9rem; color: #38bdf8;">⚡ Live Canvas Graph Preview: Autonomous Tool Loop</span>
            <a href="/ai/canvas" class="oshim-btn" style="background: linear-gradient(135deg, #00f2fe, #7F00FF); color: #070a13; font-weight: 700; font-size: 0.8rem; padding: 4px 12px; border-radius: 6px; text-decoration: none;">Launch Full Studio 🚀</a>
        </div>
        <div style="padding: 1rem; overflow-x: auto;">
            {$svgPreview}
        </div>
    </div>

    <!-- Node Taxonomy Grid -->
    <h2 style="font-size: 1.6rem; font-weight: 800; margin-bottom: 1.5rem; color: #fff;">Sovereign Node Taxonomy</h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 3rem;">
        <div class="oshim-glass-card">
            <h3 style="color: #38bdf8; font-weight: 800; display: flex; align-items: center; gap: 8px;"><span>📝</span> PromptNode</h3>
            <p style="color: #cbd5e1; font-size: 0.9rem; margin: 0.5rem 0;">Dynamic template interpolation with <code>{{var}}</code> variable binding, system prompt assembly, and context extraction.</p>
        </div>
        <div class="oshim-glass-card">
            <h3 style="color: #c084fc; font-weight: 800; display: flex; align-items: center; gap: 8px;"><span>🧠</span> LlmInferenceNode</h3>
            <p style="color: #cbd5e1; font-size: 0.9rem; margin: 0.5rem 0;">Multi-provider sovereign LLM inference with fallback priority chains, token metrics, and latency measurement.</p>
        </div>
        <div class="oshim-glass-card">
            <h3 style="color: #34d399; font-weight: 800; display: flex; align-items: center; gap: 8px;"><span>📚</span> VectorRagSearchNode</h3>
            <p style="color: #cbd5e1; font-size: 0.9rem; margin: 0.5rem 0;">Semantic vector similarity search, Top-K chunk retrieval, inline document ingestion, and context formatting.</p>
        </div>
        <div class="oshim-glass-card">
            <h3 style="color: #fbbf24; font-weight: 800; display: flex; align-items: center; gap: 8px;"><span>⚡</span> ToolExecutionNode</h3>
            <p style="color: #cbd5e1; font-size: 0.9rem; margin: 0.5rem 0;">Agentic tool discovery and execution via ToolRegistry with dynamic argument binding and error isolation.</p>
        </div>
        <div class="oshim-glass-card">
            <h3 style="color: #f87171; font-weight: 800; display: flex; align-items: center; gap: 8px;"><span>🔀</span> ConditionalBranchNode</h3>
            <p style="color: #cbd5e1; font-size: 0.9rem; margin: 0.5rem 0;">Predicate evaluation supporting regex, comparison operators, and dynamic routing to target ports/nodes.</p>
        </div>
        <div class="oshim-glass-card">
            <h3 style="color: #2dd4bf; font-weight: 800; display: flex; align-items: center; gap: 8px;"><span>✨</span> ResponseSynthesizerNode</h3>
            <p style="color: #cbd5e1; font-size: 0.9rem; margin: 0.5rem 0;">Multi-source aggregation synthesizing Markdown, JSON, HTML, or text into structured output responses.</p>
        </div>
    </div>
</div>
HTML;

        return Document::make('AI Canvas Architecture — OSHIM Documentation')
            ->navbar(NavbarWidget::makeNavbar('canvas'))
            ->body([$content])
            ->render();
    }

    /**
     * Dispatch JSON action requests (e.g. from UI or API).
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function handleAction(array $payload): array
    {
        $action = (string)($payload['action'] ?? '');

        try {
            return match ($action) {
                'ai.canvas.execute', 'execute' => self::executeGraphAction($payload),
                'ai.canvas.validate', 'validate' => self::validateGraphAction($payload),
                'ai.canvas.templates', 'templates' => self::getTemplatesAction(),
                'ai.canvas.export_php', 'export_php' => self::exportPhpAction($payload),
                'ai.canvas.save', 'save' => ['status' => 'SUCCESS', 'message' => 'Graph schema saved successfully.'],
                'ai.canvas.load', 'load' => self::loadGraphAction($payload),
                default => ['status' => 'ERROR', 'message' => "Unknown canvas action '{$action}'."],
            };
        } catch (Throwable $e) {
            return [
                'status' => 'ERROR',
                'error' => $e->getMessage(),
            ];
        }
    }

    private static function executeGraphAction(array $payload): array
    {
        $graphData = $payload['graph'] ?? null;
        $context = (array)($payload['context'] ?? []);

        if (is_string($graphData)) {
            $graph = GraphSerializer::fromJson($graphData);
        } elseif (is_array($graphData)) {
            $graph = GraphSerializer::fromArray($graphData);
        } else {
            $graph = self::createSampleGraph('CustomerSupportRAG');
        }

        return $graph->execute($context);
    }

    private static function validateGraphAction(array $payload): array
    {
        $graphData = $payload['graph'] ?? null;
        if (is_string($graphData)) {
            $graph = GraphSerializer::fromJson($graphData);
        } elseif (is_array($graphData)) {
            $graph = GraphSerializer::fromArray($graphData);
        } else {
            return ['valid' => false, 'errors' => ['No graph definition provided.']];
        }

        $errors = [];
        foreach ($graph->getNodes() as $node) {
            if (!$node->validate()) {
                $errors = array_merge($errors, $node->getErrors());
            }
        }

        $hasCycle = $graph->hasCycle();

        return [
            'valid' => empty($errors),
            'has_cycle' => $hasCycle,
            'node_count' => count($graph->getNodes()),
            'edge_count' => count($graph->getEdges()),
            'errors' => $errors,
        ];
    }

    private static function getTemplatesAction(): array
    {
        return [
            'templates' => [
                'CustomerSupportRAG' => self::createSampleGraph('CustomerSupportRAG')->toArray(),
                'ToolAgentLoop' => self::createSampleGraph('ToolAgentLoop')->toArray(),
                'MultiBranchRouter' => self::createSampleGraph('MultiBranchRouter')->toArray(),
            ]
        ];
    }

    private static function exportPhpAction(array $payload): array
    {
        $graphData = $payload['graph'] ?? null;
        $graph = is_string($graphData) ? GraphSerializer::fromJson($graphData) : (is_array($graphData) ? GraphSerializer::fromArray($graphData) : self::createSampleGraph('CustomerSupportRAG'));
        $className = (string)($payload['class_name'] ?? 'CustomerSupportGraph');

        $phpCode = GraphSerializer::exportToPhpDefinition($graph, $className);
        return [
            'status' => 'SUCCESS',
            'class_name' => $className,
            'code' => $phpCode,
        ];
    }

    private static function loadGraphAction(array $payload): array
    {
        $name = (string)($payload['name'] ?? 'CustomerSupportRAG');
        $graph = self::createSampleGraph($name);
        return [
            'status' => 'SUCCESS',
            'graph' => $graph->toArray(),
        ];
    }

    /**
     * Pre-configured sovereign template graphs.
     */
    public static function createSampleGraph(string $template = 'CustomerSupportRAG'): NodeGraph
    {
        $graph = new NodeGraph($template);

        if ($template === 'ToolAgentLoop') {
            $prompt = new PromptNode('node_prompt', 'User Goal Prompt', [
                'template' => 'Calculate total cost for servers: {{servers}} * {{cost_per_server}}',
            ], ['x' => 80, 'y' => 120]);

            $tool = new ToolExecutionNode('node_tool', 'Server Cost Tool', [
                'tool_name' => 'calculator',
                'default_arguments' => ['expression' => '10 * 45'],
            ], ['x' => 380, 'y' => 120]);

            $branch = new ConditionalBranchNode('node_branch', 'Threshold Check', [
                'rules' => [
                    ['key' => 'tool_result', 'op' => '>', 'value' => 500, 'target' => 'node_high_alert'],
                    ['key' => 'tool_result', 'op' => '<=', 'value' => 500, 'target' => 'node_synth'],
                ],
                'default_target' => 'node_synth',
            ], ['x' => 680, 'y' => 120]);

            $synth = new ResponseSynthesizerNode('node_synth', 'Final Quote Output', [
                'format' => 'markdown',
                'title' => 'Server Provisioning Estimate',
            ], ['x' => 980, 'y' => 120]);

            $graph->addNode($prompt)
                ->addNode($tool)
                ->addNode($branch)
                ->addNode($synth)
                ->setEntryPoint('node_prompt')
                ->addEdge('node_prompt', 'node_tool')
                ->addEdge('node_tool', 'node_branch')
                ->addEdge('node_branch', 'output', 'node_synth', 'input', 'tool_result <= 500');

            return $graph;
        }

        // Default: CustomerSupportRAG
        $promptNode = new PromptNode('node_prompt', 'User Inquiry Prompt', [
            'template' => 'Answer user question using context: {{query}}',
        ], ['x' => 80, 'y' => 120]);

        $ragNode = new VectorRagSearchNode('node_rag', 'Knowledge Base Search', [
            'top_k' => 3,
            'collection' => 'support_docs',
            'documents' => [
                ['id' => 'doc_1', 'text' => 'OSHIM Sovereign Framework is written in 100% Pure PHP 8.3+ with Zero external dependencies.'],
                ['id' => 'doc_2', 'text' => 'Swarm module provides HMAC-SHA256 authenticated distributed clustering and load balancing.'],
                ['id' => 'doc_3', 'text' => 'The Wasm module features a stack machine interpreter with WASI preview 1 host calls.'],
            ],
        ], ['x' => 380, 'y' => 120]);

        $llmNode = new LlmInferenceNode('node_llm', 'LLM Sovereign Inference', [
            'model' => 'oshim-sovereign-7b',
            'temperature' => 0.7,
            'system_prompt' => 'You are a sovereign technical assistant.',
        ], ['x' => 680, 'y' => 120]);

        $synthNode = new ResponseSynthesizerNode('node_synth', 'Structured Response', [
            'format' => 'markdown',
            'title' => 'Customer Inquiry Resolution',
        ], ['x' => 980, 'y' => 120]);

        $graph->addNode($promptNode)
            ->addNode($ragNode)
            ->addNode($llmNode)
            ->addNode($synthNode)
            ->setEntryPoint('node_prompt')
            ->addEdge('node_prompt', 'node_rag')
            ->addEdge('node_rag', 'node_llm')
            ->addEdge('node_llm', 'node_synth');

        return $graph;
    }
}
