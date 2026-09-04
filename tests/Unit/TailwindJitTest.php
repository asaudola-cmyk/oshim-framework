<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Ui\Css\TailwindJitCompiler;

final class TailwindJitTest extends TestCase
{
    public function testTailwindJitExtractAndCompile(): void
    {
        $html = '<div class="flex items-center justify-between p-6 bg-slate-900 rounded-2xl shadow-2xl hover:scale-105 transition-all md:grid-cols-3">';
        
        $classes = TailwindJitCompiler::extractClasses($html);
        $this->assertContains('flex', $classes);
        $this->assertContains('items-center', $classes);
        $this->assertContains('justify-between', $classes);
        $this->assertContains('p-6', $classes);
        $this->assertContains('bg-slate-900', $classes);
        $this->assertContains('rounded-2xl', $classes);
        $this->assertContains('hover:scale-105', $classes);
        $this->assertContains('md:grid-cols-3', $classes);

        $css = TailwindJitCompiler::compile($html);

        $this->assertStringContainsString('.flex{display:flex;}', $css);
        $this->assertStringContainsString('.items-center{align-items:center;}', $css);
        $this->assertStringContainsString('.p-6{padding:1.5rem;}', $css);
        $this->assertStringContainsString('.bg-slate-900{background-color:#0f172a;}', $css);
        $this->assertStringContainsString('.rounded-2xl{border-radius:1rem;}', $css);
        $this->assertStringContainsString('.hover\\:scale-105:hover{transform:scale(1.05);}', $css);
        $this->assertStringContainsString('@media (min-width: 768px)', $css);
    }
}
