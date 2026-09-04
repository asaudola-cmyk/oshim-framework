<?php
declare(strict_types=1);

namespace Tests\Unit\Ai;

use Oshim\Testing\TestCase;
use Oshim\Ai\Tokenizer\GgufTokenizer;
use Oshim\Ai\Tensor\MatrixMath;

/**
 * 👑 Comprehensive GGUF BPE, SentencePiece & Dense Neural Embedding Test Suite
 */
final class GgufTokenizerTest extends TestCase
{
    // ==========================================
    // 1. Ranked BPE Merge Rules & Subword Resolution
    // ==========================================

    public function testBpeMergeRulePriorityRanking(): void
    {
        GgufTokenizer::reset();
        GgufTokenizer::loadVocabulary([
            't' => 10, 'e' => 11, 's' => 12,
            'te' => 20, 'tes' => 21, 'test' => 22,
        ]);

        // Priority 1: 't' + 'e' -> 'te'
        // Priority 2: 'te' + 's' -> 'tes'
        // Priority 3: 'tes' + 't' -> 'test'
        GgufTokenizer::addRankedMerge('t', 'e', 1);
        GgufTokenizer::addRankedMerge('te', 's', 2);
        GgufTokenizer::addRankedMerge('tes', 't', 3);

        $tokens = GgufTokenizer::encode('test');
        $this->assertContains(22, $tokens); // Merged all the way to 'test' (id 22)
    }

    public function testBpePartialMergesWhenRankExhausted(): void
    {
        GgufTokenizer::reset();
        GgufTokenizer::loadVocabulary([
            'c' => 1, 'l' => 2, 'o' => 3, 'u' => 4, 'd' => 5,
            'cl' => 10, 'ou' => 11,
        ]);

        GgufTokenizer::addRankedMerge('c', 'l', 1);
        GgufTokenizer::addRankedMerge('o', 'u', 2);

        $tokens = GgufTokenizer::encode('cloud');
        // 'c'+'l' -> 'cl' (10), 'o'+'u' -> 'ou' (11), 'd' (5)
        $this->assertContains(10, $tokens);
        $this->assertContains(11, $tokens);
        $this->assertContains(5, $tokens);
    }

    // ==========================================
    // 2. SentencePiece Whitespace & Byte Fallback
    // ==========================================

    public function testSentencePiecePrefixWhitespaceReplacement(): void
    {
        GgufTokenizer::reset();
        $text = ' Sovereign Cloud';
        $tokenIds = GgufTokenizer::encode($text);

        $this->assertNotEmpty($tokenIds);
        $decoded = GgufTokenizer::decode($tokenIds);
        $this->assertStringContainsString('Sovereign', $decoded);
        $this->assertStringContainsString('Cloud', $decoded);
    }

    public function testSentencePieceByteFallbackForUnseenUtf8Bytes(): void
    {
        GgufTokenizer::reset();
        $rawBytes = "\xFE\xFF";
        $tokenIds = GgufTokenizer::encode($rawBytes);

        $this->assertNotEmpty($tokenIds);
        $decoded = GgufTokenizer::decode($tokenIds);
        $this->assertNotEmpty($decoded);
    }

    public function testSentencePieceMultilingualUtf8Support(): void
    {
        GgufTokenizer::reset();
        $banglaText = 'সার্ভার ভার্চুয়ালাইজেশন ইঞ্জিন';
        $tokenIds = GgufTokenizer::encode($banglaText);

        $this->assertNotEmpty($tokenIds);
        $decoded = GgufTokenizer::decode($tokenIds);
        $this->assertStringContainsString('সার্ভার', $decoded);
        $this->assertStringContainsString('ভার্চুয়ালাইজেশন', $decoded);
    }

    // ==========================================
    // 3. Special Tokens Preservation
    // ==========================================

    public function testStandardSpecialTokensPreservation(): void
    {
        GgufTokenizer::reset();
        $text = "<s>[INST] What is the CPU allocation? [/INST] 4 Cores</s>";
        $tokens = GgufTokenizer::encode($text);

        $this->assertContains(1, $tokens); // <s> / <bos>
        $this->assertContains(3, $tokens); // [INST]
        $this->assertContains(4, $tokens); // [/INST]
        $this->assertContains(2, $tokens); // </s> / <eos>

        $decoded = GgufTokenizer::decode($tokens);
        $this->assertStringContainsString('[INST]', $decoded);
        $this->assertStringContainsString('[/INST]', $decoded);
        $this->assertStringContainsString('CPU allocation', $decoded);
    }

