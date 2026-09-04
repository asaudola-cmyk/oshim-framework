<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Desktop\Bridge\NativeWebviewBridge;

final class DesktopBridgeTest extends TestCase
{
    public function testDesktopBridgeBindingAndIpcDispatch(): void
    {
        $bridge = new NativeWebviewBridge('OSHIM Admin Console', 1280, 800);

        $this->assertSame('OSHIM Admin Console', $bridge->getTitle());
        $this->assertSame(1280, $bridge->getWidth());

        $bridge->bind('get_cluster_nodes', function () {
            return ['nodes' => ['node-1', 'node-2'], 'status' => 'ONLINE'];
        });

        $bridge->bind('add_numbers', function (int $a, int $b) {
            return $a + $b;
        });

        // Valid IPC Call 1
        $res1 = $bridge->handleIpcMessage('get_cluster_nodes');
        $this->assertSame('SUCCESS', $res1['status']);
        $this->assertSame('ONLINE', $res1['result']['status']);

        // Valid IPC Call 2 with args
        $res2 = $bridge->handleIpcMessage('add_numbers', [15, 25]);
        $this->assertSame('SUCCESS', $res2['status']);
        $this->assertSame(40, $res2['result']);

        // Unknown IPC Call
        $res3 = $bridge->handleIpcMessage('unknown_method');
        $this->assertSame('ERROR', $res3['status']);

        $descriptor = $bridge->getWindowDescriptor();
        $this->assertSame('READY', $descriptor['status']);
        $this->assertContains('get_cluster_nodes', $descriptor['registered_ipc_methods']);
    }
}
