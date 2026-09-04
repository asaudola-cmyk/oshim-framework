<?php
declare(strict_types=1);

namespace Tests\Unit\Ai;

use Oshim\Testing\TestCase;
use Oshim\Ai\Inference\OshimLlmEngine;
use Oshim\Ai\Inference\Providers\LlmProviderInterface;
use Oshim\Ai\Inference\Providers\OpenAiProvider;
use Oshim\Ai\Inference\Providers\AnthropicProvider;
use Oshim\Ai\Inference\Providers\GeminiProvider;
use Oshim\Ai\Inference\Providers\OllamaProvider;
use Oshim\Ai\Inference\Providers\LocalGgufProvider;
use RuntimeException;

/**
 * 👑 Comprehensive Multi-Provider AI Inference Test Suite
 */
final class AiMultiProviderTest extends TestCase
{
    // ==========================================
    // 1. Interface Contract & Canonical Names
    // ==========================================

    public function testAllProvidersImplementLlmProviderInterface(): void
    {
        $providers = [
            new OpenAiProvider('test-key', 'gpt-4o-mini', ['sandbox' => true]),
            new AnthropicProvider('test-key', 'claude-3-5-sonnet-20241022', ['sandbox' => true]),
            new GeminiProvider('test-key', 'gemini-1.5-flash', ['sandbox' => true]),
            new OllamaProvider('http://127.0.0.1:11434', 'llama3.2', ['sandbox' => true]),
            new LocalGgufProvider('oshim-sovereign-7b'),
        ];

        foreach ($providers as $provider) {
            $this->assertInstanceOf(LlmProviderInterface::class, $provider);
            $this->assertNotEmpty($provider->getProviderName());
            $this->assertIsBool($provider->isAvailable());
        }
    }

    public function testProviderNamesAreCanonicalAndDistinct(): void
    {
        $openai = new OpenAiProvider('test-key', 'gpt-4o-mini', ['sandbox' => true]);
        $anthropic = new AnthropicProvider('test-key', 'claude-3-5-sonnet-20241022', ['sandbox' => true]);
        $gemini = new GeminiProvider('test-key', 'gemini-1.5-flash', ['sandbox' => true]);
        $ollama = new OllamaProvider('http://127.0.0.1:11434', 'llama3.2', ['sandbox' => true]);
        $local = new LocalGgufProvider('oshim-sovereign-7b');

        $this->assertSame('openai', $openai->getProviderName());
        $this->assertSame('anthropic', $anthropic->getProviderName());
        $this->assertSame('gemini', $gemini->getProviderName());
        $this->assertSame('ollama', $ollama->getProviderName());
        $this->assertSame('local_gguf', $local->getProviderName());
    }

    // ==========================================
    // 2. OpenAI Provider Tests
    // ==========================================

    public function testOpenAiProviderGenerateSandboxResponse(): void
    {
        $provider = new OpenAiProvider('sk-test-key-12345', 'gpt-4o-mini', ['sandbox' => true]);
        $this->assertTrue($provider->isAvailable());

        $reply = $provider->generate('Deploy microVM container');
        $this->assertNotEmpty($reply);
        $this->assertIsString($reply);
        $this->assertStringContainsString('microVM', $reply);
    }

    public function testOpenAiProviderEmbeddingsSandbox(): void
    {
        $provider = new OpenAiProvider('sk-test-key-12345', 'gpt-4o-mini', ['sandbox' => true]);
        $vec = $provider->embed('Sovereign Cloud Infrastructure');

        $this->assertIsArray($vec);
        $this->assertNotEmpty($vec);
        $this->assertCount(64, $vec);
        $this->assertIsFloat($vec[0]);
    }

    public function testOpenAiProviderRequestFormattingWithSystemPrompt(): void
    {
        $provider = new OpenAiProvider('sk-test-key-12345', 'gpt-4o', ['sandbox' => true]);
        $payload = $provider->formatChatPayload('How many cores?', [
            'system' => 'You are a bare-metal hypervisor assistant.',
            'temperature' => 0.2,
            'max_tokens' => 128,
        ]);

        $this->assertSame('gpt-4o', $payload['model']);
        $this->assertCount(2, $payload['messages']);
        $this->assertSame('system', $payload['messages'][0]['role']);
        $this->assertSame('You are a bare-metal hypervisor assistant.', $payload['messages'][0]['content']);
        $this->assertSame('user', $payload['messages'][1]['role']);
        $this->assertSame('How many cores?', $payload['messages'][1]['content']);
        $this->assertSame(0.2, $payload['temperature']);
        $this->assertSame(128, $payload['max_tokens']);
    }

