<?php
declare(strict_types=1);

namespace Tests\Unit\Ui;

use Oshim\Testing\TestCase;
use App\Controllers\ShowcaseController;
use Oshim\Http\Request;

class ShowcaseApplicationTest extends TestCase
{
    private ShowcaseController $controller;

    public function setUp(): void
    {
        parent::setUp();
        $this->controller = new ShowcaseController();
    }

    public function testIndexEndpointReturnsLayout(): void
    {
        $request = new Request('GET', '/api/showcase/index');
        $response = $this->controller->index($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('<!DOCTYPE html>', $response->getContent());
    }

    public function testAiTokenInference(): void
    {
        $request = new Request('POST', '/api/showcase/ai/tokenize', [], [], [], [
            'text' => 'Test Tokenization'
        ]);
        $response = $this->controller->tokenizeGguf($request);

        $this->assertSame(200, $response->getStatusCode());
        
        $body = $response->getContent();
        $data = json_decode($body, true);
        
        $this->assertSame('success', $data['status']);
        $this->assertIsArray($data['tokens']);
    }

    public function testMicroVmTelemetryEndpoint(): void
    {
        $request = new Request('GET', '/api/showcase/vm/telemetry');
        $response = $this->controller->getVmTelemetry($request);

        if ($response->getStatusCode() === 500) {
            echo "RESPONSE BODY: " . $response->getContent() . "\n";
        }

        $this->assertSame(200, $response->getStatusCode());
        
        $body = $response->getContent();
        $data = json_decode($body, true);
        
        $this->assertSame('success', $data['status']);
        $this->assertIsArray($data['cgroup']);
        $this->assertIsArray($data['swarm']);
    }
}
