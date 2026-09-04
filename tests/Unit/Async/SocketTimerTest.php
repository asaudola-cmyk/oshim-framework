<?php
declare(strict_types=1);

namespace Tests\Unit\Async;

use Oshim\Testing\TestCase;
use Oshim\Async\EventLoop;
use Oshim\Async\Timer\TimerQueue;
use Oshim\Async\Socket\AsyncTcpServer;
use Oshim\Async\Socket\AsyncTcpClient;
use Oshim\Async\Socket\AsyncUdpServer;
use Oshim\Async\Socket\StreamConnection;

class SocketTimerTest extends TestCase
{
    public function testTimerQueueExecutionAndCancellation(): void
    {
        $queue = new TimerQueue();
        $executed = false;

        $timer = $queue->add(10, false, function () use (&$executed) {
            $executed = true;
        });

        $this->assertEquals(1, $queue->count());
        $this->assertNotNull($queue->getTimeout());

        // Cancel timer
        $queue->cancel($timer);
        $this->assertTrue($timer->isCancelled());
        $this->assertEquals(0, $queue->tick());
        $this->assertFalse($executed);
    }

    public function testNonBlockingTcpEchoServer(): void
    {
        $loop = EventLoop::getInstance();
        $server = new AsyncTcpServer($loop);
        $server->listen('127.0.0.1', 0); // Bind ephemeral port
        $port = $server->getPort();

        $serverReceived = '';
        $serverConn = null;

        $server->onConnect(function (StreamConnection $conn) use (&$serverReceived, &$serverConn) {
            $serverConn = $conn;
            $conn->onData(function ($chunk, StreamConnection $c) use (&$serverReceived) {
                $serverReceived = $chunk;
                $c->write("ECHO:{$chunk}");
            });
        });

        $clientReceived = '';
        $clientConnRef = null;

        AsyncTcpClient::connect('127.0.0.1', $port, 2.0, [], $loop)->then(function (StreamConnection $clientConn) use (&$clientReceived, &$clientConnRef) {
            $clientConnRef = $clientConn;
            $clientConn->onData(function ($chunk) use (&$clientReceived) {
                $clientReceived = $chunk;
            });
            $clientConn->write("HELLO_TCP");
        });

        // Tick loop until echo received
        $start = microtime(true);
        while ($clientReceived === '' && (microtime(true) - $start) < 2.0) {
            $loop->tick(0.01);
        }

        $server->close();
        if ($clientConnRef !== null) {
            $clientConnRef->close();
        }
        if ($serverConn !== null) {
            $serverConn->close();
        }

        $this->assertEquals('HELLO_TCP', $serverReceived);
        $this->assertEquals('ECHO:HELLO_TCP', $clientReceived);
    }

    public function testNonBlockingUdpServer(): void
    {
        $loop = EventLoop::getInstance();
        $udpServer = new AsyncUdpServer($loop);
        $udpServer->listen('127.0.0.1', 0);
        $port = $udpServer->getPort();

        $receivedPacket = '';
        $receivedPeer = '';

        $udpServer->onMessage(function ($packet, $peer, $server) use (&$receivedPacket, &$receivedPeer) {
            $receivedPacket = $packet;
            $receivedPeer = $peer;
            $server->sendTo("ACK:{$packet}", $peer);
        });

        // Send UDP packet via standard socket
        $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        $msg = "PING_UDP";
        socket_sendto($sock, $msg, strlen($msg), 0, '127.0.0.1', $port);

        // Tick loop
        $start = microtime(true);
        while ($receivedPacket === '' && (microtime(true) - $start) < 1.0) {
            $loop->tick(0.01);
        }

        $udpServer->close();
        socket_close($sock);

        $this->assertEquals('PING_UDP', $receivedPacket);
        $this->assertStringContainsString('127.0.0.1', $receivedPeer);
    }
}
