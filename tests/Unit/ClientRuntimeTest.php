<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Ui\Runtime\OshimClientRuntime;

final class ClientRuntimeTest extends TestCase
{
    public function testClientRuntimeScriptAndSize(): void
    {
        $script = OshimClientRuntime::getScript();

        $this->assertNotEmpty($script);
        $this->assertStringContainsString('window.Oshim', $script);
        $this->assertStringContainsString('initSoftNav', $script);
        $this->assertStringContainsString('initIslands', $script);
        $this->assertStringContainsString('initActions', $script);
        $this->assertStringContainsString('initSignals', $script);

        // Verify ultra-compact size under 4KB (4096 bytes)
        $scriptSize = strlen($script);
        $this->assertTrue($scriptSize < 4096, "Client runtime size must be under 4KB (got {$scriptSize} bytes)");

        $tag = OshimClientRuntime::renderTag();
        $this->assertStringStartsWith('<script>', $tag);
        $this->assertStringEndsWith('</script>', $tag);
    }
}
