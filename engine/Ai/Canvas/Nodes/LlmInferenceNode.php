<?php
declare(strict_types=1);

namespace Oshim\Ai\Canvas\Nodes;

use Oshim\Ai\Canvas\AbstractNode;
use Oshim\Ai\Inference\OshimLlmEngine;
use Oshim\Ai\OshimAi;

/**
 * LLM Inference Node: Connects with sovereign LLM inference engine and multi-provider backends.
 */
class LlmInferenceNode extends AbstractNode
{
    protected string $type = 'llm_inference';
    protected string $title = 'LLM Inference';

    protected function definePorts(): void
    {
        $this->registerInputPort('prompt', 'string', 'User or interpolated prompt', true, '');
        $this->registerInputPort('system_prompt', 'string', 'System instructions', false, null);
        $this->registerInputPort('context', 'any', 'Context or RAG document snippets', false, null);
        $this->registerInputPort('model', 'string', 'Model identifier', false, null);
        $this->registerInputPort('temperature', 'float', 'Sampling temperature (0.0 - 2.0)', false, null);
        $this->registerInputPort('max_tokens', 'int', 'Max tokens to generate', false, null);

        $this->registerOutputPort('reply', 'string', 'Generated text reply');
        $this->registerOutputPort('model', 'string', 'Model name used');
        $this->registerOutputPort('tokens', 'int', 'Total token count');
        $this->registerOutputPort('input_tokens', 'int', 'Input prompt token count');
        $this->registerOutputPort('output_tokens', 'int', 'Output token count');
        $this->registerOutputPort('latency_ms', 'float', 'Inference duration in ms');
        $this->registerOutputPort('status', 'string', 'Execution status');
    }

    protected function validateCustom(): void
    {
        $temp = $this->getConfigValue('temperature', 0.7);
        if (!is_numeric($temp) || $temp < 0 || $temp > 2.0) {
            $this->addError("Temperature must be a float between 0.0 and 2.0.");
        }
    }

    protected function process(array $inputs): array
    {
        $prompt = (string)($inputs['prompt'] ?? $inputs['user_query'] ?? $inputs['query'] ?? $this->getConfigValue('default_prompt', ''));
        $systemPrompt = $inputs['system_prompt'] ?? $this->getConfigValue('system_prompt', null);
        $model = (string)($inputs['model'] ?? $this->getConfigValue('model', 'oshim-sovereign-7b'));
        $temperature = (float)($inputs['temperature'] ?? $this->getConfigValue('temperature', 0.7));
        $maxTokens = (int)($inputs['max_tokens'] ?? $this->getConfigValue('max_tokens', 512));

        // Incorporate context if provided
        if (isset($inputs['context']) && !empty($inputs['context'])) {
            $contextText = is_array($inputs['context'])
                ? (isset($inputs['context']['rag_context']) ? (string)$inputs['context']['rag_context'] : json_encode($inputs['context'], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT))
                : (string)$inputs['context'];

            if (!empty($contextText)) {
                $prompt = "Context:\n" . $contextText . "\n\nQuery:\n" . $prompt;
            }
        }

        $engine = new OshimLlmEngine($model, $temperature, $maxTokens);
        if ($systemPrompt !== null && is_string($systemPrompt)) {
            $engine->setSystemPrompt($systemPrompt);
        }

        $result = $engine->generate($prompt, [
            'model' => $model,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'system' => $systemPrompt,
        ]);

        $reply = (string)($result['reply'] ?? '');
        $inputTokens = (int)($result['input_tokens'] ?? 0);
        $outputTokens = (int)($result['output_tokens'] ?? 0);
        $durationSeconds = (float)($result['inference_time_seconds'] ?? 0.001);

        return [
            'reply' => $reply,
            'model' => $result['model'] ?? $model,
            'provider' => $result['provider'] ?? 'local_gguf',
            'tokens' => $inputTokens + $outputTokens,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'latency_ms' => round($durationSeconds * 1000, 2),
            'status' => $result['status'] ?? 'COMPLETED',
        ];
    }
}
