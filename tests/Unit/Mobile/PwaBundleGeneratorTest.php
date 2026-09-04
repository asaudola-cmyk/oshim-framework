<?php
declare(strict_types=1);

namespace Tests\Unit\Mobile;

use Oshim\Testing\TestCase;
use Oshim\Mobile\PwaBundleGenerator;

class PwaBundleGeneratorTest extends TestCase
{
    private string $tempDir;

    public function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/oshim_pwa_' . bin2hex(random_bytes(4));
    }

    public function tearDown(): void
    {
        parent::tearDown();
        if (is_dir($this->tempDir)) {
            @unlink($this->tempDir . '/manifest.json');
            @unlink($this->tempDir . '/service-worker.js');
            @unlink($this->tempDir . '/offline.html');
            @rmdir($this->tempDir);
        }
    }

    public function testPwaBundleGeneration(): void
    {
        $res = PwaBundleGenerator::build($this->tempDir);

        $this->assertSame('GENERATED', $res['status']);
        $this->assertTrue(file_exists($this->tempDir . '/manifest.json'));
        $this->assertTrue(file_exists($this->tempDir . '/service-worker.js'));
        $this->assertTrue(file_exists($this->tempDir . '/offline.html'));

        $manifestContent = file_get_contents($this->tempDir . '/manifest.json');
        $this->assertStringContainsString('OSHIM Sovereign Cloud App', $manifestContent);
        $this->assertStringContainsString('standalone', $manifestContent);
    }
}
