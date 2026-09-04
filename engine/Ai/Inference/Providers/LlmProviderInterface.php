<?php
declare(strict_types=1);

namespace Oshim\Ai\Inference\Providers;

/**
 * Universal interface contract for all LLM and embedding providers.
 */
interface LlmProviderInterface
{
    /**
     * Return the unique canonical name of the provider (e.g. 'openai', 'anthropic', 'gemini', 'ollama', 'local_gguf').
     */
    public function getProviderName(): string;

    /**
     * Return whether this provider is configured and available for inference.
     */
    public function isAvailable(): bool;

    /**
     * Generate text completion for a given prompt with optional runtime options.
     *
     * @param string $prompt
     * @param array<string, mixed> $options
     * @return string
     */
    public function generate(string $prompt, array $options = []): string;

    /**
     * Generate dense float vector embeddings for text.
     *
     * @param string $text
     * @return array<float>
     */
    public function embed(string $text): array;
}
