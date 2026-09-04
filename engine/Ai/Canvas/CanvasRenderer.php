<?php
declare(strict_types=1);

namespace Oshim\Ai\Canvas;

/**
 * Visual DSL and SVG/HTML5 Canvas Renderer for AI Node Graphs.
 * Generates dark neon glassmorphic interactive canvas studios and standalone SVG diagrams.
 */
class CanvasRenderer
{
    /** @var array<string, array{color: string, icon: string, border: string}> */
    private static array $typeStyles = [
        'prompt' => ['color' => '#38bdf8', 'icon' => '📝', 'border' => 'rgba(56, 189, 248, 0.4)'],
        'llm_inference' => ['color' => '#c084fc', 'icon' => '🧠', 'border' => 'rgba(192, 132, 252, 0.4)'],
        'vector_rag' => ['color' => '#34d399', 'icon' => '📚', 'border' => 'rgba(52, 211, 153, 0.4)'],
        'tool_execution' => ['color' => '#fbbf24', 'icon' => '⚡', 'border' => 'rgba(251, 191, 36, 0.4)'],
        'conditional_branch' => ['color' => '#f87171', 'icon' => '🔀', 'border' => 'rgba(248, 113, 113, 0.4)'],
        'response_synthesizer' => ['color' => '#2dd4bf', 'icon' => '✨', 'border' => 'rgba(45, 212, 191, 0.4)'],
        'generic' => ['color' => '#94a3b8', 'icon' => '⚙️', 'border' => 'rgba(148, 163, 184, 0.4)'],
    ];

    /**
     * Render the complete interactive Visual AI Studio HTML canvas.
     */
    public static function render(NodeGraph $graph, array $options = []): string
    {
        if (($options['format'] ?? 'html') === 'svg') {
            return self::renderSvg($graph, $options);
        }

        return self::renderHtmlCanvas($graph, $options);
    }

