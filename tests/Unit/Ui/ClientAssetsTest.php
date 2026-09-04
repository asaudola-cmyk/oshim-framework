<?php
declare(strict_types=1);

namespace Tests\Unit\Ui;

use Oshim\Tests\Harness\TestCase;

class ClientAssetsTest extends TestCase
{
    protected string $assetsDir;

    protected function setUp(): void
    {
        $this->assetsDir = dirname(__DIR__, 3) . '/public/assets';
    }

    public function testClientAssetFilesExistInPublicDirectory(): void
    {
        $this->assert(is_dir($this->assetsDir), "Assets directory [{$this->assetsDir}] must exist.");

        $cssFile = $this->assetsDir . '/oshim.css';
        $clientJsFile = $this->assetsDir . '/oshim-client.js';
        $terminalJsFile = $this->assetsDir . '/oshim-terminal.js';

        $this->assert(is_file($cssFile), "oshim.css must exist.");
        $this->assert(is_file($clientJsFile), "oshim-client.js must exist.");
        $this->assert(is_file($terminalJsFile), "oshim-terminal.js must exist.");
    }

    public function testOshimClientJsUncompressedSizeUnderBudget(): void
    {
        $clientJsFile = $this->assetsDir . '/oshim-client.js';
        $size = filesize($clientJsFile);

        $this->assertLessThan(12288, $size, "oshim-client.js uncompressed size ({$size} bytes) must be < 12KB (12288 bytes).");
    }

    public function testOshimTerminalJsUncompressedSizeUnderBudget(): void
    {
        $terminalJsFile = $this->assetsDir . '/oshim-terminal.js';
        $size = filesize($terminalJsFile);

        $this->assertLessThan(25600, $size, "oshim-terminal.js uncompressed size ({$size} bytes) must be < 25KB (25600 bytes).");
    }

    public function testOshimCssContainsAllDesignTokens(): void
    {
        $cssContent = (string)file_get_contents($this->assetsDir . '/oshim.css');

        $tokens = [
            '--oshim-bg-root',
            '--oshim-bg-card',
            '--oshim-border-glass',
            '--oshim-border-focus',
            '--oshim-blur-md',
            '--oshim-z-base',
            '--oshim-z-dropdown',
            '--oshim-z-sticky',
            '--oshim-z-modal',
            '--oshim-shadow-card',
            '--oshim-shadow-modal',
            '--oshim-glow-cyan',
            '--oshim-accent-cyan',
            '--oshim-grad-cyan',
            '--oshim-color-running',
            '--oshim-color-stopped',
        ];

        foreach ($tokens as $token) {
            $this->assertStringContains($token, $cssContent, "oshim.css must declare design token [{$token}].");
        }
    }

    public function testOshimCssContainsAllComponentClasses(): void
    {
        $cssContent = (string)file_get_contents($this->assetsDir . '/oshim.css');

        $classes = [
            '.oshim-glass',
            '.oshim-btn',
            '.oshim-btn--primary',
            '.oshim-card',
            '.oshim-table',
            '.oshim-badge',
            '.oshim-badge--pulse',
            '.oshim-modal-backdrop',
            '.oshim-modal',
            '.oshim-form',
            '.oshim-input',
            '.oshim-toggle',
            '.oshim-sidebar',
            '.oshim-sidebar--collapsed',
            '.oshim-navbar',
            '.oshim-terminal',
            '.oshim-datagrid',
            '.oshim-chart',
        ];

        foreach ($classes as $cls) {
            $this->assertStringContains($cls, $cssContent, "oshim.css must define CSS class [{$cls}].");
        }
    }

    public function testJsAssetsHaveZeroExternalDependencies(): void
    {
        $clientJs = (string)file_get_contents($this->assetsDir . '/oshim-client.js');
        $termJs = (string)file_get_contents($this->assetsDir . '/oshim-terminal.js');

        $forbidden = ['react', 'vue', 'angular', 'jquery', 'bootstrap', 'tailwind', 'alpine', 'morphdom', 'xterm'];

        foreach ($forbidden as $dep) {
            $this->assertStringNotContains("require('{$dep}')", $clientJs);
            $this->assertStringNotContains("import '{$dep}'", $clientJs);
            $this->assertStringNotContains("require('{$dep}')", $termJs);
            $this->assertStringNotContains("import '{$dep}'", $termJs);
        }
    }

    public function testJsAssetsSupportAllDirectives(): void
    {
        $clientJs = (string)file_get_contents($this->assetsDir . '/oshim-client.js');

        $directives = [
            'data-oshim-id',
            'data-oshim-action',
            'data-oshim-bind',
            'data-oshim-submit',
            'data-oshim-poll',
            'data-oshim-stream',
            'oshim:click',
            'oshim:input',
            'oshim:change',
            'oshim:submit',
        ];

        foreach ($directives as $dir) {
            $this->assertStringContains($dir, $clientJs, "oshim-client.js must support directive [{$dir}].");
        }
    }
}
