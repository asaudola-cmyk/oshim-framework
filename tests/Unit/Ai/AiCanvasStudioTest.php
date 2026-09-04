<?php
declare(strict_types=1);

namespace Tests\Unit\Ai;

use Oshim\Ai\Canvas\CanvasRenderer;
use Oshim\Ai\Canvas\GraphSerializer;
use Oshim\Ai\Canvas\NodeGraph;
use Oshim\Ai\Canvas\Nodes\PromptNode;
use Oshim\Ai\Canvas\Nodes\LlmInferenceNode;
use Oshim\Ai\Canvas\Nodes\VectorRagSearchNode;
use Oshim\Ai\Canvas\Nodes\ResponseSynthesizerNode;
use Oshim\Cli\Commands\AiCanvasCommand;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Testing\TestCase;

class AiCanvasStudioTest extends TestCase
{
    public function testNodeGraphConstructionAndSerialization(): void
    {
        $graph = new NodeGraph('SupportAutomation', '2.0');

        $promptNode = new PromptNode('n_prompt', 'User Prompt Collector');
        $llmNode = new LlmInferenceNode('n_llm', 'Groq Fast Reasoner');
        $ragNode = new VectorRagSearchNode('n_rag', 'Knowledge Base Search');
        $synthNode = new ResponseSynthesizerNode('n_synth', 'Final Formatter');

        $graph->addNode($promptNode)
            ->addNode($ragNode)
            ->addNode($llmNode)
            ->addNode($synthNode);

        // Wire nodes
        $graph->addEdge('n_prompt', 'prompt', 'n_rag', 'query');
        $graph->addEdge('n_rag', 'rag_context', 'n_llm', 'system_prompt');
        $graph->addEdge('n_llm', 'response', 'n_synth', 'draft_content');

        $this->assertCount(4, $graph->getNodes());
        $this->assertCount(3, $graph->getEdges());
        $this->assertSame('SupportAutomation', $graph->getName());

        // Test JSON export and re-import
        $json = $graph->exportJson();
        $this->assertNotEmpty($json);
        $this->assertStringContainsString('SupportAutomation', $json);
        $this->assertStringContainsString('n_llm', $json);

        $restored = (new NodeGraph())->importJson($json);
        $this->assertSame('SupportAutomation', $restored->getName());
        $this->assertCount(4, $restored->getNodes());
        $this->assertCount(3, $restored->getEdges());
    }

    public function testCanvasRendererGeneratesHtmlAndSvg(): void
    {
        $graph = new NodeGraph('VisualTest');
        $nodeA = new PromptNode('n1', 'Input Node');
        $nodeB = new LlmInferenceNode('n2', 'AI Core');
        $graph->addNode($nodeA)->addNode($nodeB);
        $graph->addEdge('n1', 'out', 'n2', 'in');

        // Render HTML5 Studio
        $html = CanvasRenderer::render($graph, ['format' => 'html']);
        $this->assertStringContainsString('oshim-node-canvas', $html);
        $this->assertStringContainsString('Input Node', $html);
        $this->assertStringContainsString('AI Core', $html);

        // Render Standalone SVG
        $svg = CanvasRenderer::render($graph, ['format' => 'svg']);
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);
        $this->assertStringContainsString('n1', $svg);
    }

    public function testAiCanvasCommandExecution(): void
    {
        $cmd = new AiCanvasCommand();

        // 1. Templates listing
        $input = new Input(['oshim', 'ai:canvas', 'templates']);
        $output = new Output();
        ob_start();
        $code = $cmd->execute($input, $output);
        $text = ob_get_clean();
        $this->assertSame(0, $code);
        $this->assertStringContainsString('Available Sovereign AI Graph Templates', $text);

        // 2. Serve action
        $input2 = new Input(['oshim', 'ai:canvas', 'serve']);
        ob_start();
        $code2 = $cmd->execute($input2, $output);
        $text2 = ob_get_clean();
        $this->assertSame(0, $code2);
        $this->assertStringContainsString('OSHIM Sovereign AI Studio & Node Canvas', $text2);
    }
}
