<?php
declare(strict_types=1);

namespace Tests\Unit\Ai;

use Oshim\Testing\TestCase;
use Oshim\Ai\Tokenizer\GgufTokenizer;

class GgufBpeTokenizerTest extends TestCase
{
    public function testGgufTokenizerSpecialTokens(): void
    {
        $text = "<|begin_of_text|>[INST] Deploy KVM MicroVM [/INST]<|eot_id|>";
        $tokenIds = GgufTokenizer::encode($text);

        $this->assertContains(128000, $tokenIds);
        $this->assertContains(3, $tokenIds);
        $this->assertContains(4, $tokenIds);
        $this->assertContains(128009, $tokenIds);

        $decoded = GgufTokenizer::decode($tokenIds);
        $this->assertStringContainsString('<|begin_of_text|>', $decoded);
        $this->assertStringContainsString('[INST]', $decoded);
        $this->assertStringContainsString('MicroVM', $decoded);
        $this->assertStringContainsString('<|eot_id|>', $decoded);
    }
}