    public function testOpenAiProviderResponseParsing(): void
    {
        $provider = new OpenAiProvider('sk-test-key', 'gpt-4o-mini', ['sandbox' => true]);
        $mockJson = json_encode([
            'id' => 'chatcmpl-test-01',
            'choices' => [
                [
                    'message' => ['role' => 'assistant', 'content' => 'Server status: 100% HEALTHY'],
                    'finish_reason' => 'stop',
                ]
            ],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 8, 'total_tokens' => 18],
        ]);

        $parsed = $provider->parseChatResponse($mockJson);
        $this->assertSame('Server status: 100% HEALTHY', $parsed);
    }

    public function testOpenAiProviderHandlesErrorResponse(): void
    {
        $provider = new OpenAiProvider('sk-test-key', 'gpt-4o-mini', ['sandbox' => true]);
        $mockError = json_encode([
            'error' => [
                'message' => 'Invalid API key provided',
                'type' => 'invalid_request_error',
                'code' => 'invalid_api_key'
            ]
        ]);

        $this->assertThrows(function () use ($provider, $mockError) {
            $provider->parseChatResponse($mockError);
        }, RuntimeException::class);
    }

    // ==========================================
    // 3. Anthropic Provider Tests
    // ==========================================

    public function testAnthropicProviderGenerateSandboxResponse(): void
    {
        $provider = new AnthropicProvider('ant-test-key-99', 'claude-3-5-sonnet-20241022', ['sandbox' => true]);
        $this->assertTrue($provider->isAvailable());

        $reply = $provider->generate('Optimize KVM latency');
        $this->assertNotEmpty($reply);
        $this->assertIsString($reply);
        $this->assertStringContainsString('KVM', $reply);
    }

    public function testAnthropicProviderSystemPromptSeparation(): void
    {
        $provider = new AnthropicProvider('ant-test-key', 'claude-3-5-sonnet-20241022', ['sandbox' => true]);
        $payload = $provider->formatMessagesPayload('Check BGP routes', [
            'system' => 'Network engineering AI specialist.',
            'max_tokens' => 256,
        ]);

        $this->assertSame('claude-3-5-sonnet-20241022', $payload['model']);
        $this->assertSame('Network engineering AI specialist.', $payload['system']);
        $this->assertCount(1, $payload['messages']);
        $this->assertSame('user', $payload['messages'][0]['role']);
        $this->assertSame('Check BGP routes', $payload['messages'][0]['content']);
        $this->assertSame(256, $payload['max_tokens']);
    }

    public function testAnthropicProviderResponseParsing(): void
    {
        $provider = new AnthropicProvider('ant-test-key', 'claude-3-5-sonnet-20241022', ['sandbox' => true]);
        $mockJson = json_encode([
            'id' => 'msg_12345',
            'type' => 'message',
            'role' => 'assistant',
            'content' => [
                ['type' => 'text', 'text' => 'BGP routes verified: AS65001 active.']
            ],
            'stop_reason' => 'end_turn',
        ]);

        $parsed = $provider->parseMessagesResponse($mockJson);
        $this->assertSame('BGP routes verified: AS65001 active.', $parsed);
    }

    // ==========================================
    // 4. Gemini Provider Tests
    // ==========================================

    public function testGeminiProviderGenerateSandboxResponse(): void
    {
        $provider = new GeminiProvider('AIza-test-key', 'gemini-1.5-flash', ['sandbox' => true]);
        $this->assertTrue($provider->isAvailable());

        $reply = $provider->generate('Explain NVMe direct I/O');
        $this->assertNotEmpty($reply);
        $this->assertIsString($reply);
        $this->assertStringContainsString('NVMe', $reply);
    }

    public function testGeminiProviderContentsPartsPayloadFormatting(): void
    {
        $provider = new GeminiProvider('AIza-test-key', 'gemini-1.5-flash', ['sandbox' => true]);
        $payload = $provider->formatGeneratePayload('Explain zero-copy ring buffers', [
            'temperature' => 0.4,
            'max_tokens' => 512,
        ]);

        $this->assertArrayHasKey('contents', $payload);
        $this->assertCount(1, $payload['contents']);
        $this->assertSame('user', $payload['contents'][0]['role']);
        $this->assertSame('Explain zero-copy ring buffers', $payload['contents'][0]['parts'][0]['text']);
        $this->assertSame(0.4, $payload['generationConfig']['temperature']);
        $this->assertSame(512, $payload['generationConfig']['maxOutputTokens']);
    }

    public function testGeminiProviderResponseParsing(): void
    {
        $provider = new GeminiProvider('AIza-test-key', 'gemini-1.5-flash', ['sandbox' => true]);
        $mockJson = json_encode([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'Zero-copy ring buffer allocates contiguous memory mapped to kernel space.']
                        ],
                        'role' => 'model'
                    ],
                    'finishReason' => 'STOP'
                ]
            ]
        ]);

        $parsed = $provider->parseGenerateResponse($mockJson);
        $this->assertSame('Zero-copy ring buffer allocates contiguous memory mapped to kernel space.', $parsed);
    }

    // ==========================================
    // 5. Ollama Provider Tests
    // ==========================================

    public function testOllamaProviderGenerateSandboxResponse(): void
    {
        $provider = new OllamaProvider('http://127.0.0.1:11434', 'llama3.2', ['sandbox' => true]);
        $this->assertTrue($provider->isAvailable());

        $reply = $provider->generate('Check DNS zone serial');
        $this->assertNotEmpty($reply);
        $this->assertIsString($reply);
        $this->assertStringContainsString('DNS', $reply);
    }

    public function testOllamaProviderPayloadFormatting(): void
    {
        $provider = new OllamaProvider('http://127.0.0.1:11434', 'llama3.2', ['sandbox' => true]);
        $payload = $provider->formatGeneratePayload('Query cluster status', [
            'system' => 'Cluster manager bot',
            'temperature' => 0.1,
        ]);

        $this->assertSame('llama3.2', $payload['model']);
        $this->assertSame('Query cluster status', $payload['prompt']);
        $this->assertSame('Cluster manager bot', $payload['system']);
        $this->assertFalse($payload['stream']);
        $this->assertSame(0.1, $payload['options']['temperature']);
    }

    public function testOllamaProviderResponseParsing(): void
    {
        $provider = new OllamaProvider('http://127.0.0.1:11434', 'llama3.2', ['sandbox' => true]);
        $mockJson = json_encode([
            'model' => 'llama3.2',
            'response' => 'Cluster nodes: 4 online, 0 offline.',
            'done' => true,
            'total_duration' => 5000000,
        ]);

        $parsed = $provider->parseGenerateResponse($mockJson);
        $this->assertSame('Cluster nodes: 4 online, 0 offline.', $parsed);
    }

    // ==========================================
    // 6. Local GGUF Provider Tests
    // ==========================================

    public function testLocalGgufProviderAlwaysAvailableAndOffline(): void
    {
        $provider = new LocalGgufProvider('oshim-sovereign-7b');
        $this->assertTrue($provider->isAvailable());
        $this->assertSame('local_gguf', $provider->getProviderName());

        $reply = $provider->generate('Hello sovereign hypervisor');
        $this->assertNotEmpty($reply);
        $this->assertIsString($reply);

        $embedding = $provider->embed('Linux kernel FFI bridge');
        $this->assertIsArray($embedding);
        $this->assertCount(64, $embedding);
    }

    // ==========================================
    // 7. OshimLlmEngine Dynamic Routing & Resilient Fallback
    // ==========================================

    public function testEngineDynamicProviderRegistration(): void
    {
        $engine = new OshimLlmEngine('oshim-sovereign-7b');
        $openai = new OpenAiProvider('test-key', 'gpt-4o-mini', ['sandbox' => true]);
        $anthropic = new AnthropicProvider('test-key', 'claude-3-5-sonnet', ['sandbox' => true]);

        $engine->registerProvider($openai);
        $engine->registerProvider($anthropic);

        $this->assertTrue($engine->hasProvider('openai'));
        $this->assertTrue($engine->hasProvider('anthropic'));
        $this->assertSame($openai, $engine->getProvider('openai'));
        $this->assertSame($anthropic, $engine->getProvider('anthropic'));
    }

    public function testEngineDynamicRoutingByModelPrefix(): void
    {
        $engine = new OshimLlmEngine();
        $openai = new OpenAiProvider('test-key', 'gpt-4o-mini', ['sandbox' => true]);
        $anthropic = new AnthropicProvider('test-key', 'claude-3-5-sonnet', ['sandbox' => true]);

        $engine->registerProvider($openai);
        $engine->registerProvider($anthropic);

        $res1 = $engine->generate('Status', ['model' => 'gpt-4o']);
        $this->assertSame('openai', $res1['provider']);

        $res2 = $engine->generate('Status', ['model' => 'claude-3-5-sonnet']);
        $this->assertSame('anthropic', $res2['provider']);
    }

    public function testEngineExplicitProviderOptionSelection(): void
    {
        $engine = new OshimLlmEngine();
        $gemini = new GeminiProvider('test-key', 'gemini-1.5-flash', ['sandbox' => true]);
        $engine->registerProvider($gemini);

        $res = $engine->generate('Analyze memory leak', ['provider' => 'gemini']);
        $this->assertSame('gemini', $res['provider']);
        $this->assertNotEmpty($res['reply']);
    }

    public function testEngineMultiTurnHistoryManagement(): void
    {
        $engine = new OshimLlmEngine('oshim-sovereign-7b', 0.7, 512, 'local_gguf');

        $engine->generate('Hello, I am root user.');
        $engine->generate('Show system uptime.');

        $history = $engine->getHistory();
        $this->assertCount(4, $history); // 2 user turns, 2 assistant turns
        $this->assertSame('user', $history[0]['role']);
        $this->assertSame('assistant', $history[1]['role']);
        $this->assertSame('user', $history[2]['role']);
        $this->assertSame('assistant', $history[3]['role']);

        $engine->clearHistory();
        $this->assertEmpty($engine->getHistory());
    }

    public function testEngineAutomaticFallbackChainWhenPrimaryFails(): void
    {
        $engine = new OshimLlmEngine();

        // Create failing primary provider
        $failingProvider = new class implements LlmProviderInterface {
            public function getProviderName(): string { return 'failing_cloud'; }
            public function isAvailable(): bool { return true; }
            public function generate(string $prompt, array $options = []): string {
                throw new RuntimeException('API Rate Limit Exceeded (HTTP 429)');
            }
            public function embed(string $text): array {
                throw new RuntimeException('Embeddings service unavailable');
            }
        };

        // Create working fallback provider
        $backupProvider = new class implements LlmProviderInterface {
            public function getProviderName(): string { return 'backup_provider'; }
            public function isAvailable(): bool { return true; }
            public function generate(string $prompt, array $options = []): string {
                return 'Fallback successfully handled query: ' . $prompt;
            }
            public function embed(string $text): array {
                return array_fill(0, 64, 0.1);
            }
        };

        $engine->registerProvider($failingProvider);
        $engine->registerProvider($backupProvider);
        $engine->setFallbackChain(['failing_cloud', 'backup_provider', 'local_gguf']);

        $res = $engine->generate('Deploy container instance', ['provider' => 'failing_cloud']);

        $this->assertSame('COMPLETED', $res['status']);
        $this->assertSame('backup_provider', $res['provider']);
        $this->assertTrue($res['fallback_occurred'] ?? false);
        $this->assertStringContainsString('Fallback successfully handled', $res['reply']);
    }

    public function testEngineCascadesFallbackToLocalGguf(): void
    {
        $engine = new OshimLlmEngine();

        $failingProvider1 = new class implements LlmProviderInterface {
            public function getProviderName(): string { return 'failing_1'; }
            public function isAvailable(): bool { return true; }
            public function generate(string $prompt, array $options = []): string {
                throw new RuntimeException('Network timeout');
            }
            public function embed(string $text): array { return []; }
        };

        $engine->registerProvider($failingProvider1);
        $engine->setFallbackChain(['failing_1', 'local_gguf']);

        $res = $engine->generate('Verify cluster health', ['provider' => 'failing_1']);

        $this->assertSame('COMPLETED', $res['status']);
        $this->assertSame('local_gguf', $res['provider']);
        $this->assertNotEmpty($res['reply']);
    }
}
