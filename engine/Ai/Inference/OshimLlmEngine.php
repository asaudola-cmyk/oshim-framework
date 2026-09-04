<?php
declare(strict_types=1);

namespace Oshim\Ai\Inference;

use Oshim\Ai\Embedding\TfIdfEmbedder;
use Oshim\Ai\Inference\Providers\AnthropicProvider;
use Oshim\Ai\Inference\Providers\GeminiProvider;
use Oshim\Ai\Inference\Providers\LlmProviderInterface;
use Oshim\Ai\Inference\Providers\LocalGgufProvider;
use Oshim\Ai\Inference\Providers\OllamaProvider;
use Oshim\Ai\Inference\Providers\OpenAiProvider;
use Oshim\Ai\Tokenizer\GgufTokenizer;
use Throwable;

/**
 * Sovereign AI Inference Engine & Dynamic Multi-Provider Router.
 * Manages provider registries, dynamic model prefix routing,
 * priority fallback chains, chat history, and dense embeddings.
 */
class OshimLlmEngine
{
    private string $modelName;
    private float $temperature;
    private int $maxTokens;
    private ?string $primaryProvider = null;
    private ?string $systemPrompt = null;
    private array $history = [];
    private array $fallbackChain = [];

    /** @var array<string, LlmProviderInterface> */
    private array $providers = [];

    public function __construct(
        string $modelName = 'oshim-sovereign-7b',
        float $temperature = 0.7,
        int $maxTokens = 512,
        string|LlmProviderInterface $provider = 'auto',
        ?string $apiKey = null,
        ?string $apiEndpoint = null
    ) {
        $this->modelName = $modelName;
        $this->temperature = $temperature;
        $this->maxTokens = $maxTokens;

        // Always register sovereign local provider
        $this->registerProvider(new LocalGgufProvider($modelName));

        // Auto-discover and register standard providers
        $openAiKey = $apiKey ?? ($_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?: ($_ENV['OSHIM_AI_API_KEY'] ?? getenv('OSHIM_AI_API_KEY') ?: null));
        $openAiConfig = [];
        if ($apiEndpoint !== null) {
            $openAiConfig['endpoint'] = $apiEndpoint;
        }
        $this->registerProvider(new OpenAiProvider($openAiKey, 'gpt-4o-mini', $openAiConfig));

        $anthropicKey = $_ENV['ANTHROPIC_API_KEY'] ?? getenv('ANTHROPIC_API_KEY') ?: null;
        $this->registerProvider(new AnthropicProvider($anthropicKey));

        $geminiKey = $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY') ?: null;
        $this->registerProvider(new GeminiProvider($geminiKey));

        $ollamaHost = $_ENV['OLLAMA_HOST'] ?? getenv('OLLAMA_HOST') ?: null;
        $this->registerProvider(new OllamaProvider($ollamaHost));

        // Configure active provider
        if ($provider instanceof LlmProviderInterface) {
            $this->registerProvider($provider, true);
        } elseif (is_string($provider)) {
            $pLower = strtolower($provider);
            if ($pLower !== 'auto') {
                if ($pLower === 'cloud' || $pLower === 'openai') {
                    $this->primaryProvider = 'openai';
                } elseif (isset($this->providers[$pLower])) {
                    $this->primaryProvider = $pLower;
                } else {
                    $this->primaryProvider = $pLower;
                }
            } else {
                $this->primaryProvider = $openAiKey !== null ? 'openai' : 'local_gguf';
            }
        }

        // Default fallback chain
        $this->fallbackChain = ['openai', 'anthropic', 'gemini', 'ollama', 'local_gguf'];
    }

    public function registerProvider(LlmProviderInterface $provider, bool $setAsPrimary = false): static
    {
        $name = $provider->getProviderName();
        $this->providers[$name] = $provider;
        if ($setAsPrimary || $this->primaryProvider === null) {
            $this->primaryProvider = $name;
        }
        return $this;
    }

    public function hasProvider(string $name): bool
    {
        return isset($this->providers[$name]);
    }

    public function getProvider(string $name): ?LlmProviderInterface
    {
        return $this->providers[$name] ?? null;
    }

    public function getProviders(): array
    {
        return $this->providers;
    }

    public function setPrimaryProvider(string $name): static
    {
        $this->primaryProvider = $name;
        return $this;
    }

    public function setDefaultProvider(string $name): static
    {
        return $this->setPrimaryProvider($name);
    }

    public function getPrimaryProvider(): ?LlmProviderInterface
    {
        if ($this->primaryProvider !== null && isset($this->providers[$this->primaryProvider])) {
            return $this->providers[$this->primaryProvider];
        }
        return $this->providers['local_gguf'] ?? null;
    }

    public function setFallbackChain(array $chain): static
    {
        $this->fallbackChain = $chain;
        return $this;
    }

    public function getFallbackChain(): array
    {
        return $this->fallbackChain;
    }

    public function setSystemPrompt(?string $prompt): static
    {
        $this->systemPrompt = $prompt;
        return $this;
    }

    public function getSystemPrompt(): ?string
    {
        return $this->systemPrompt;
    }

    public function addMessage(string $role, string $content): static
    {
        $this->history[] = [
            'role' => $role,
            'content' => $content,
        ];
        return $this;
    }

    public function getHistory(): array
    {
        return $this->history;
    }

