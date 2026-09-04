<?php
declare(strict_types=1);

namespace Tests\Unit\Ui;

use Oshim\Testing\TestCase;
use Oshim\Ui\Css\TailwindJitCompiler;

class TailwindJitArbitraryValuesTest extends TestCase
{
    public function testArbitraryValuesCompilation(): void
    {
        $html = '<div class="w-[350px] h-[120px] bg-[#1a202c] text-[#00f2fe] rounded-[16px] p-[24px] gap-[12px] grid-cols-[1fr_2fr_1fr]">Arbitrary</div>';
        $css = TailwindJitCompiler::compile($html);

        $this->assertStringContainsString('width:350px;', $css);
        $this->assertStringContainsString('height:120px;', $css);
        $this->assertStringContainsString('background-color:#1a202c;', $css);
        $this->assertStringContainsString('color:#00f2fe;', $css);
        $this->assertStringContainsString('border-radius:16px;', $css);
        $this->assertStringContainsString('padding:24px;', $css);
        $this->assertStringContainsString('gap:12px;', $css);
        $this->assertStringContainsString('grid-template-columns:1fr 2fr 1fr;', $css);
    }
}
