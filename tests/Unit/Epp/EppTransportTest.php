<?php
declare(strict_types=1);

namespace Oshim\Tests\Unit\Epp;

use Oshim\Epp\Exceptions\EppConnectionException;
use Oshim\Epp\Exceptions\EppTransportException;
use Oshim\Epp\Transport\MemoryTransport;
use Oshim\Epp\Transport\TlsStreamTransport;
use Oshim\Tests\Harness\TestCase;

class EppTransportTest extends TestCase
{
    public function testMemoryTransportBasicOperations(): void
    {
        $transport = new MemoryTransport();
        $this->assertFalse($transport->isConnected());

        $greeting = $transport->connect();
        $this->assertTrue($transport->isConnected());
        $this->assertStringContains('<greeting>', $greeting);

        $command = '<epp><command><logout/></command></epp>';
        $transport->send($command);
        $this->assertSame($command, $transport->getLastCommand());
        $this->assertCount(1, $transport->getHistory());

        $response = $transport->receive();
        $this->assertStringContains('<result code="1000">', $response);

        $transport->disconnect();
        $this->assertFalse($transport->isConnected());
    }

    public function testMemoryTransportQueuedResponses(): void
    {
        $transport = new MemoryTransport();
        $transport->connect();

        $customResp1 = '<epp><response><result code="1000"><msg>First</msg></result></response></epp>';
        $customResp2 = '<epp><response><result code="1001"><msg>Second</msg></result></response></epp>';

        $transport->queueResponse($customResp1);
        $transport->queueResponse($customResp2);

        $this->assertSame($customResp1, $transport->receive());
        $this->assertSame($customResp2, $transport->receive());
    }

    public function testMemoryTransportThrowsWhenNotConnected(): void
    {
        $transport = new MemoryTransport();

        $this->assertThrows(EppTransportException::class, function () use ($transport) {
            $transport->send('<test/>');
        }, 'not connected');

        $this->assertThrows(EppTransportException::class, function () use ($transport) {
            $transport->receive();
        }, 'not connected');
    }

    public function testTlsStreamTransportThrowsOnConnectionToInvalidHost(): void
    {
        // 127.0.0.1 on closed port 59999 with short timeout
        $transport = new TlsStreamTransport([
            'host' => '127.0.0.1',
            'port' => 59999,
            'timeout' => 1,
        ]);

        $this->assertThrows(EppConnectionException::class, function () use ($transport) {
            $transport->connect();
        });
    }

    public function testTlsStreamTransportOperationsThrowWhenDisconnected(): void
    {
        $transport = new TlsStreamTransport();

        $this->assertThrows(EppTransportException::class, function () use ($transport) {
            $transport->send('<test/>');
        }, 'not connected');

        $this->assertThrows(EppTransportException::class, function () use ($transport) {
            $transport->receive();
        }, 'not connected');
    }
}
