<?php
declare(strict_types=1);

namespace Tests\Unit\Omnichannel;

use Oshim\Testing\TestCase;
use Oshim\Mobile\MobileAppEngine;
use Oshim\Desktop\DesktopAppEngine;
use Oshim\Ai\Tensor\MatrixMath;
use Oshim\Ai\Tokenizer\GgufTokenizer;
use Oshim\Ai\Inference\OshimLlmEngine;
use Oshim\Ai\OshimAi;

class OmnichannelTest extends TestCase
{
    public function testMobileAppEngineManifestAndNav(): void
    {
        $manifest = MobileAppEngine::getManifestConfig();
        $this->assertSame('OSHIM Sovereign Mobile', $manifest['name']);
        $this->assertSame('standalone', $manifest['display']);

        $bottomNav = MobileAppEngine::renderMobileBottomNav('/vps');
        $this->assertStringContainsString('oshim-mobile-bottom-nav', $bottomNav);
        $this->assertStringContainsString('হোম', $bottomNav);
        $this->assertStringContainsString('VPS', $bottomNav);

        $sw = MobileAppEngine::getServiceWorkerScript();
        $this->assertStringContainsString('oshim-mobile-v1', $sw);
    }

    public function testDesktopAppEngineConfigAndLauncher(): void
    {
        $cfg = DesktopAppEngine::getDesktopConfig();
        $this->assertSame('OSHIM Sovereign Desktop', $cfg['app_name']);
        $this->assertSame(1280, $cfg['window']['width']);

        $launch = DesktopAppEngine::launchStandaloneWindow('http://127.0.0.1:8080/client/dashboard');
        $this->assertSame('LAUNCHED', $launch['status']);
        $this->assertTrue($launch['tray_active']);
    }

    public function testMatrixMathTensorOperations(): void
    {
        $vecA = [1.0, 2.0, 3.0];
        $vecB = [4.0, 5.0, 6.0];

        $dot = MatrixMath::dotProduct($vecA, $vecB);
        $this->assertEquals(32.0, $dot); // 1*4 + 2*5 + 3*6 = 32

        $cos = MatrixMath::cosineSimilarity([1.0, 0.0], [1.0, 0.0]);
        $this->assertEquals(1.0, round($cos, 4));

        $softmax = MatrixMath::softmax([1.0, 2.0, 3.0]);
        $this->assertCount(3, $softmax);
        $this->assertEquals(1.0, round(array_sum($softmax), 4));

        $matA = [[1, 2], [3, 4]];
        $matB = [[2, 0], [1, 2]];
        $mult = MatrixMath::matrixMultiply($matA, $matB);
        $this->assertSame([[4.0, 4.0], [10.0, 8.0]], $mult);
    }

    public function testGgufTokenizerEncodeDecode(): void
    {
        $text = "OSHIM Cloud Sovereign AI Platform";
        $tokens = GgufTokenizer::encode($text);
        $this->assertIsArray($tokens);
        $this->assertTrue(count($tokens) >= 4);

        $decoded = GgufTokenizer::decode($tokens);
        $this->assertStringContainsString('OSHIM', $decoded);
        $this->assertStringContainsString('Cloud', $decoded);
    }

    public function testOshimNativeAiInferenceAndEmbeddings(): void
    {
        $ai = new OshimLlmEngine('oshim-7b');
        $res = $ai->generate('Hello OSHIM AI');
        $this->assertSame('COMPLETED', $res['status']);
        $this->assertNotEmpty($res['reply']);

        $embed = $ai->generateEmbeddings('Cloud VPS Hosting');
        $this->assertCount(64, $embed);

        $sim = OshimAi::semanticSimilarity('Cloud Server', 'Cloud VPS');
        $this->assertIsFloat($sim);
    }
}
