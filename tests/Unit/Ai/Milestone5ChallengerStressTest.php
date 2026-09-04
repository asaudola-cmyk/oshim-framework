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
use Oshim\Ai\Tokenizer\GgufTokenizer;
use Oshim\Ai\Tensor\MatrixMath;
use RuntimeException;
use Exception;
use Error;

/**
 * 👑 Milestone 5 Adversarial Challenger Stress Test Suite
 * Rigorously stress-tests OshimLlmEngine dynamic routing, multi-tier fallback chains,
 * chat history accumulation, vendor payload formatting, response parsing, and dense embeddings.
 */
final class Milestone5ChallengerStressTest extends TestCase
{
    // =========================================================================
    // 1. Dynamic Model Prefix Routing & Provider Selection Stress
    // =========================================================================

    public function testDynamicModelPrefixRoutingAcrossAllVendorFamilies(): void
    {
        $engine = new OshimLlmEngine();
        $openai = new OpenAiProvider('test-key', 'gpt-4o-mini', ['sandbox' => true]);
        $anthropic = new AnthropicProvider('test-key', 'claude-3-5-sonnet', ['sandbox' => true]);
        $gemini = new GeminiProvider('test-key', 'gemini-1.5-flash', ['sandbox' => true]);
        $ollama = new OllamaProvider('http://127.0.0.1:11434', 'llama3.2', ['sandbox' => true]);
        $local = new LocalGgufProvider('oshim-sovereign-7b');

        $engine->registerProvider($openai);
        $engine->registerProvider($anthropic);
        $engine->registerProvider($gemini);
        $engine->registerProvider($ollama);
        $engine->registerProvider($local);

        // OpenAI prefixes
        $openAiModels = ['gpt-4o', 'gpt-4o-mini', 'gpt-3.5-turbo', 'text-davinci-003', 'o1-preview', 'o3-mini'];
        foreach ($openAiModels as $m) {
            $res = $engine->generate('System status', ['model' => $m]);
            $this->assertSame('openai', $res['provider'], "Model '{$m}' must route to OpenAI provider");
            $this->assertSame('COMPLETED', $res['status']);
        }

        // Anthropic prefixes
        $anthropicModels = ['claude-3-5-sonnet-20241022', 'claude-3-opus-20240229', 'claude-3-haiku-20240307', 'claude-2.1'];
        foreach ($anthropicModels as $m) {
            $res = $engine->generate('Security check', ['model' => $m]);
            $this->assertSame('anthropic', $res['provider'], "Model '{$m}' must route to Anthropic provider");
            $this->assertSame('COMPLETED', $res['status']);
        }

        // Gemini prefixes
        $geminiModels = ['gemini-1.5-pro', 'gemini-1.5-flash', 'gemini-1.0-pro', 'gemini-2.0-flash-exp'];
        foreach ($geminiModels as $m) {
            $res = $engine->generate('Analyze topology', ['model' => $m]);
            $this->assertSame('gemini', $res['provider'], "Model '{$m}' must route to Gemini provider");
            $this->assertSame('COMPLETED', $res['status']);
        }

        // Ollama prefixes
        $ollamaModels = ['llama3:8b', 'llama3.2:1b', 'mistral-7b-instruct', 'qwen2.5-coder:7b'];
        foreach ($ollamaModels as $m) {
            $res = $engine->generate('Local query', ['model' => $m]);
            $this->assertSame('ollama', $res['provider'], "Model '{$m}' must route to Ollama provider");
            $this->assertSame('COMPLETED', $res['status']);
        }

        // Local GGUF sovereign prefixes
        $localModels = ['oshim-7b', 'oshim-sovereign-7b', 'oshim-moe-14b'];
        foreach ($localModels as $m) {
            $res = $engine->generate('Kernel syscalls', ['model' => $m]);
            $this->assertSame('local_gguf', $res['provider'], "Model '{$m}' must route to Local GGUF provider");
            $this->assertSame('COMPLETED', $res['status']);
        }
    }

