<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Http\WebSocket\WebSocketFrame;
use Oshim\Http\WebSocket\WebSocketConnection;
use Oshim\Http\WebSocket\WebSocketServer;
use Oshim\Http\Sse\SseStreamer;

final class WebSocketTest extends TestCase
{
    public function testWebSocketFrameEncodingAndDecoding(): void
    {
        $originalText = "Hello OSHIM WebSocket RFC 6455 🚀";
        $frame = WebSocketFrame::text($originalText);

        $this->assertTrue($frame->isText());
        $this->assertSame($originalText, $frame->getPayload());

        // Unmasked encode/decode
        $encoded = $frame->encode(false);
        [$decoded, $consumed] = WebSocketFrame::decode($encoded);

        $this->assertNotNull($decoded);
        $this->assertSame($originalText, $decoded->getPayload());
        $this->assertSame(strlen($encoded), $consumed);

        // Masked encode/decode
        $maskedEncoded = $frame->encode(true);
        [$maskedDecoded, $maskedConsumed] = WebSocketFrame::decode($maskedEncoded);

        $this->assertNotNull($maskedDecoded);
        $this->assertSame($originalText, $maskedDecoded->getPayload());
        $this->assertSame(strlen($maskedEncoded), $maskedConsumed);
    }

    public function testHandshakeCalculation(): void
    {
        // Standard RFC 6455 example
        $clientKey = "dGhlIHNhbXBsZSBub25jZQ==";
        $expectedAccept = "s3pPLMBiTxaQ9kYGzzhZRbK+xOo=";

        $accept = WebSocketServer::computeAcceptKey($clientKey);
        $this->assertSame($expectedAccept, $accept);

        $headers = "GET /chat HTTP/1.1\r\nHost: localhost\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: {$clientKey}\r\nSec-WebSocket-Version: 13\r\n\r\n";
        $handshake = WebSocketServer::buildHandshakeResponse($headers);

        $this->assertNotNull($handshake);
        $this->assertStringContainsString("Sec-WebSocket-Accept: {$expectedAccept}", $handshake);
        $this->assertStringContainsString("101 Switching Protocols", $handshake);
    }

    public function testWebSocketServerRoomsAndBroadcasting(): void
    {
        $server = new WebSocketServer();

        $conn1 = new WebSocketConnection('conn-1');
        $conn2 = new WebSocketConnection('conn-2');
        $conn3 = new WebSocketConnection('conn-3');

        $server->addConnection($conn1);
        $server->addConnection($conn2);
        $server->addConnection($conn3);

        $server->joinRoom('conn-1', 'general');
        $server->joinRoom('conn-2', 'general');
        $server->joinRoom('conn-3', 'vip');

        $this->assertTrue($conn1->isInRoom('general'));
        $this->assertTrue($conn2->isInRoom('general'));
        $this->assertFalse($conn3->isInRoom('general'));
        $this->assertTrue($conn3->isInRoom('vip'));

        $this->assertCount(3, $server->getConnections());
        $server->removeConnection('conn-3');
        $this->assertCount(2, $server->getConnections());
    }

    public function testSseStreamerFormatting(): void
    {
        $formatted = SseStreamer::formatEvent(['status' => 'ok'], 'metric', 'event-101', 3000);

        $this->assertStringContainsString("id: event-101\n", $formatted);
        $this->assertStringContainsString("event: metric\n", $formatted);
        $this->assertStringContainsString("retry: 3000\n", $formatted);
        $this->assertStringContainsString('data: {"status":"ok"}', $formatted);

        $tokenChunk = SseStreamer::formatToken("Hello", false);
        $this->assertStringContainsString('event: token', $tokenChunk);
        $this->assertStringContainsString('"token":"Hello"', $tokenChunk);
    }
}