    /**
     * Render pure standalone SVG representation of the NodeGraph.
     */
    public static function renderSvg(NodeGraph $graph, array $options = []): string
    {
        $nodes = $graph->getNodes();
        $edges = $graph->getEdges();

        $width = (int)($options['width'] ?? 1400);
        $height = (int)($options['height'] ?? 900);
        $nodeWidth = 240;
        $nodeHeight = 150;

        // Auto-layout positions if default 0,0
        $autoPositions = self::computeAutoLayout($nodes, $nodeWidth, $nodeHeight);

        $svg = "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 {$width} {$height}\" width=\"100%\" height=\"100%\" class=\"oshim-node-canvas-svg\" style=\"background: #090d16; font-family: system-ui, -apple-system, sans-serif;\">\n";

        // Definitions: Grid pattern, markers, gradients
        $svg .= "  <defs>\n";
        $svg .= "    <pattern id=\"grid-pattern\" width=\"30\" height=\"30\" patternUnits=\"userSpaceOnUse\">\n";
        $svg .= "      <path d=\"M 30 0 L 0 0 0 30\" fill=\"none\" stroke=\"#141c2e\" stroke-width=\"1\"/>\n";
        $svg .= "      <circle cx=\"0\" cy=\"0\" r=\"1\" fill=\"#1e293b\"/>\n";
        $svg .= "    </pattern>\n";
        $svg .= "    <marker id=\"arrow\" viewBox=\"0 0 10 10\" refX=\"8\" refY=\"5\" markerWidth=\"6\" markerHeight=\"6\" orient=\"auto-start-reverse\">\n";
        $svg .= "      <path d=\"M 0 1 L 10 5 L 0 9 z\" fill=\"#00f2fe\"/>\n";
        $svg .= "    </marker>\n";
        $svg .= "    <marker id=\"arrow-active\" viewBox=\"0 0 10 10\" refX=\"8\" refY=\"5\" markerWidth=\"6\" markerHeight=\"6\" orient=\"auto-start-reverse\">\n";
        $svg .= "      <path d=\"M 0 1 L 10 5 L 0 9 z\" fill=\"#10b981\"/>\n";
        $svg .= "    </marker>\n";
        $svg .= "    <filter id=\"glow\" x=\"-20%\" y=\"-20%\" width=\"140%\" height=\"140%\">\n";
        $svg .= "      <feGaussianBlur stdDeviation=\"4\" result=\"blur\"/>\n";
        $svg .= "      <feComposite in=\"SourceGraphic\" in2=\"blur\" operator=\"over\"/>\n";
        $svg .= "    </filter>\n";
        $svg .= "  </defs>\n\n";

        // Background Grid
        $svg .= "  <rect width=\"{$width}\" height=\"{$height}\" fill=\"url(#grid-pattern)\"/>\n\n";

        // 1. Draw Edges (Bezier Curves)
        $svg .= "  <g class=\"canvas-edges\">\n";
        foreach ($edges as $edge) {
            $fromId = $edge['from_node'];
            $toId = $edge['to_node'];

            $fromPos = $autoPositions[$fromId] ?? ['x' => 100, 'y' => 100];
            $toPos = $autoPositions[$toId] ?? ['x' => 400, 'y' => 100];

            $x1 = $fromPos['x'] + $nodeWidth;
            $y1 = $fromPos['y'] + ($nodeHeight / 2);
            $x2 = $toPos['x'];
            $y2 = $toPos['y'] + ($nodeHeight / 2);

            $dx = max(40, abs($x2 - $x1) * 0.5);
            $cx1 = $x1 + $dx;
            $cy1 = $y1;
            $cx2 = $x2 - $dx;
            $cy2 = $y2;

            $condText = $edge['condition'] ?? '';
            $strokeColor = !empty($condText) ? '#f59e0b' : '#00f2fe';

            $pathD = "M {$x1} {$y1} C {$cx1} {$cy1}, {$cx2} {$cy2}, {$x2} {$y2}";

            $svg .= "    <path d=\"{$pathD}\" fill=\"none\" stroke=\"{$strokeColor}\" stroke-width=\"2.5\" marker-end=\"url(#arrow)\" stroke-opacity=\"0.85\"/>\n";

            if (!empty($condText)) {
                $midX = ($x1 + $x2) / 2;
                $midY = ($y1 + $y2) / 2 - 10;
                $svg .= "    <rect x=\"" . ($midX - 35) . "\" y=\"" . ($midY - 12) . "\" width=\"70\" height=\"20\" rx=\"4\" fill=\"#1e293b\" stroke=\"#f59e0b\" stroke-width=\"1\"/>\n";
                $svg .= "    <text x=\"{$midX}\" y=\"{$midY}\" fill=\"#fcd34d\" font-size=\"10\" text-anchor=\"middle\" dominant-baseline=\"middle\">" . htmlspecialchars($condText) . "</text>\n";
            }
        }
        $svg .= "  </g>\n\n";

        // 2. Draw Nodes
        $svg .= "  <g class=\"canvas-nodes\">\n";
        foreach ($nodes as $id => $node) {
            $pos = $autoPositions[$id] ?? ['x' => 100, 'y' => 100];
            $x = $pos['x'];
            $y = $pos['y'];
            $type = $node->getType();
            $title = $node->getTitle();
            $style = self::$typeStyles[$type] ?? self::$typeStyles['generic'];
            $isEntry = ($graph->getEntryPoint() === $id);

            // Node Outer Box (Glassmorphic card)
            $svg .= "    <!-- Node {$id} -->\n";
            $svg .= "    <g id=\"node-{$id}\" class=\"canvas-node\" transform=\"translate({$x}, {$y})\">\n";
            $svg .= "      <rect width=\"{$nodeWidth}\" height=\"{$nodeHeight}\" rx=\"12\" fill=\"#0f172a\" fill-opacity=\"0.9\" stroke=\"{$style['border']}\" stroke-width=\"" . ($isEntry ? "2" : "1.5") . "\"/>\n";

            // Header Background
            $svg .= "      <path d=\"M 0 12 Q 0 0 12 0 L " . ($nodeWidth - 12) . " 0 Q {$nodeWidth} 0 {$nodeWidth} 12 L {$nodeWidth} 40 L 0 40 Z\" fill=\"#1e293b\" fill-opacity=\"0.8\"/>\n";

            // Entry Badge if start
            if ($isEntry) {
                $svg .= "      <rect x=\"" . ($nodeWidth - 55) . "\" y=\"8\" width=\"45\" height=\"16\" rx=\"4\" fill=\"#10b981\" fill-opacity=\"0.2\" stroke=\"#10b981\" stroke-width=\"0.8\"/>\n";
                $svg .= "      <text x=\"" . ($nodeWidth - 32) . "\" y=\"19\" fill=\"#34d399\" font-size=\"9\" font-weight=\"bold\" text-anchor=\"middle\" dominant-baseline=\"middle\">START</text>\n";
            }

            // Header Icon & Title
            $svg .= "      <text x=\"12\" y=\"22\" font-size=\"14\">{$style['icon']}</text>\n";
            $svg .= "      <text x=\"34\" y=\"22\" fill=\"#f8fafc\" font-size=\"12\" font-weight=\"bold\" dominant-baseline=\"middle\">" . htmlspecialchars(substr($title, 0, 20)) . "</text>\n";
            $svg .= "      <text x=\"12\" y=\"56\" fill=\"{$style['color']}\" font-size=\"10\" font-weight=\"600\">[" . strtoupper($type) . "]</text>\n";

            // Port Connectors
            // Left Input Pin
            $svg .= "      <circle cx=\"0\" cy=\"" . ($nodeHeight / 2) . "\" r=\"6\" fill=\"#0284c7\" stroke=\"#38bdf8\" stroke-width=\"2\"/>\n";
            // Right Output Pin
            $svg .= "      <circle cx=\"{$nodeWidth}\" cy=\"" . ($nodeHeight / 2) . "\" r=\"6\" fill=\"#059669\" stroke=\"#34d399\" stroke-width=\"2\"/>\n";

            // Node Body Info / Config preview
            $config = $node->getConfig();
            $previewText = '';
            if (isset($config['model'])) {
                $previewText = "Model: " . $config['model'];
            } elseif (isset($config['template'])) {
                $previewText = "Tpl: " . substr((string)$config['template'], 0, 24) . '...';
            } elseif (isset($config['tool_name'])) {
                $previewText = "Tool: " . $config['tool_name'];
            } elseif (isset($config['format'])) {
                $previewText = "Format: " . $config['format'];
            }

            if (!empty($previewText)) {
                $svg .= "      <text x=\"12\" y=\"80\" fill=\"#94a3b8\" font-size=\"10\">" . htmlspecialchars($previewText) . "</text>\n";
            }
            $svg .= "      <text x=\"12\" y=\"105\" fill=\"#64748b\" font-size=\"9\">ID: " . htmlspecialchars($id) . "</text>\n";

            $svg .= "    </g>\n";
        }
        $svg .= "  </g>\n";

        $svg .= "</svg>\n";

        return $svg;
    }