    public function testExplicitProviderOverridePreemptsModelPrefix(): void
    {
        $engine = new OshimLlmEngine();
        $openai = new OpenAiProvider('test-key', 'gpt-4o-mini', ['sandbox' => true]);
        $anthropic = new AnthropicProvider('test-key', 'claude-3-5-sonnet', ['sandbox' => true]);
        $gemini = new GeminiProvider('test-key', 'gemini-1.5-flash', ['sandbox' => true]);

        $engine->registerProvider($openai);
        $engine->registerProvider($anthropic);
        $engine->registerProvider($gemini);

        // Model prefix says 'gpt-4o' (OpenAI), but options say provider='gemini'
        $res = $engine->generate('Inspect packet', ['model' => 'gpt-4o', 'provider' => 'gemini']);
        $this->assertSame('gemini', $res['provider'], "Explicit provider option must override model prefix");
        $this->assertSame('COMPLETED', $res['status']);

        // Model prefix says 'claude-3' (Anthropic), but options say provider='openai'
        $res2 = $engine->generate('Inspect packet', ['model' => 'claude-3-5-sonnet', 'provider' => 'openai']);
        $this->assertSame('openai', $res2['provider'], "Explicit provider option must override model prefix");
        $this->assertSame('COMPLETED', $res2['status']);
    }

    public function testUnknownModelPrefixDefaultsToPrimaryOrLocal(): void
    {
        $engine = new OshimLlmEngine('oshim-sovereign-7b');
        $openai = new OpenAiProvider('test-key', 'gpt-4o-mini', ['sandbox' => true]);
        $engine->registerProvider($openai);
        $engine->setPrimaryProvider('openai');

        $res = $engine->generate('Query', ['model' => 'deepseek-coder-v2']);
        $this->assertSame('openai', $res['provider'], "Unknown model prefix must use primary provider");

        $engine->setPrimaryProvider('local_gguf');
        $res2 = $engine->generate('Query', ['model' => 'custom-neural-net']);
        $this->assertSame('local_gguf', $res2['provider'], "Unknown model prefix must use local_gguf when primary");
    }

    // =========================================================================
    // 2. Cascading Fallback Chains & Multi-Tier Fault Tolerance
    // =========================================================================

    public function testFourTierCascadingFallbackChainSuccess(): void
    {
        $engine = new OshimLlmEngine();

        // Level 1: Primary fails with HTTP 429 Rate Limit
        $level1 = new class implements LlmProviderInterface {
            public function getProviderName(): string { return 'tier1_openai'; }
            public function isAvailable(): bool { return true; }
            public function generate(string $prompt, array $options = []): string {
                throw new RuntimeException('HTTP 429: Rate limit exceeded');
            }
            public function embed(string $text): array { return []; }
        };

        // Level 2: Secondary returns empty string response
        $level2 = new class implements LlmProviderInterface {
            public function getProviderName(): string { return 'tier2_anthropic'; }
            public function isAvailable(): bool { return true; }
            public function generate(string $prompt, array $options = []): string {
                return ''; // Triggers fallback
            }
            public function embed(string $text): array { return []; }
        };

        // Level 3: Tertiary is marked unavailable
        $level3 = new class implements LlmProviderInterface {
            public function getProviderName(): string { return 'tier3_gemini'; }
            public function isAvailable(): bool { return false; }
            public function generate(string $prompt, array $options = []): string {
                return 'Should not be called';
            }
            public function embed(string $text): array { return []; }
        };

        // Level 4: Quaternary throws fatal Exception
        $level4 = new class implements LlmProviderInterface {
            public function getProviderName(): string { return 'tier4_ollama'; }
            public function isAvailable(): bool { return true; }
            public function generate(string $prompt, array $options = []): string {
                throw new Exception('Connection reset by peer');
            }
            public function embed(string $text): array { return []; }
        };

        // Level 5: Resilient Local sovereign backup
        $level5 = new class implements LlmProviderInterface {
            public function getProviderName(): string { return 'tier5_sovereign_local'; }
            public function isAvailable(): bool { return true; }
            public function generate(string $prompt, array $options = []): string {
                return 'Resilient sovereign cluster response for: ' . $prompt;
            }
            public function embed(string $text): array { return array_fill(0, 64, 0.1); }
        };

        $engine->registerProvider($level1);
        $engine->registerProvider($level2);
        $engine->registerProvider($level3);
        $engine->registerProvider($level4);
        $engine->registerProvider($level5);

        $engine->setFallbackChain([
            'tier1_openai',
            'tier2_anthropic',
            'tier3_gemini',
            'tier4_ollama',
            'tier5_sovereign_local',
        ]);

        $res = $engine->generate('Deploy cluster mesh', ['provider' => 'tier1_openai']);

        $this->assertSame('COMPLETED', $res['status']);
        $this->assertSame('tier5_sovereign_local', $res['provider']);
        $this->assertTrue($res['fallback_occurred']);
        $this->assertTrue($res['fallback_used']);
        $this->assertStringContainsString('Resilient sovereign cluster response', $res['reply']);
        $this->assertTrue($res['input_tokens'] > 0);
        $this->assertTrue($res['output_tokens'] > 0);
        $this->assertTrue($res['tokens_per_second'] > 0);
        $this->assertTrue($res['inference_time_seconds'] >= 0.0);
    }