    public function testLlama3SpecialTokensPreservation(): void
    {
        GgufTokenizer::reset();
        $prompt = "<|begin_of_text|><|start_header_id|>system<|end_header_id|>\nYou are a secure hypervisor.<|eot_id|>";
        $tokens = GgufTokenizer::encode($prompt);

        $this->assertContains(128000, $tokens); // <|begin_of_text|>
        $this->assertContains(128009, $tokens); // <|eot_id|>

        $decoded = GgufTokenizer::decode($tokens);
        $this->assertStringContainsString('<|begin_of_text|>', $decoded);
        $this->assertStringContainsString('<|eot_id|>', $decoded);
    }

    public function testCustomSpecialTokenRegistration(): void
    {
        GgufTokenizer::reset();
        GgufTokenizer::registerSpecialToken('<|sovereign_kernel|>', 99999);

        $tokens = GgufTokenizer::encode('<|sovereign_kernel|> execute ioctl');
        $this->assertContains(99999, $tokens);

        $decoded = GgufTokenizer::decode($tokens);
        $this->assertStringContainsString('<|sovereign_kernel|>', $decoded);
    }

    // ==========================================
    // 4. Roundtrip Encode / Decode Integrity
    // ==========================================

    public function testRoundtripEncodeDecodePlainText(): void
    {
        GgufTokenizer::reset();
        $text = 'OSHIM Sovereign Framework zero dependency PHP 8.3 kernel.';
        $tokens = GgufTokenizer::encode($text);
        $decoded = GgufTokenizer::decode($tokens);

        $this->assertStringContainsString('OSHIM', $decoded);
        $this->assertStringContainsString('Sovereign', $decoded);
        $this->assertStringContainsString('dependency', $decoded);
    }

    public function testRoundtripEncodeDecodeEmptyString(): void
    {
        GgufTokenizer::reset();
        $tokens = GgufTokenizer::encode('');
        $this->assertEmpty($tokens);

        $decoded = GgufTokenizer::decode([]);
        $this->assertSame('', $decoded);
    }

    public function testRoundtripEncodeDecodeSpecialPunctuationAndCode(): void
    {
        GgufTokenizer::reset();
        $code = 'if ($vps->status === "RUNNING") { return true; }';
        $tokens = GgufTokenizer::encode($code);
        $decoded = GgufTokenizer::decode($tokens);

        $this->assertStringContainsString('$vps', $decoded);
        $this->assertStringContainsString('RUNNING', $decoded);
        $this->assertStringContainsString('return', $decoded);
    }

    // ==========================================
    // 5. Dense Neural Embeddings & L2 Unit Normalization
    // ==========================================

    public function testDenseNeuralEmbeddingDimensions(): void
    {
        GgufTokenizer::reset();
        $text = 'Dedicated Bare-Metal MicroVM Cloud Hypervisor';

        $vec64 = GgufTokenizer::embed($text, 64);
        $this->assertCount(64, $vec64);

        $vec128 = GgufTokenizer::embed($text, 128);
        $this->assertCount(128, $vec128);

        $vec256 = GgufTokenizer::embed($text, 256);
        $this->assertCount(256, $vec256);

        $vec768 = GgufTokenizer::embed($text, 768);
        $this->assertCount(768, $vec768);
    }

    public function testDenseNeuralEmbeddingL2UnitNormalization(): void
    {
        GgufTokenizer::reset();
        $vec = GgufTokenizer::embed('Ultra-fast fiber event loop networking', 128);

        $magnitude = MatrixMath::vectorMagnitude($vec);
        $this->assertTrue(
            abs($magnitude - 1.0) < 1e-4,
            "Dense neural embedding must be L2 normalized to unit length 1.0 (got {$magnitude})"
        );
    }

    public function testDenseNeuralEmbeddingDeterminism(): void
    {
        GgufTokenizer::reset();
        $text = 'Zero-allocation ring buffer packet parsing';

        $vecA = GgufTokenizer::embed($text, 64);
        $vecB = GgufTokenizer::embed($text, 64);

        $this->assertSame($vecA, $vecB);
    }

    public function testDenseNeuralEmbeddingCosineSimilarityDiscrimination(): void
    {
        GgufTokenizer::reset();
        $docTechA = 'High throughput bare-metal Linux MicroVM virtualization';
        $docTechB = 'Linux virtual machine cloud computing server hypervisor';
        $docUnrelated = 'Chocolate cake recipe with dark cocoa and vanilla';

        $vecA = GgufTokenizer::embed($docTechA, 128);
        $vecB = GgufTokenizer::embed($docTechB, 128);
        $vecC = GgufTokenizer::embed($docUnrelated, 128);

        $simAB = MatrixMath::cosineSimilarity($vecA, $vecB);
        $simAC = MatrixMath::cosineSimilarity($vecA, $vecC);

        $this->assertTrue($simAB > 0.3, "Related technical documents must have strong cosine similarity (got {$simAB})");
        $this->assertTrue(
            $simAB > $simAC,
            "Related documents similarity ({$simAB}) must exceed unrelated documents similarity ({$simAC})"
        );
    }
}
