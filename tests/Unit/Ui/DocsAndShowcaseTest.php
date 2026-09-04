<?php
declare(strict_types=1);

namespace Tests\Unit\Ui;

use Oshim\Testing\TestCase;
use Oshim\Ui\Docs\DocsPortalLayout;
use Oshim\Ui\Showcase\SovereignShowcaseLayout;

class DocsAndShowcaseTest extends TestCase
{
    public function testDocsPortalLayoutRendersAllSections(): void
    {
        $htmlQuickstart = DocsPortalLayout::renderFullPage('quickstart');
        $this->assertStringContainsString('<!DOCTYPE html>', $htmlQuickstart);
        $this->assertStringContainsString('Getting Started: 1-Minute Quickstart', $htmlQuickstart);
        $this->assertStringContainsString('Option A: Single-File Micro-Application', $htmlQuickstart);

        $htmlBenchmarks = DocsPortalLayout::renderFullPage('benchmarks');
        $this->assertStringContainsString('Global Technology Comparison', $htmlBenchmarks);
        $this->assertStringContainsString('1.4 Million RPS', $htmlBenchmarks);

        $htmlPackager = DocsPortalLayout::renderFullPage('packager');
        $this->assertStringContainsString('pack:standalone', $htmlPackager);
    }

    public function testSovereignShowcaseLayoutRendersUnifiedHud(): void
    {
        $html = SovereignShowcaseLayout::renderFullPage();

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('SHOWCASE', $html);
        $this->assertStringContainsString('Autonomous AI Squad', $html);
        $this->assertStringContainsString('KVM MicroVM Hypervisor', $html);
        $this->assertStringContainsString('Cryptographic Blockchain', $html);
        $this->assertStringContainsString('OSHIM Standalone Sandbox', $html);
        $this->assertStringContainsString('<style>', $html);
    }
}
