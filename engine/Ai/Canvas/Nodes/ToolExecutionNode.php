<?php
declare(strict_types=1);

namespace Oshim\Ai\Canvas\Nodes;

use Oshim\Ai\Canvas\AbstractNode;
use Oshim\Ai\Tools\ToolRegistry;
use Throwable;

/**
 * Tool Execution Node: Dynamically discovers and invokes agentic tools from ToolRegistry.
 */
class ToolExecutionNode extends AbstractNode
{
    protected string $type = 'tool_execution';
    protected string $title = 'Tool Execution';

    private ?ToolRegistry $toolRegistry = null;

    protected function definePorts(): void
    {
        $this->registerInputPort('tool_name', 'string', 'Target tool identifier to execute', false, null);
        $this->registerInputPort('arguments', 'array', 'Arguments map for tool invocation', false, []);
        $this->registerInputPort('input_data', 'any', 'Direct payload to map into tool parameters', false, null);

        $this->registerOutputPort('tool_result', 'any', 'Output returned from tool execution');
        $this->registerOutputPort('status', 'string', 'Execution status (SUCCESS / FAILED)');
        $this->registerOutputPort('tool_error', 'string', 'Error message if tool execution failed');
        $this->registerOutputPort('tool_name', 'string', 'Name of tool that was executed');
    }

    public function setToolRegistry(ToolRegistry $registry): static
    {
        $this->toolRegistry = $registry;
        return $this;
    }

    public function getToolRegistry(): ToolRegistry
    {
        if ($this->toolRegistry === null) {
            $this->toolRegistry = $this->createDefaultToolRegistry();
        }
        return $this->toolRegistry;
    }

    protected function createDefaultToolRegistry(): ToolRegistry
    {
        $reg = new ToolRegistry();

        // 1. Calculator tool
        $reg->register(
            'calculator',
            'Evaluates mathematical expressions safely',
            ['type' => 'object', 'properties' => ['expression' => ['type' => 'string']]],
            function (array $args): mixed {
                $expr = (string)($args['expression'] ?? $args['expr'] ?? '0');
                $sanitized = preg_replace('/[^0-9\+\-\*\/\(\)\.\s]/', '', $expr);
                if (empty($sanitized)) {
                    return 0;
                }
                try {
                    // Safe basic math evaluator
                    $fn = @eval('return (' . $sanitized . ');');
                    return $fn !== false ? $fn : 0;
                } catch (Throwable) {
                    return 0;
                }
            }
        );

        // 2. Text Formatter tool
        $reg->register(
            'text_formatter',
            'Formats and transforms text strings',
            ['type' => 'object', 'properties' => ['text' => ['type' => 'string'], 'action' => ['type' => 'string']]],
            function (array $args): string {
                $text = (string)($args['text'] ?? '');
                $action = (string)($args['action'] ?? 'uppercase');
                return match ($action) {
                    'uppercase', 'upper' => strtoupper($text),
                    'lowercase', 'lower' => strtolower($text),
                    'title' => ucwords(strtolower($text)),
                    'trim' => trim($text),
                    'slug' => strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text), '-')),
                    default => $text,
                };
            }
        );

        // 3. Current Time tool
        $reg->register(
            'current_time',
            'Returns the current server time and timezone',
            ['type' => 'object', 'properties' => ['format' => ['type' => 'string']]],
            function (array $args): array {
                $format = (string)($args['format'] ?? 'Y-m-d H:i:s');
                return [
                    'timestamp' => time(),
                    'formatted' => date($format),
                    'timezone' => date_default_timezone_get(),
                ];
            }
        );

        return $reg;
    }

    protected function process(array $inputs): array
    {
        $toolName = (string)($inputs['tool_name'] ?? $this->getConfigValue('tool_name', ''));
        $args = (array)($inputs['arguments'] ?? $this->getConfigValue('default_arguments', []));

        // Merge input_data into args if provided
        if (isset($inputs['input_data']) && !empty($inputs['input_data'])) {
            if (is_array($inputs['input_data'])) {
                $args = array_merge($args, $inputs['input_data']);
            } else {
                $args['input'] = $inputs['input_data'];
            }
        }

        // Pass scalar inputs as arguments if tool expects them
        foreach ($inputs as $k => $v) {
            if (!in_array($k, ['tool_name', 'arguments', 'input_data'], true) && !isset($args[$k])) {
                $args[$k] = $v;
            }
        }

        $registry = $this->getToolRegistry();

        if (empty($toolName)) {
            return [
                'tool_result' => null,
                'status' => 'FAILED',
                'tool_error' => 'No tool_name specified for execution.',
                'tool_name' => '',
            ];
        }

        if (!$registry->has($toolName)) {
            return [
                'tool_result' => null,
                'status' => 'FAILED',
                'tool_error' => "Tool '{$toolName}' is not registered in ToolRegistry.",
                'tool_name' => $toolName,
            ];
        }

        try {
            $result = $registry->execute($toolName, $args);
            return [
                'tool_result' => $result,
                'status' => 'SUCCESS',
                'tool_error' => null,
                'tool_name' => $toolName,
            ];
        } catch (Throwable $e) {
            return [
                'tool_result' => null,
                'status' => 'FAILED',
                'tool_error' => $e->getMessage(),
                'tool_name' => $toolName,
            ];
        }
    }
}
