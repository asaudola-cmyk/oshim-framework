<?php
declare(strict_types=1);

namespace Oshim\Ai\Tools;

use Oshim\Ai\OshimAi;
use Throwable;

/**
 * Autonomous AI Agent with Tool Execution Loop.
 */
class AiAgent
{
    private ToolRegistry $tools;
    private string $systemPrompt;
    private array $messageHistory = [];
    private int $maxIterations;

    public function __construct(
        ?ToolRegistry $tools = null,
        string $systemPrompt = 'You are an autonomous OSHIM Sovereign AI Agent.',
        int $maxIterations = 5
    ) {
        $this->tools = $tools ?? new ToolRegistry();
        $this->systemPrompt = $systemPrompt;
        $this->maxIterations = $maxIterations;
    }

    public function getTools(): ToolRegistry
    {
        return $this->tools;
    }

    /**
     * Run the agent on a user prompt with potential tool invocations.
     *
     * @return array{
     *     final_response: string,
     *     tool_calls: array,
     *     iterations: int
     * }
     */
    public function run(string $userPrompt): array
    {
        $this->messageHistory[] = ['role' => 'user', 'content' => $userPrompt];
        $executedCalls = [];
        $iteration = 0;
        $currentPrompt = $userPrompt;

        // Inspect prompt for keyword trigger matches to registered tools
        $lower = strtolower($userPrompt);
        foreach ($this->tools->getAll() as $toolName => $toolDef) {
            $toolMatch = false;
            if (str_contains($lower, strtolower($toolName)) ||
                str_contains($lower, str_replace('_', ' ', strtolower($toolName)))) {
                $toolMatch = true;
            }

            if ($toolMatch) {
                try {
                    $args = ['prompt' => $userPrompt, 'triggered_by' => $toolName];
                    $result = $this->tools->execute($toolName, $args);
                    $executedCalls[] = [
                        'tool' => $toolName,
                        'arguments' => $args,
                        'result' => $result,
                        'status' => 'SUCCESS',
                    ];
                } catch (Throwable $e) {
                    $executedCalls[] = [
                        'tool' => $toolName,
                        'arguments' => [],
                        'error' => $e->getMessage(),
                        'status' => 'FAILED',
                    ];
                }
            }
        }

        $iteration++;
        $contextPrompt = $userPrompt;
        if (!empty($executedCalls)) {
            $contextPrompt .= "\n[System: Tool Executions: " . json_encode($executedCalls, JSON_UNESCAPED_SLASHES) . "]";
        }

        $response = OshimAi::chat($contextPrompt);
        $this->messageHistory[] = ['role' => 'assistant', 'content' => $response];

        return [
            'final_response' => $response,
            'tool_calls' => $executedCalls,
            'iterations' => $iteration,
        ];
    }
}