    public function getChatHistory(): array
    {
        return $this->getHistory();
    }

    public function clearHistory(): static
    {
        $this->history = [];
        return $this;
    }

    public function clearChatHistory(): static
    {
        return $this->clearHistory();
    }

    public function generate(string $prompt, array $options = []): array
    {
        $startTime = microtime(true);
        $model = (string)($options['model'] ?? $this->modelName);

        // 1. Determine initial target provider
        $targetProvider = $this->resolveTargetProvider($model, $options);

        // 2. Build prioritized candidate execution sequence
        $candidates = [$targetProvider];
        foreach ($this->fallbackChain as $fb) {
            if (!in_array($fb, $candidates, true)) {
                $candidates[] = $fb;
            }
        }
        if (!in_array('local_gguf', $candidates, true)) {
            $candidates[] = 'local_gguf';
        }

        $mergedOptions = array_merge([
            'model' => $model,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
            'system' => $this->systemPrompt,
            'history' => $this->history,
        ], $options);

        $reply = '';
        $usedProvider = 'local_gguf';
        $fallbackOccurred = false;

        // 3. Execute with seamless fallback
        foreach ($candidates as $idx => $candidateName) {
            $provider = $this->getOrInstantiateProvider($candidateName);
            if ($provider === null) {
                $fallbackOccurred = true;
                continue;
            }

            if (!$provider->isAvailable()) {
                $fallbackOccurred = true;
                continue;
            }

            try {
                $res = $provider->generate($prompt, $mergedOptions);
                if (!empty($res)) {
                    $reply = $res;
                    $usedProvider = $candidateName;
                    if ($idx > 0) {
                        $fallbackOccurred = true;
                    }
                    break;
                }
            } catch (Throwable) {
                $fallbackOccurred = true;
                continue;
            }
        }

        // Guaranteed fallback to local grounded synthesis if all failed
        if (empty($reply)) {
            $local = $this->providers['local_gguf'] ?? new LocalGgufProvider($model);
            $reply = $local->generate($prompt, $mergedOptions);
            $usedProvider = 'local_gguf';
            $fallbackOccurred = true;
        }

        $inputTokens = GgufTokenizer::encode($prompt);
        $outputTokens = GgufTokenizer::encode($reply);

        $elapsed = max(0.0001, microtime(true) - $startTime);
        $tokensPerSecond = round(count($outputTokens) / $elapsed, 2);

        // Record history
        $this->addMessage('user', $prompt);
        $this->addMessage('assistant', $reply);

        return [
            'model' => $model,
            'provider' => $usedProvider,
            'reply' => $reply,
            'input_tokens' => count($inputTokens),
            'output_tokens' => count($outputTokens),
            'tokens_per_second' => $tokensPerSecond,
            'inference_time_seconds' => round($elapsed, 4),
            'status' => 'COMPLETED',
            'fallback_occurred' => $fallbackOccurred,
            'fallback_used' => $fallbackOccurred,
        ];
    }

    public function generateEmbeddings(string $text, int $dimensions = 64): array
    {
        // Try active provider first
        $active = $this->getPrimaryProvider();
        if ($active !== null && $active->isAvailable()) {
            try {
                $vec = $active->embed($text);
                if (!empty($vec)) {
                    if (count($vec) === $dimensions) {
                        return $vec;
                    }
                    return TfIdfEmbedder::embed($text, $dimensions);
                }
            } catch (Throwable) {
                // Fallback to dense neural embeddings
            }
        }

        return GgufTokenizer::embed($text, $dimensions);
    }

    private function resolveTargetProvider(string $model, array $options): string
    {
        if (isset($options['provider']) && is_string($options['provider'])) {
            return strtolower($options['provider']);
        }

        $mLower = strtolower($model);
        if (str_starts_with($mLower, 'gpt-') || str_starts_with($mLower, 'text-davinci') || str_starts_with($mLower, 'o1-') || str_starts_with($mLower, 'o3-')) {
            return 'openai';
        }
        if (str_starts_with($mLower, 'claude-')) {
            return 'anthropic';
        }
        if (str_starts_with($mLower, 'gemini-')) {
            return 'gemini';
        }
        if (str_starts_with($mLower, 'llama') || str_starts_with($mLower, 'mistral') || str_starts_with($mLower, 'qwen')) {
            return 'ollama';
        }
        if (str_starts_with($mLower, 'oshim-')) {
            return 'local_gguf';
        }

        return $this->primaryProvider ?? 'local_gguf';
    }

    private function getOrInstantiateProvider(string $name): ?LlmProviderInterface
    {
        if (isset($this->providers[$name])) {
            return $this->providers[$name];
        }

        // Auto-instantiate standard providers in sandbox mode if requested
        $created = match ($name) {
            'openai' => new OpenAiProvider(null, 'gpt-4o-mini', ['sandbox' => true]),
            'anthropic' => new AnthropicProvider(null, 'claude-3-5-sonnet-20241022', ['sandbox' => true]),
            'gemini' => new GeminiProvider(null, 'gemini-1.5-flash', ['sandbox' => true]),
            'ollama' => new OllamaProvider(null, 'llama3.2', ['sandbox' => true]),
            'local_gguf' => new LocalGgufProvider($this->modelName),
            default => null,
        };

        if ($created !== null) {
            $this->providers[$name] = $created;
        }

        return $created;
    }
}