    public function testFallbackOrderPreservationAndShortCircuiting(): void
    {
        $engine = new OshimLlmEngine();

        $executionOrder = [];

        $p1 = new class($executionOrder) implements LlmProviderInterface {
            private array $order;
            public function __construct(array &$order) { $this->order = &$order; }
            public function getProviderName(): string { return 'order_p1'; }
            public function isAvailable(): bool { return true; }
            public function generate(string $prompt, array $options = []): string {
                $this->order[] = 'order_p1';
                throw new RuntimeException('P1 failed');
            }
            public function embed(string $text): array { return []; }
        };

        $p2 = new class($executionOrder) implements LlmProviderInterface {
            private array $order;
            public function __construct(array &$order) { $this->order = &$order; }
            public function getProviderName(): string { return 'order_p2'; }
            public function isAvailable(): bool { return true; }
            public function generate(string $prompt, array $options = []): string {
                $this->order[] = 'order_p2';
                return 'P2 succeeded!';
            }
            public function embed(string $text): array { return []; }
        };

        $p3 = new class($executionOrder) implements LlmProviderInterface {
            private array $order;
            public function __construct(array &$order) { $this->order = &$order; }
            public function getProviderName(): string { return 'order_p3'; }
            public function isAvailable(): bool { return true; }
            public function generate(string $prompt, array $options = []): string {
                $this->order[] = 'order_p3';
                return 'P3 succeeded!';
            }
            public function embed(string $text): array { return []; }
        };

        $engine->registerProvider($p1);
        $engine->registerProvider($p2);
        $engine->registerProvider($p3);

        $engine->setFallbackChain(['order_p1', 'order_p2', 'order_p3']);

        $res = $engine->generate('Test order', ['provider' => 'order_p1']);

        $this->assertSame('order_p2', $res['provider']);
        $this->assertSame('P2 succeeded!', $res['reply']);
        $this->assertSame(['order_p1', 'order_p2'], $executionOrder, "P3 must not be executed after P2 succeeds");
    }

    public function testPrimarySuccessProducesNoFallbackFlag(): void
    {
        $engine = new OshimLlmEngine();
        $openai = new OpenAiProvider('test-key', 'gpt-4o-mini', ['sandbox' => true]);
        $engine->registerProvider($openai);

        $res = $engine->generate('Ping', ['provider' => 'openai']);
        $this->assertSame('openai', $res['provider']);
        $this->assertFalse($res['fallback_occurred'], "Successful primary provider must have fallback_occurred=false");
        $this->assertFalse($res['fallback_used'], "Successful primary provider must have fallback_used=false");
    }

    // =========================================================================
    // 3. Multi-Turn Chat History Accumulation & System Prompt Injection
    // =========================================================================

    public function testMultiTurnChatHistorySequentialAccumulation(): void
    {
        $engine = new OshimLlmEngine('oshim-sovereign-7b', 0.7, 512, 'local_gguf');
        $this->assertEmpty($engine->getHistory());
        $this->assertEmpty($engine->getChatHistory());

        $engine->generate('Hello, I am node operator 01.');
        $history1 = $engine->getHistory();
        $this->assertCount(2, $history1);
        $this->assertSame('user', $history1[0]['role']);
        $this->assertSame('Hello, I am node operator 01.', $history1[0]['content']);
        $this->assertSame('assistant', $history1[1]['role']);
        $this->assertNotEmpty($history1[1]['content']);

        $engine->generate('What is the current hypervisor status?');
        $history2 = $engine->getHistory();
        $this->assertCount(4, $history2);
        $this->assertSame('user', $history2[2]['role']);
        $this->assertSame('What is the current hypervisor status?', $history2[2]['content']);
        $this->assertSame('assistant', $history2[3]['role']);

        $engine->generate('Allocate 4 cores.');
        $history3 = $engine->getHistory();
        $this->assertCount(6, $history3);

        // Test clearChatHistory
        $engine->clearChatHistory();
        $this->assertEmpty($engine->getHistory());
        $this->assertEmpty($engine->getChatHistory());
    }

