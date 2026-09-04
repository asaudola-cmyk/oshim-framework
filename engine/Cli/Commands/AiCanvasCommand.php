<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Ai\Canvas\AiCanvasController;
use Oshim\Ai\Canvas\CanvasRenderer;
use Oshim\Ai\Canvas\GraphSerializer;
use Oshim\Ai\Canvas\NodeGraph;
use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Throwable;

/**
 * CLI Command: oshim ai:canvas [export|serve|run|validate|templates]
 * Sovereign No-Code Visual AI Studio & Node Canvas CLI controller.
 */
class AiCanvasCommand extends Command
{
    protected string $name = 'ai:canvas';
    protected string $description = 'Visual AI Studio & Node Canvas management (export, run, validate, serve)';

    protected function configure(): void
    {
        $this->addArgument('action', Input::OPTIONAL, 'Action: export, serve, run, validate, templates', 'serve')
            ->addArgument('target', Input::OPTIONAL, 'File path or template name (CustomerSupportRAG, ToolAgentLoop)', null)
            ->addOption('file', 'f', Input::VALUE_OPTIONAL, 'Path to canvas JSON file')
            ->addOption('format', null, Input::VALUE_OPTIONAL, 'Export format: json, php, svg, html', 'json')
            ->addOption('output', 'o', Input::VALUE_OPTIONAL, 'Output destination file path')
            ->addOption('class', 'c', Input::VALUE_OPTIONAL, 'PHP class name for export', 'CustomAiGraph')
            ->addOption('context', null, Input::VALUE_OPTIONAL, 'JSON string of context variables for execution', '{}')
            ->addOption('max-iterations', null, Input::VALUE_OPTIONAL, 'Max execution loop iterations', 50);
    }

    public function execute(Input $input, Output $output): int
    {
        $action = strtolower((string)($input->getArgument('action') ?? $input->getArgument(0, 'serve')));
        $target = $input->getArgument('target') ?? $input->getArgument(1);

        return match ($action) {
            'export' => $this->handleExport($input, $output, $target),
            'run' => $this->handleRun($input, $output, $target),
            'validate' => $this->handleValidate($input, $output, $target),
            'templates', 'list' => $this->handleTemplates($output),
            'serve' => $this->handleServe($output),
            default => $this->handleUnknownAction($action, $output),
        };
    }

    private function handleServe(Output $output): int
    {
        $output->writeln("<bold><cyan>🎨 OSHIM Sovereign AI Studio & Node Canvas</cyan></bold>");
        $output->writeln("Visual Graph Studio available at: <yellow>http://localhost:8000/ai/canvas</yellow>");
        $output->writeln("Interactive Documentation:        <yellow>http://localhost:8000/docs/ai/canvas</yellow>");
        $output->writeln("\n<dim>Use 'oshim serve' to boot the full HTTP development reactor.</dim>");
        return 0;
    }

