<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Ui\Css\TailwindJitCompiler;

final class TailwindJitFullTest extends TestCase
{
    public function testTailwindColorsAndOpacity(): void
    {
        $html = '<div class="bg-blue-500 bg-red-600/80 bg-purple-950 text-emerald-400 border-rose-500/50 bg-black/40 border-white/20">';
        $css = TailwindJitCompiler::compile($html);

        $this->assertStringContainsString('#3b82f6', $css);
        $this->assertStringContainsString('rgba(220,38,38,0.8)', $css);
        $this->assertStringContainsString('#3b0764', $css);
        $this->assertStringContainsString('#34d399', $css);
        $this->assertStringContainsString('rgba(244,63,94,0.5)', $css);
        $this->assertStringContainsString('rgba(0,0,0,0.4)', $css);
        $this->assertStringContainsString('rgba(255,255,255,0.2)', $css);
    }

    public function testTailwindPseudoVariantsAndBreakpoints(): void
    {
        $html = '<button class="hover:scale-105 focus:bg-indigo-600 active:scale-95 sm:grid-cols-2 lg:grid-cols-4 animate-spin backdrop-blur-xl">';
        $css = TailwindJitCompiler::compile($html);

        $this->assertStringContainsString(':hover{transform:scale(1.05);}', $css);
        $this->assertStringContainsString(':focus{background-color:#4f46e5;}', $css);
        $this->assertStringContainsString(':active{transform:scale(0.95);}', $css);
        $this->assertStringContainsString('@media (min-width: 640px)', $css);
        $this->assertStringContainsString('@media (min-width: 1024px)', $css);
        $this->assertStringContainsString('backdrop-filter:blur(24px);', $css);
        $this->assertStringContainsString('animation:spin 1s linear infinite;', $css);
    }
}