    public function testSystemPromptPropagationAcrossAllProviders(): void
    {
        $systemText = 'You are a sovereign Linux microVM kernel assistant.';

        // OpenAI: system prompt formatted as system message
        $openAi = new OpenAiProvider('test-key', 'gpt-4o', ['sandbox' => true]);
        $openAiPayload = $openAi->formatChatPayload('Verify memory', [
            'system' => $systemText,
            'temperature' => 0.3,
            'max_tokens' => 256,
        ]);
        $this->assertSame('system', $openAiPayload['messages'][0]['role']);
        $this->assertSame($systemText, $openAiPayload['messages'][0]['content']);
        $this->assertSame(0.3, $openAiPayload['temperature']);
        $this->assertSame(256, $openAiPayload['max_tokens']);

        // Anthropic: system prompt separated to top-level 'system' key
        $anthropic = new AnthropicProvider('test-key', 'claude-3-5-sonnet', ['sandbox' => true]);
        $anthropicPayload = $anthropic->formatMessagesPayload('Verify memory', [
            'system' => $systemText,
            'max_tokens' => 512,
            'temperature' => 0.5,
        ]);
        $this->assertSame($systemText, $anthropicPayload['system']);
        $this->assertSame(512, $anthropicPayload['max_tokens']);
        $this->assertSame(0.5, $anthropicPayload['temperature']);
        // Verify messages list only contains user message
        $this->assertCount(1, $anthropicPayload['messages']);
        $this->assertSame('user', $anthropicPayload['messages'][0]['role']);

        // Gemini: system prompt formatted in systemInstruction.parts
        $gemini = new GeminiProvider('test-key', 'gemini-1.5-flash', ['sandbox' => true]);
        $geminiPayload = $gemini->formatGeneratePayload('Verify memory', [
            'system' => $systemText,
            'temperature' => 0.2,
            'max_tokens' => 128,
        ]);
        $this->assertArrayHasKey('systemInstruction', $geminiPayload);
        $this->assertSame($systemText, $geminiPayload['systemInstruction']['parts'][0]['text']);
        $this->assertSame(0.2, $geminiPayload['generationConfig']['temperature']);
        $this->assertSame(128, $geminiPayload['generationConfig']['maxOutputTokens']);

        // Ollama: system prompt formatted in top-level system parameter
        $ollama = new OllamaProvider('http://127.0.0.1:11434', 'llama3.2', ['sandbox' => true]);
        $ollamaPayload = $ollama->formatGeneratePayload('Verify memory', [
            'system' => $systemText,
            'temperature' => 0.1,
            'max_tokens' => 64,
        ]);
        $this->assertSame($systemText, $ollamaPayload['system']);
        $this->assertSame(0.1, $ollamaPayload['options']['temperature']);
        $this->assertSame(64, $ollamaPayload['options']['num_predict']);
    }

    public function testAnthropicHistorySystemMessageExtraction(): void
    {
        $anthropic = new AnthropicProvider('test-key', 'claude-3-5-sonnet', ['sandbox' => true]);

        // History includes an embedded system message
        $history = [
            ['role' => 'system', 'content' => 'Top secret hypervisor instructions'],
            ['role' => 'user', 'content' => 'Hello server'],
            ['role' => 'assistant', 'content' => 'Server ready'],
        ];

        $payload = $anthropic->formatMessagesPayload('Show stats', ['history' => $history]);

        $this->assertSame('Top secret hypervisor instructions', $payload['system']);
        // messages should contain user, assistant, user (3 items total, no system role)
        $this->assertCount(3, $payload['messages']);
        foreach ($payload['messages'] as $m) {
            $this->assertNotSame('system', $m['role'], "Anthropic messages array must never contain 'system' role");
        }
    }

    // =========================================================================
    // 4. Dual-Mode Execution & Response Parser Robustness Stress
    // =========================================================================

