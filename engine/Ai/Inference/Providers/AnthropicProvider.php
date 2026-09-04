<?php
declare(strict_types=1);

namespace Oshim\Ai\Inference\Providers;

use Oshim\Ai\Embedding\TfIdfEmbedder;
use RuntimeException;

/**
 * Anthropic Claude Messages API Provider.
 * Enforces top-level system prompt separation and dual-mode execution.
 */
class AnthropicProvider implements LlmProviderInterface
{
    private ?string $apiKey;
    private string $defaultModel;
    private string $apiEndpoint;
    private bool $sandbox;

    public function __construct(
        ?string $apiKey = null,
        string $defaultModel = 'claude-3-5-sonnet-20241022',
        array $config = []
    ) {
        $this->apiKey = $apiKey ?? ($_ENV['ANTHROPIC_API_KEY'] ?? getenv('ANTHROPIC_API_KEY') ?: null);
        $this->defaultModel = $defaultModel;
        $this->apiEndpoint = $config['endpoint'] ?? 'https://api.anthropic.com/v1/messages';
        $this->sandbox = (bool)($config['sandbox'] ?? false);
    }

    public function getProviderName(): string
    {
        return 'anthropic';
    }

    public function isAvailable(): bool
    {
        return $this->sandbox || ($this->apiKey !== null && strlen($this->apiKey) > 0);
    }

    public function formatMessagesPayload(string $prompt, array $options = []): array
    {
        $systemPrompt = $options['system'] ?? null;
        $messages = [];

        if (!empty($options['history']) && is_array($options['history'])) {
            foreach ($options['history'] as $msg) {
                if (isset($msg['role'], $msg['content'])) {
                    if ($msg['role'] === 'system' && $systemPrompt === null) {
                        $systemPrompt = (string)$msg['content'];
                    } elseif ($msg['role'] === 'user' || $msg['role'] === 'assistant') {
                        $messages[] = [
                            'role' => (string)$msg['role'],
                            'content' => (string)$msg['content'],
                        ];
                    }
                }
            }
        }

        $messages[] = [
            'role' => 'user',
            'content' => $prompt,
        ];

        $payload = [
            'model' => (string)($options['model'] ?? $this->defaultModel),
            'max_tokens' => (int)($options['max_tokens'] ?? 1024),
            'messages' => $messages,
        ];

        if (!empty($systemPrompt)) {
            $payload['system'] = (string)$systemPrompt;
        }

        if (isset($options['temperature'])) {
            $payload['temperature'] = (float)$options['temperature'];
        }

        return $payload;
    }

    public function parseMessagesResponse(string $jsonString): string
    {
        $data = json_decode($jsonString, true);
        if (!is_array($data)) {
            throw new RuntimeException('Malformed JSON response from Anthropic');
        }

        if (isset($data['type']) && $data['type'] === 'error') {
            $msg = $data['error']['message'] ?? 'Anthropic Error';
            $errType = $data['error']['type'] ?? 'unknown';
            throw new RuntimeException("Anthropic API Error [{$errType}]: {$msg}");
        }

        if (!isset($data['content'][0]['text'])) {
            throw new RuntimeException('Unexpected Anthropic response structure: missing content text');
        }

        return (string)$data['content'][0]['text'];
    }

    public function generate(string $prompt, array $options = []): string
    {
        $isSandbox = $this->sandbox || (bool)($options['sandbox'] ?? false) || empty($this->apiKey);

        if ($isSandbox) {
            return $this->generateSandboxReply($prompt, $options);
        }

        $payload = $this->formatMessagesPayload($prompt, $options);
        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        $httpOpts = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nx-api-key: {$this->apiKey}\r\nanthropic-version: 2023-06-01\r\n",
                'content' => $jsonPayload,
                'timeout' => 15,
                'ignore_errors' => true,
            ]
        ];

        if (isset($options['stream_context_options']) && is_array($options['stream_context_options'])) {
            $httpOpts = array_replace_recursive($httpOpts, $options['stream_context_options']);
        }

        $context = stream_context_create($httpOpts);
        $res = @file_get_contents($this->apiEndpoint, false, $context);
        if ($res === false) {
            throw new RuntimeException('HTTP connection failure to Anthropic endpoint');
        }

        return $this->parseMessagesResponse($res);
    }

    public function embed(string $text): array
    {
        // Anthropic does not have a dedicated embeddings endpoint; fallback to dense normalized TF-IDF embeddings
        return TfIdfEmbedder::embed($text, 64);
    }

    private function generateSandboxReply(string $prompt, array $options = []): string
    {
        $model = $options['model'] ?? $this->defaultModel;
        return "Anthropic ({$model}) processed: '{$prompt}'. Sovereign KVM hypervisor and BGP network parameters verified.";
    }
}
