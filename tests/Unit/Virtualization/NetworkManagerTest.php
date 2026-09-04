<?php
declare(strict_types=1);

namespace Tests\Unit\Virtualization;

use Oshim\Testing\TestCase;
use Oshim\Virtualization\Network\BridgeManager;
use Oshim\Virtualization\Network\NatManager;
use Oshim\Virtualization\Network\SimulatedNatRouter;
use Oshim\Virtualization\Network\TapManager;
use Oshim\Virtualization\Network\VethManager;
use RuntimeException;

class NetworkManagerTest extends TestCase
{
    public function testBridgeManagerMethods(): void
    {
        $bridgeManager = new BridgeManager('oshim0', '10.42.0.1/24');
        $info = $bridgeManager->getBridgeInfo('oshim0');

        $this->assertArrayHasKey('bridge_name', $info);
        $this->assertArrayHasKey('is_active', $info);
        $this->assertArrayHasKey('interfaces', $info);
        $this->assertEquals('oshim0', $info['bridge_name']);
    }

    public function testNatManagerRuleSyntaxFormatting(): void
    {
        $rules = NatManager::formatRuleStrings(
            '10.42.0.0/24',
            'oshim0',
            '198.51.100.10',
            8080,
            '10.42.0.15',
            80,
            'tcp'
        );

        $this->assertStringContainsString('MASQUERADE', $rules['masquerade']);
        $this->assertStringContainsString('10.42.0.0/24', $rules['masquerade']);
        $this->assertStringContainsString('oshim0', $rules['masquerade']);

        $this->assertStringContainsString('DNAT --to-destination 10.42.0.15:80', $rules['dnat']);
        $this->assertStringContainsString('--dport 8080', $rules['dnat']);
    }

    public function testSimulatedNatRouterPortForwardingAndCollisionDetection(): void
    {
        $router = new SimulatedNatRouter();
        $router->reset();

        $key = $router->addPortForward('0.0.0.0', 8080, '10.42.0.10', 80, 'tcp');
        $this->assertEquals('tcp:0.0.0.0:8080', $key);

        $dest = $router->resolveDestination('0.0.0.0', 8080, 'tcp');
        $this->assertNotNull($dest);
        $this->assertEquals('10.42.0.10', $dest['guest_ip']);
        $this->assertEquals(80, $dest['guest_port']);

        // Adding duplicate port should throw RuntimeException
        $this->assertThrows(function () use ($router) {
            $router->addPortForward('0.0.0.0', 8080, '10.42.0.11', 8080, 'tcp');
        }, RuntimeException::class);

        // Removing port forward
        $this->assertTrue($router->removePortForward('0.0.0.0', 8080, 'tcp'));
        $this->assertNull($router->resolveDestination('0.0.0.0', 8080, 'tcp'));
        $this->assertFalse($router->removePortForward('0.0.0.0', 8080, 'tcp'));
    }

    public function testVethManagerClassStructure(): void
    {
        $veth = new VethManager();
        $this->assertInstanceOf(VethManager::class, $veth);
    }
}