    public function testResponseParsersHandleMalformedJsonGracefully(): void
    {
        $openai = new OpenAiProvider('test-key', 'gpt-4o-mini', ['sandbox' => true]);
        $anthropic = new AnthropicProvider('test-key', 'claude-3-5-sonnet', ['sandbox' => true]);
        $gemini = new GeminiProvider('test-key', 'gemini-1.5-flash', ['sandbox' => true]);
        $ollama = new OllamaProvider('http://127.0.0.1:11434', 'llama3.2', ['sandbox' => true]);

        $malformedJson = "<html><body>502 Bad Gateway</body></html>";

        $this->assertThrows(fn() => $openai->parseChatResponse($malformedJson), RuntimeException::class);
        $this->assertThrows(fn() => $openai->parseEmbeddingsResponse($malformedJson), RuntimeException::class);
        $this->assertThrows(fn() => $anthropic->parseMessagesResponse($malformedJson), RuntimeException::class);
        $this->assertThrows(fn() => $gemini->parseGenerateResponse($malformedJson), RuntimeException::class);
        $this->assertThrows(fn() => $gemini->parseEmbedResponse($malformedJson), RuntimeException::class);
        $this->assertThrows(fn() => $ollama->parseGenerateResponse($malformedJson), RuntimeException::class);
        $this->assertThrows(fn() => $ollama->parseEmbeddingsResponse($malformedJson), RuntimeException::class);
    }

    public function testResponseParsersHandleVendorSpecificApiErrors(): void
    {
        $openai = new OpenAiProvider('test-key', 'gpt-4o-mini', ['sandbox' => true]);
        $openAiErr = json_encode(['error' => ['message' => 'Quota exceeded', 'code' => 'insufficient_quota']]);
        $this->assertThrows(fn() => $openai->parseChatResponse($openAiErr), RuntimeException::class);

        $anthropic = new AnthropicProvider('test-key', 'claude-3-5-sonnet', ['sandbox' => true]);
        $anthropicErr = json_encode(['type' => 'error', 'error' => ['type' => 'authentication_error', 'message' => 'Invalid API Key']]);
        $this->assertThrows(fn() => $anthropic->parseMessagesResponse($anthropicErr), RuntimeException::class);

        $gemini = new GeminiProvider('test-key', 'gemini-1.5-flash', ['sandbox' => true]);
        $geminiErr = json_encode(['error' => ['code' => 403, 'message' => 'API key expired']]);
        $this->assertThrows(fn() => $gemini->parseGenerateResponse($geminiErr), RuntimeException::class);

        $ollama = new OllamaProvider('http://127.0.0.1:11434', 'llama3.2', ['sandbox' => true]);
        $ollamaErr = json_encode(['error' => 'model "llama3.2" not found']);
        $this->assertThrows(fn() => $ollama->parseGenerateResponse($ollamaErr), RuntimeException::class);
    }

    public function testOfflineSandboxModeProducesDeterministicResponses(): void
    {
        $openai = new OpenAiProvider('', 'gpt-4o-mini');
        $anthropic = new AnthropicProvider(null, 'claude-3-5-sonnet');
        $gemini = new GeminiProvider(null, 'gemini-1.5-flash');
        $ollama = new OllamaProvider('http://127.0.0.1:11434', 'llama3.2', ['sandbox' => true]);

        $prompt = 'Verify sovereign cloud kernel';

        $rOpenAi = $openai->generate($prompt);
        $this->assertStringContainsString('OpenAI', $rOpenAi);
        $this->assertStringContainsString($prompt, $rOpenAi);

        $rAnthropic = $anthropic->generate($prompt);
        $this->assertStringContainsString('Anthropic', $rAnthropic);
        $this->assertStringContainsString($prompt, $rAnthropic);

        $rGemini = $gemini->generate($prompt);
        $this->assertStringContainsString('Gemini', $rGemini);
        $this->assertStringContainsString($prompt, $rGemini);

        $rOllama = $ollama->generate($prompt);
        $this->assertStringContainsString('Ollama', $rOllama);
        $this->assertStringContainsString($prompt, $rOllama);
    }

    // =========================================================================
    // 5. GGUF BPE, SentencePiece & Dense Neural Embedding Stress
    // =========================================================================