    private function handleExport(Input $input, Output $output, ?string $target): int
    {
        $filePath = $input->getOption('file') ?? $target;
        $format = strtolower((string)($input->getOption('format') ?? 'json'));
        $outPath = $input->getOption('output');
        $className = (string)($input->getOption('class') ?? 'CustomAiGraph');

        try {
            $graph = $this->loadGraphFromInput($filePath);
        } catch (Throwable $e) {
            $output->error("Export failed: " . $e->getMessage());
            return 1;
        }

        $content = match ($format) {
            'php' => GraphSerializer::exportToPhpDefinition($graph, $className),
            'svg' => CanvasRenderer::renderSvg($graph),
            'html' => CanvasRenderer::renderHtmlCanvas($graph),
            'json' => GraphSerializer::toJson($graph, true),
            default => GraphSerializer::toJson($graph, true),
        };

        if (!empty($outPath) && is_string($outPath)) {
            $dir = dirname($outPath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            file_put_contents($outPath, $content);
            $output->success("Exported {$graph->getName()} [{$format}] to: {$outPath}");
        } else {
            $output->writeln($content);
        }

        return 0;
    }

    private function handleRun(Input $input, Output $output, ?string $target): int
    {
        $filePath = $input->getOption('file') ?? $target;
        $contextRaw = (string)($input->getOption('context') ?? '{}');
        $maxIterations = (int)($input->getOption('max-iterations') ?? 50);

        try {
            $graph = $this->loadGraphFromInput($filePath);
        } catch (Throwable $e) {
            $output->error("Run failed: " . $e->getMessage());
            return 1;
        }

        $context = json_decode($contextRaw, true);
        if (!is_array($context)) {
            $context = ['query' => $contextRaw];
        }

        $output->writeln("<bold><cyan>🚀 Executing AI Node Graph:</cyan> <yellow>{$graph->getName()}</yellow></bold>");
        $result = $graph->execute($context, $maxIterations);

        $output->writeln("Status:        <green>{$result['status']}</green>");
        $output->writeln("Execution Path: " . implode(' -> ', $result['execution_path']));
        $output->writeln("Duration:      {$result['duration_ms']} ms");
        $output->writeln("Steps:         {$result['steps_executed']}");

        $output->writeln("\n<bold>Outputs:</bold>");
        $output->writeln(json_encode($result['outputs'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return 0;
    }

    private function handleValidate(Input $input, Output $output, ?string $target): int
    {
        $filePath = $input->getOption('file') ?? $target;

        try {
            $graph = $this->loadGraphFromInput($filePath);
        } catch (Throwable $e) {
            $output->error("Validation failed: " . $e->getMessage());
            return 1;
        }

        $hasCycle = $graph->hasCycle();
        $nodeCount = count($graph->getNodes());
        $edgeCount = count($graph->getEdges());
        $errors = [];

        foreach ($graph->getNodes() as $node) {
            if (!$node->validate()) {
                $errors = array_merge($errors, $node->getErrors());
            }
        }

        $output->writeln("<bold><cyan>🔍 Validating Graph:</cyan> {$graph->getName()}</bold>");
        $output->writeln("Nodes: " . $nodeCount);
        $output->writeln("Edges: " . $edgeCount);
        $output->writeln("Contains Cycle: " . ($hasCycle ? '<yellow>Yes (Cyclic Loop)</yellow>' : '<green>No (DAG)</green>'));

        if (!empty($errors)) {
            $output->error("Validation Errors Found:");
            foreach ($errors as $err) {
                $output->writeln(" - <red>{$err}</red>");
            }
            return 1;
        }

        $output->success("Graph schema is valid and executable!");
        return 0;
    }

    private function handleTemplates(Output $output): int
    {
        $output->writeln("<bold><cyan>📚 Available Sovereign AI Graph Templates:</cyan></bold>");
        $templates = [
            ['Name' => 'CustomerSupportRAG', 'Description' => 'Prompt -> Vector RAG Search -> Sovereign LLM -> Synthesizer'],
            ['Name' => 'ToolAgentLoop', 'Description' => 'Goal Prompt -> Tool Execution (Calculator) -> Threshold Branch -> Quote Output'],
            ['Name' => 'MultiBranchRouter', 'Description' => 'Input Dispatcher -> Semantic Classifier -> Multi-Agent Routing'],
        ];

        $headers = ['Template', 'Description'];
        $rows = array_map(fn($t) => [$t['Name'], $t['Description']], $templates);
        $output->table($headers, $rows);

        return 0;
    }

    private function handleUnknownAction(string $action, Output $output): int
    {
        $output->error("Unknown action '{$action}'. Supported actions: export, serve, run, validate, templates");
        return 1;
    }

    private function loadGraphFromInput(?string $filePath): NodeGraph
    {
        if (empty($filePath)) {
            return AiCanvasController::createSampleGraph('CustomerSupportRAG');
        }

        if (in_array($filePath, ['CustomerSupportRAG', 'ToolAgentLoop', 'MultiBranchRouter'], true)) {
            return AiCanvasController::createSampleGraph($filePath);
        }

        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: '{$filePath}'");
        }

        $json = (string)file_get_contents($filePath);
        return GraphSerializer::fromJson($json);
    }
}