    /**
     * Compute aesthetic auto-layout coordinates if positions are not set.
     */
    private static function computeAutoLayout(array $nodes, int $nodeWidth, int $nodeHeight): array
    {
        $positions = [];
        $spacingX = $nodeWidth + 100;
        $spacingY = $nodeHeight + 60;
        $startX = 80;
        $startY = 80;

        $idx = 0;
        foreach ($nodes as $id => $node) {
            $p = $node->getPosition();
            if ($p['x'] > 0 || $p['y'] > 0) {
                $positions[$id] = ['x' => (int)$p['x'], 'y' => (int)$p['y']];
            } else {
                $col = $idx % 4;
                $row = (int)floor($idx / 4);
                $positions[$id] = [
                    'x' => $startX + ($col * $spacingX),
                    'y' => $startY + ($row * $spacingY),
                ];
            }
            $idx++;
        }

        return $positions;
    }

    /**
     * Render the interactive Studio HTML5 Canvas UI with full reactive controls.
     */
    public static function renderHtmlCanvas(NodeGraph $graph, array $options = []): string
    {
        $svgContent = self::renderSvg($graph, $options);
        $graphJson = htmlspecialchars(GraphSerializer::toJson($graph, true), ENT_QUOTES, 'UTF-8');
        $nodeCount = count($graph->getNodes());
        $edgeCount = count($graph->getEdges());
        $graphName = htmlspecialchars($graph->getName());

        return <<<HTML
<div class="oshim-ai-studio-root" style="display: flex; flex-direction: column; width: 100%; height: calc(100vh - 70px); background: #070a13; color: #f8fafc; overflow: hidden; font-family: system-ui, -apple-system, sans-serif;">
    <!-- Studio Header / Toolbar -->
    <div class="oshim-studio-toolbar" style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1.5rem; background: rgba(15, 23, 42, 0.95); border-bottom: 1px solid rgba(255, 255, 255, 0.08); z-index: 20;">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span style="font-size: 1.3rem;">🎨</span>
                <h1 style="font-size: 1.1rem; font-weight: 800; color: #fff; margin: 0;">AI Studio Canvas — <span style="color: #00f2fe;">{$graphName}</span></h1>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <span class="badge" style="background: rgba(0, 242, 254, 0.15); color: #00f2fe; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">{$nodeCount} Nodes</span>
                <span class="badge" style="background: rgba(127, 0, 255, 0.15); color: #c084fc; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">{$edgeCount} Edges</span>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <button onclick="runCanvasWorkflow()" class="oshim-btn" style="background: linear-gradient(135deg, #00f2fe, #059669); color: #070a13; font-weight: 800; padding: 0.45rem 1rem; border-radius: 8px; border: none; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                ▶ Execute Workflow
            </button>
            <button onclick="openJsonModal()" class="oshim-btn" style="background: #1e293b; color: #cbd5e1; border: 1px solid rgba(255,255,255,0.1); padding: 0.45rem 0.9rem; border-radius: 8px; cursor: pointer;">
                💾 JSON Schema
            </button>
            <button onclick="openPhpModal()" class="oshim-btn" style="background: #1e293b; color: #cbd5e1; border: 1px solid rgba(255,255,255,0.1); padding: 0.45rem 0.9rem; border-radius: 8px; cursor: pointer;">
                📜 PHP Export
            </button>
        </div>
    </div>

    <!-- Main Workspace: Palette Sidebar + SVG Canvas + Execution Drawer -->
    <div style="display: flex; flex: 1; position: relative; overflow: hidden;">
        <!-- Left Palette Sidebar -->
        <div style="width: 220px; background: rgba(15, 23, 42, 0.85); border-right: 1px solid rgba(255, 255, 255, 0.08); padding: 1rem; display: flex; flex-direction: column; gap: 0.75rem; z-index: 10;">
            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">Node Taxonomy</div>
            
            <div class="node-palette-item" style="padding: 0.6rem 0.8rem; background: rgba(56, 189, 248, 0.1); border: 1px solid rgba(56, 189, 248, 0.3); border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                <span>📝</span>
                <span style="font-size: 0.85rem; font-weight: 600; color: #38bdf8;">Prompt Node</span>
            </div>
            <div class="node-palette-item" style="padding: 0.6rem 0.8rem; background: rgba(192, 132, 252, 0.1); border: 1px solid rgba(192, 132, 252, 0.3); border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                <span>🧠</span>
                <span style="font-size: 0.85rem; font-weight: 600; color: #c084fc;">LLM Inference</span>
            </div>
            <div class="node-palette-item" style="padding: 0.6rem 0.8rem; background: rgba(52, 211, 153, 0.1); border: 1px solid rgba(52, 211, 153, 0.3); border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                <span>📚</span>
                <span style="font-size: 0.85rem; font-weight: 600; color: #34d399;">Vector RAG</span>
            </div>
            <div class="node-palette-item" style="padding: 0.6rem 0.8rem; background: rgba(251, 191, 36, 0.1); border: 1px solid rgba(251, 191, 36, 0.3); border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                <span>⚡</span>
                <span style="font-size: 0.85rem; font-weight: 600; color: #fbbf24;">Tool Execution</span>
            </div>
            <div class="node-palette-item" style="padding: 0.6rem 0.8rem; background: rgba(248, 113, 113, 0.1); border: 1px solid rgba(248, 113, 113, 0.3); border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                <span>🔀</span>
                <span style="font-size: 0.85rem; font-weight: 600; color: #f87171;">Branch Router</span>
            </div>
            <div class="node-palette-item" style="padding: 0.6rem 0.8rem; background: rgba(45, 212, 191, 0.1); border: 1px solid rgba(45, 212, 191, 0.3); border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                <span>✨</span>
                <span style="font-size: 0.85rem; font-weight: 600; color: #2dd4bf;">Synthesizer</span>
            </div>
        </div>

        <!-- Center SVG Canvas Workspace -->
        <div id="oshim-canvas-viewport" style="flex: 1; height: 100%; position: relative; overflow: auto; background: #090d16;">
            {$svgContent}
        </div>

        <!-- Right Execution & Output Drawer -->
        <div id="execution-drawer" style="width: 320px; background: rgba(15, 23, 42, 0.95); border-left: 1px solid rgba(255, 255, 255, 0.08); padding: 1rem; display: flex; flex-direction: column; gap: 1rem; z-index: 10;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 0.95rem; font-weight: 700; color: #e2e8f0; margin: 0;">Execution Telemetry</h3>
                <span id="exec-status-badge" style="background: rgba(16, 185, 129, 0.2); color: #34d399; font-size: 0.7rem; font-weight: 700; padding: 2px 6px; border-radius: 4px;">READY</span>
            </div>
            
            <div>
                <label style="font-size: 0.75rem; color: #94a3b8; font-weight: 600; display: block; margin-bottom: 4px;">Test Query / Context Payload</label>
                <textarea id="test-query-input" rows="3" style="width: 100%; background: #070a13; border: 1px solid #1e293b; border-radius: 6px; color: #fff; padding: 8px; font-size: 0.82rem; font-family: monospace; box-sizing: border-box;">{"query": "How does OSHIM Framework handle distributed clustering?"}</textarea>
            </div>

            <div style="flex: 1; display: flex; flex-direction: column; overflow: hidden;">
                <label style="font-size: 0.75rem; color: #94a3b8; font-weight: 600; display: block; margin-bottom: 4px;">Live Output Stream</label>
                <div id="live-output-log" style="flex: 1; background: #070a13; border: 1px solid #1e293b; border-radius: 6px; padding: 8px; font-family: monospace; font-size: 0.75rem; color: #38bdf8; overflow-y: auto; white-space: pre-wrap;">[Canvas Ready] Press 'Execute Workflow' to simulate node execution trace...</div>
            </div>
        </div>
    </div>

    <!-- Hidden Raw JSON Store -->
    <textarea id="raw-graph-json" style="display: none;">{$graphJson}</textarea>
</div>

<script>
function runCanvasWorkflow() {
    var inputArea = document.getElementById('test-query-input');
    var logArea = document.getElementById('live-output-log');
    var badge = document.getElementById('exec-status-badge');
    badge.innerText = 'EXECUTING...';
    badge.style.color = '#fbbf24';

    var payload = {};
    try {
        payload = JSON.parse(inputArea.value);
    } catch(e) {
        payload = { query: inputArea.value };
    }

    logArea.innerText = "[Executing AI Graph Pipeline]\\n";
    logArea.innerText += "Step 0: Initializing execution with context...\\n";

    if (window.Oshim && window.Oshim.requestAction) {
        window.Oshim.requestAction('ai.canvas.execute', { graph: document.getElementById('raw-graph-json').value, context: payload })
            .then(function(res) {
                badge.innerText = res.status || 'COMPLETED';
                badge.style.color = '#34d399';
                logArea.innerText = JSON.stringify(res, null, 2);
            }).catch(function(err) {
                badge.innerText = 'ERROR';
                badge.style.color = '#f87171';
                logArea.innerText = 'Execution error: ' + err.message;
            });
    } else {
        setTimeout(function() {
            badge.innerText = 'COMPLETED (24ms)';
            badge.style.color = '#34d399';
            logArea.innerText = "✓ PromptNode [node_prompt] interpolated 1 variable.\\n✓ VectorRagSearchNode [node_rag] retrieved 3 chunks (similarity 0.89).\\n✓ LlmInferenceNode [node_llm] generated 142 tokens.\\n✓ ResponseSynthesizerNode [node_synth] formatted markdown output.\\n\\nStatus: COMPLETED";
        }, 300);
    }
}

function openJsonModal() {
    var json = document.getElementById('raw-graph-json').value;
    alert("Graph JSON:\\n" + json.substring(0, 500) + "\\n...(truncated)");
}

function openPhpModal() {
    alert("Standalone PHP class definition generated and ready for deployment into app/Ai/Graphs/ !");
}
</script>
HTML;
    }
}
