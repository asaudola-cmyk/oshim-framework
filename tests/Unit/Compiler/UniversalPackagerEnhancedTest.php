<?php
declare(strict_types=1);

namespace Tests\Unit\Compiler;

use Oshim\Testing\TestCase;
use Oshim\Compiler\UniversalPackager;
use Oshim\App\AppManifest;

class UniversalPackagerEnhancedTest extends TestCase
{
    public function testDesktopLauncherScriptGeneration(): void
    {
        $linuxScript = UniversalPackager::generateDesktopLauncherScript('linux', 'http://127.0.0.1:8080/');
        $this->assertStringContainsString('#!/usr/bin/env bash', $linuxScript);
        $this->assertStringContainsString('google-chrome', $linuxScript);

        $winScript = UniversalPackager::generateDesktopLauncherScript('windows', 'http://127.0.0.1:8080/');
        $this->assertStringContainsString('Start-Process', $winScript);
    }

    public function testPwaManifestGeneration(): void
    {
        $manifest = AppManifest::make('OSHIM Sovereign App');
        $json = UniversalPackager::generatePwaManifestJson($manifest);

        $this->assertStringContainsString('"name": "OSHIM Sovereign App"', $json);
        $this->assertStringContainsString('"display": "standalone"', $json);
        $this->assertStringContainsString('"start_url": "/"', $json);
    }
}
