<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Ui\Rsc\ServerComponent;
use Oshim\Ui\Rsc\SuspenseBoundary;
use Oshim\Ui\Rsc\StreamingSsrEngine;

class CloudMetricsServerComponent extends ServerComponent
{
    public function render(): string
    {
        return "<div class=\"metrics\"><h3>Cluster Metrics</h3><p>Active Nodes: 12</p></div>";
    }
}

final class RscStreamingTest extends TestCase
{
    public function testReactServerComponentRender(): void
    {
        $comp = new CloudMetricsServerComponent();
        $html = $comp->render();

        $this->assertStringContainsString('Cluster Metrics', $html);
        $this->assertStringContainsString('Active Nodes: 12', $html);

        $json = $comp->jsonSerialize();
        $this->assertSame('SERVER_COMPONENT', $json['type']);
        $this->assertTrue($json['zero_js']);
    }

    public function testSuspenseBoundaryAndChunkResolution(): void
    {
        $boundary = new SuspenseBoundary(
            '<div class="skeleton">Loading billing records...</div>',
            function () {
                return '<div class="billing-table"><p>Invoice Total: $120.00</p></div>';
            },
            'suspense-billing-1'
        );

        $initial = $boundary->renderInitial();
        $this->assertStringContainsString('id="suspense-billing-1"', $initial);
        $this->assertStringContainsString('Loading billing records...', $initial);
        $this->assertStringContainsString('data-oshim-suspense="pending"', $initial);

        $chunk = $boundary->resolveChunk();
        $this->assertSame('suspense-billing-1', $chunk['id']);
        $this->assertStringContainsString('<template id="chunk-suspense-billing-1">', $chunk['stream_chunk']);
        $this->assertStringContainsString('Invoice Total: $120.00', $chunk['resolved_html']);
    }

    public function testStreamingSsrEngineFlushing(): void
    {
        $template = "<html><body><!-- suspense:s1 --></body></html>";
        $boundary = new SuspenseBoundary(
            '<p>Loading...</p>',
            fn() => '<p>Resolved Real-Time Data</p>',
            's1'
        );

        $engine = new StreamingSsrEngine($template);
        $engine->addSuspense($boundary);

        $initialShell = $engine->renderInitialShell();
        $this->assertStringContainsString('<div id="s1" data-oshim-suspense="pending"><p>Loading...</p></div>', $initialShell);

        $chunks = [];
        $engine->stream(function ($data) use (&$chunks) {
            $chunks[] = $data;
        });

        $this->assertCount(2, $chunks);
        $this->assertStringContainsString('Loading...', $chunks[0]);
        $this->assertStringContainsString('Resolved Real-Time Data', $chunks[1]);
    }
}
