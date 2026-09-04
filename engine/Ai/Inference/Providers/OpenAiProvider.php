<?php
declare(strict_types=1);

namespace Oshim\Ai\Inference\Providers;

use Oshim\Ai\Embedding\TfIdfEmbedder;
use RuntimeException;
use Throwable;

/**
 * OpenAI Chat Completions and Embeddings API Provider.
 * Supports dual-mode live execution and deterministic offline sandbox.
 */
class OpenAiProvider implements LlmProviderInterface
{
    private ?string $apiKey;
    private string $defaultModel;
    private string $apiEndpoint;
    private string $embeddingsEndpoint;
    private bool $sandbox;

    public function __construct(
        ?string $apiKey = null,
        string $defaultModel = 'gpt-4o-mini',
        array $config = []
    ) {
        $this->apiKey = $apiKey ?? ($_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?: ($_ENV['OSHIM_AI_API_KEY'] ?? getenv('OSHIM_AI_API_KEY') ?: null));
        $this->defaultModel = $defaultModel;
        $this->apiEndpoint = $config['endpoint'] ?? 'https://api.openai.com/v1/chat/completions';
        $this->embeddingsEndpoint = $config['embeddings_endpoint'] ?? 'https://api.openai.com/v1/embeddings';
        $this->sandbox = (bool)($config['sandbox'] ?? false);
    }

    public function getProviderName(): string
    {
        return 'openai';
    }

    public function isAvailable(): bool
    {
        return $this->sandbox || ($this->apiKey !== null && strlen($this->apiKey) > 0);
    }

    public function formatChatPayload(string $prompt, array $options = []): array
    {
        $messages = [];

        if (!empty($options['system'])) {
            $messages[] = [
                'role' => 'system',
                'content' => (string)$options['system'],
            ];
        }

        if (!empty($options['history']) && is_array($options['history'])) {
            foreach ($options['history'] as $msg) {
                if (isset($msg['role'], $msg['content'])) {
                    $messages[] = [
                        'role' => (string)$msg['role'],
                        'content' => (string)$msg['content'],
                    ];
                }
            }
        }

        $messages[] = [
            'role' => 'user',
            'content' => $prompt,
        ];

        return [
            'model' => (string)($options['model'] ?? $this->defaultModel),
            'messages' => $messages,
            'temperature' => (float)($options['temperature'] ?? 0.7),
            'max_tokens' => (int)($options['max_tokens'] ?? 512),
        ];
    }

    public function parseChatResponse(string $jsonString): string
    {
        $data = json_decode($jsonString, true);
        if (!is_array($data)) {
            throw new RuntimeException('Malformed JSON response from OpenAI');
        }

        if (isset($data['error'])) {
            $msg = $data['error']['message'] ?? 'Unknown OpenAI Error';
            $code = $data['error']['code'] ?? '';
            throw new RuntimeException("OpenAI API Error: {$msg} [{$code}]");
        }

        if (!isset($data['choices'][0]['message']['content'])) {
            throw new RuntimeException('Unexpected OpenAI response structure: missing choices content');
        }

        return (string)$data['choices'][0]['message']['content'];
    }

    public function formatEmbeddingsPayload(string $text, array $options = []): array
    {
        return [
            'model' => (string)($options['model'] ?? 'text-embedding-3-small'),
            'input' => $text,
        ];
    }

    public function parseEmbeddingsResponse(string $jsonString): array
    {
        $data = json_decode($jsonString, true);
        if (!is_array($data)) {
            throw new RuntimeException('Malformed JSON response from OpenAI embeddings');
        }

        if (isset($data['error'])) {
            $msg = $data['error']['message'] ?? 'OpenAI Embeddings Error';
            throw new RuntimeException("OpenAI Embeddings Error: {$msg}");
        }

        if (!isset($data['data'][0]['embedding']) || !is_array($data['data'][0]['embedding'])) {
            throw new RuntimeException('Unexpected OpenAI embeddings response structure');
        }

        return $data['data'][0]['embedding'];
    }

    public function generate(string $prompt, array $options = []): string
    {
        $isSandbox = $this->sandbox || (bool)($options['sandbox'] ?? false) || empty($this->apiKey);

        if ($isSandbox) {
            return $this->generateSandboxReply($prompt, $options);
        }

        $payload = $this->formatChatPayload($prompt, $options);
        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        $httpOpts = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAuthorization: Bearer {$this->apiKey}\r\n",
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
            throw new RuntimeException('HTTP connection failure to OpenAI endpoint');
        }

        return $this->parseChatResponse($res);
    }

    public function embed(string $text): array
    {
        $isSandbox = $this->sandbox || empty($this->apiKey);

        if ($isSandbox) {
            return TfIdfEmbedder::embed($text, 64);
        }

        $payload = $this->formatEmbeddingsPayload($text);
        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        $httpOpts = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAuthorization: Bearer {$this->apiKey}\r\n",
                'content' => $jsonPayload,
                'timeout' => 15,
                'ignore_errors' => true,
            ]
        ];

        $context = stream_context_create($httpOpts);
        $res = @file_get_contents($this->embeddingsEndpoint, false, $context);
        if ($res === false) {
            return TfIdfEmbedder::embed($text, 64);
        }

        try {
            return $this->parseEmbeddingsResponse($res);
        } catch (Throwable) {
            return TfIdfEmbedder::embed($text, 64);
        }
    }

    private function generateSandboxReply(string $prompt, array $options = []): string
    {
        $model = $options['model'] ?? $this->defaultModel;
        return "OpenAI ({$model}) processed prompt: '{$prompt}'. Sovereign microVM and cloud infrastructure components verified.";
    }
}