    public function testHighVolumeTokenizationThroughputAndIntegrity(): void
    {
        GgufTokenizer::reset();

        // 10,000 character synthetic infrastructure log payload
        $repeatedChunk = " [INFO] OSHIM Sovereign MicroVM instance-8891 booting KVM kernel with 16GB RAM and 8 vCPUs. ";
        $largeText = str_repeat($repeatedChunk, 100);

        $start = microtime(true);
        $tokenIds = GgufTokenizer::encode($largeText);
        $elapsed = microtime(true) - $start;

        $this->assertNotEmpty($tokenIds);
        $this->assertTrue(count($tokenIds) > 500, "Should generate > 500 tokens for 10KB text");
        $this->assertTrue($elapsed < 0.200, "10KB tokenization should finish under 200ms (took {$elapsed}s)");

        $decoded = GgufTokenizer::decode($tokenIds);
        $this->assertStringContainsString('OSHIM Sovereign MicroVM', $decoded);
        $this->assertStringContainsString('KVM kernel', $decoded);
    }

    public function testDenseNeuralEmbeddingsAcrossMultipleDimensionsAndUnitNorm(): void
    {
        GgufTokenizer::reset();
        $text = 'Sovereign bare-metal Linux MicroVM virtualization';

        $dims = [32, 64, 128, 256, 512, 768];
        foreach ($dims as $d) {
            $vec = GgufTokenizer::embed($text, $d);
            $this->assertCount($d, $vec, "Embedding vector must have exact dimension {$d}");

            $magnitude = MatrixMath::vectorMagnitude($vec);
            $this->assertTrue(
                abs($magnitude - 1.0) < 1e-4,
                "Embedding vector of dimension {$d} must be L2 normalized to unit length 1.0 (got {$magnitude})"
            );
        }
    }

    public function testEngineDenseEmbeddingDelegation(): void
    {
        $engine = new OshimLlmEngine('oshim-sovereign-7b');
        $vec = $engine->generateEmbeddings('Enterprise high availability cloud virtualization', 64);

        $this->assertIsArray($vec);
        $this->assertCount(64, $vec);
        $magnitude = MatrixMath::vectorMagnitude($vec);
        $this->assertTrue(abs($magnitude - 1.0) < 1e-4);
    }

    // =========================================================================
    // 6. Edge Cases & Boundary Conditions
    // =========================================================================

    public function testEmptyPromptHandling(): void
    {
        $engine = new OshimLlmEngine('oshim-sovereign-7b');
        $res = $engine->generate('');

        $this->assertSame('COMPLETED', $res['status']);
        $this->assertIsString($res['reply']);
        $this->assertSame(0, $res['input_tokens']);
    }

    public function testMultilingualBanglaAndUnicodeGroundedRagInference(): void
    {
        $local = new LocalGgufProvider('oshim-sovereign-7b');

        // Test Grounded RAG query
        $ragPrompt = "Context:\nসার্ভার সিপিইউ ইউটিলাইজেশন বর্তমানে ৪৫ শতাংশ।\nQuestion: সার্ভার সিপিইউ কত?";
        $ragReply = $local->generate($ragPrompt);

        $this->assertStringContainsString('নলেজ বেস অনুযায়ী', $ragReply);
        $this->assertStringContainsString('৪৫ শতাংশ', $ragReply);

        // Test Bangla greeting
        $greetingReply = $local->generate('হ্যালো, আপনি কেমন আছেন?');
        $this->assertStringContainsString('হ্যালো! আমি OSHIM Sovereign AI', $greetingReply);
    }

    public function testEngineHandlesCustomExceptionTypesInFallback(): void
    {
        $engine = new OshimLlmEngine();

        $errorProvider = new class implements LlmProviderInterface {
            public function getProviderName(): string { return 'error_provider'; }
            public function isAvailable(): bool { return true; }
            public function generate(string $prompt, array $options = []): string {
                throw new Error('Fatal PHP internal engine error simulation');
            }
            public function embed(string $text): array { return []; }
        };

        $engine->registerProvider($errorProvider);
        $engine->setFallbackChain(['error_provider', 'local_gguf']);

        $res = $engine->generate('Fatal simulation', ['provider' => 'error_provider']);
        $this->assertSame('COMPLETED', $res['status']);
        $this->assertSame('local_gguf', $res['provider']);
        $this->assertTrue($res['fallback_occurred']);
    }
}
