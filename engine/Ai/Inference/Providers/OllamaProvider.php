<?php
declare(strict_types=1);

namespace Oshim\Ai\Inference\Providers;

use Oshim\Ai\Embedding\TfIdfEmbedder;
use RuntimeException;
use Throwable;

/**
 * Ollama Local AI Daemon Provider.
 * Supports /api/generate and /api/embeddings with zero-network sandbox mode.
 */
class OllamaProvider implements LlmProviderInterface
{
    private string $host;
    private string $defaultModel;
    private string $embeddingModel;
    private bool $sandbox;

    public function __construct(
        ?string $host = null,
        string $defaultModel = 'llama3.2',
        array $config = []
    ) {
        $this->host = $host ?? ($_ENV['OLLAMA_HOST'] ?? getenv('OLLAMA_HOST') ?: 'http://127.0.0.1:11434');
        $this->defaultModel = $defaultModel;
        $this->embeddingModel = $config['embedding_model'] ?? 'nomic-embed-text';
        $this->sandbox = (bool)($config['sandbox'] ?? false);
    }

    public function getProviderName(): string
    {
        return 'ollama';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function formatGeneratePayload(string $prompt, array $options = []): array
    {
        $payload = [
            'model' => (string)($options['model'] ?? $this->defaultModel),
            'prompt' => $prompt,
            'stream' => false,
            'options' => [
                'temperature' => (float)($options['temperature'] ?? 0.7),
                'num_predict' => (int)($options['max_tokens'] ?? 512),
            ]
        ];

        if (!empty($options['system'])) {
            $payload['system'] = (string)$options['system'];
        }

        return $payload;
    }

    public function parseGenerateResponse(string $jsonString): string
    {
        $data = json_decode($jsonString, true);
        if (!is_array($data)) {
            throw new RuntimeException('Malformed JSON response from Ollama daemon');
        }

        if (isset($data['error'])) {
            $msg = is_string($data['error']) ? $data['error'] : ($data['error']['message'] ?? 'Ollama Error');
            throw new RuntimeException("Ollama Daemon Error: {$msg}");
        }

        if (isset($data['response'])) {
            return (string)$data['response'];
        }

        if (isset($data['message']['content'])) {
            return (string)$data['message']['content'];
        }

        throw new RuntimeException('Unexpected Ollama response structure: missing response field');
    }

    public function parseEmbeddingsResponse(string $jsonString): array
    {
        $data = json_decode($jsonString, true);
        if (!is_array($data)) {
            throw new RuntimeException('Malformed JSON response from Ollama embeddings');
        }

        if (isset($data['error'])) {
            $msg = is_string($data['error']) ? $data['error'] : 'Ollama Embeddings Error';
            throw new RuntimeException("Ollama Embeddings Error: {$msg}");
        }

        if (isset($data['embedding']) && is_array($data['embedding'])) {
            return $data['embedding'];
        }

        if (isset($data['embeddings'][0]) && is_array($data['embeddings'][0])) {
            return $data['embeddings'][0];
        }

        throw new RuntimeException('Unexpected Ollama embeddings structure');
    }

    public function generate(string $prompt, array $options = []): string
    {
        $isSandbox = $this->sandbox || (bool)($options['sandbox'] ?? false);

        if ($isSandbox) {
            return $this->generateSandboxReply($prompt, $options);
        }

        $payload = $this->formatGeneratePayload($prompt, $options);
        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        $url = rtrim($this->host, '/') . '/api/generate';

        $httpOpts = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $jsonPayload,
                'timeout' => 15,
                'ignore_errors' => true,
            ]
        ];

        if (isset($options['stream_context_options']) && is_array($options['stream_context_options'])) {
            $httpOpts = array_replace_recursive($httpOpts, $options['stream_context_options']);
        }

        $context = stream_context_create($httpOpts);
        $res = @file_get_contents($url, false, $context);
        if ($res === false) {
            throw new RuntimeException('HTTP connection failure to Ollama daemon');
        }

        return $this->parseGenerateResponse($res);
    }

    public function embed(string $text): array
    {
        $isSandbox = $this->sandbox;

        if ($isSandbox) {
            return TfIdfEmbedder::embed($text, 64);
        }

        $url = rtrim($this->host, '/') . '/api/embeddings';
        $payload = [
            'model' => $this->embeddingModel,
            'prompt' => $text,
        ];
        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        $httpOpts = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $jsonPayload,
                'timeout' => 15,
                'ignore_errors' => true,
            ]
        ];

        $context = stream_context_create($httpOpts);
        $res = @file_get_contents($url, false, $context);
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
        return "Ollama ({$model}) local node executed prompt: '{$prompt}'. Sovereign DNS zone and cluster health verified.";
    }
}
