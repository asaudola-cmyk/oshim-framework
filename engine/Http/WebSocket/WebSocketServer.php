<?php
declare(strict_types=1);

namespace Oshim\Http\WebSocket;

use Oshim\Async\EventLoop;
use Closure;

/**
 * High-performance RFC 6455 WebSocket Server & Channel Hub.
 */
class WebSocketServer
{
    public const GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    /** @var array<string, WebSocketConnection> */
    private array $connections = [];
    /** @var array<string, array<string, bool>> */
    private array $rooms = [];

    /** @var Closure|null */
    private ?Closure $onConnect = null;
    /** @var Closure|null */
    private ?Closure $onMessage = null;
    /** @var Closure|null */
    private ?Closure $onClose = null;
    /** @var Closure|null */
    private ?Closure $onError = null;

    /** @var resource|null */
    private $serverSocket = null;
    private bool $running = false;

    public function onConnect(callable $callback): self
    {
        $this->onConnect = $callback(...);
        return $this;
    }

    public function onMessage(callable $callback): self
    {
        $this->onMessage = $callback(...);
        return $this;
    }

    public function onClose(callable $callback): self
    {
        $this->onClose = $callback(...);
        return $this;
    }

    public function onError(callable $callback): self
    {
        $this->onError = $callback(...);
        return $this;
    }

    /**
     * Perform RFC 6455 handshake calculation.
     */
    public static function computeAcceptKey(string $secWebSocketKey): string
    {
        return base64_encode(sha1(trim($secWebSocketKey) . self::GUID, true));
    }

    /**
     * Process an incoming HTTP upgrade request string into a WebSocket response.
     */
    public static function buildHandshakeResponse(string $headers): ?string
    {
        if (!preg_match('/Sec-WebSocket-Key:\s*([^\r\n]+)/i', $headers, $matches)) {
            return null;
        }

        $acceptKey = self::computeAcceptKey($matches[1]);

        return "HTTP/1.1 101 Switching Protocols\r\n" .
               "Upgrade: websocket\r\n" .
               "Connection: Upgrade\r\n" .
               "Sec-WebSocket-Accept: {$acceptKey}\r\n" .
               "Sec-WebSocket-Version: 13\r\n" .
               "Server: OSHIM Sovereign WebSocket/1.0\r\n\r\n";
    }

    public function addConnection(WebSocketConnection $conn): void
    {
        $this->connections[$conn->getId()] = $conn;
        if ($this->onConnect !== null) {
            ($this->onConnect)($conn);
        }
    }

    public function removeConnection(string $id): void
    {
        if (isset($this->connections[$id])) {
            $conn = $this->connections[$id];
            foreach ($conn->getRooms() as $room) {
                unset($this->rooms[$room][$id]);
            }
            unset($this->connections[$id]);
            if ($this->onClose !== null) {
                ($this->onClose)($conn);
            }
        }
    }

    public function getConnection(string $id): ?WebSocketConnection
    {
        return $this->connections[$id] ?? null;
    }

    public function getConnections(): array
    {
        return $this->connections;
    }

    public function joinRoom(string $connectionId, string $room): void
    {
        if (isset($this->connections[$connectionId])) {
            $this->connections[$connectionId]->join($room);
            $this->rooms[$room][$connectionId] = true;
        }
    }

    public function leaveRoom(string $connectionId, string $room): void
    {
        if (isset($this->connections[$connectionId])) {
            $this->connections[$connectionId]->leave($room);
            unset($this->rooms[$room][$connectionId]);
        }
    }

    public function broadcastToRoom(string $room, string|WebSocketFrame $message, ?string $exceptConnectionId = null): int
    {
        if (!isset($this->rooms[$room])) {
            return 0;
        }

        $frame = is_string($message) ? WebSocketFrame::text($message) : $message;
        $sentCount = 0;

        foreach (array_keys($this->rooms[$room]) as $connId) {
            if ($exceptConnectionId !== null && $connId === $exceptConnectionId) {
                continue;
            }
            if (isset($this->connections[$connId])) {
                if ($this->connections[$connId]->send($frame)) {
                    $sentCount++;
                }
            }
        }

        return $sentCount;
    }

    public function broadcastAll(string|WebSocketFrame $message, ?string $exceptConnectionId = null): int
    {
        $frame = is_string($message) ? WebSocketFrame::text($message) : $message;
        $sentCount = 0;

        foreach ($this->connections as $connId => $conn) {
            if ($exceptConnectionId !== null && $connId === $exceptConnectionId) {
                continue;
            }
            if ($conn->send($frame)) {
                $sentCount++;
            }
        }

        return $sentCount;
    }

    /**
     * Feed incoming raw socket data for a connection.
     */
    public function handleRawData(WebSocketConnection $conn, string $data): void
    {
        if (!$conn->isHandshaken()) {
            $conn->appendBuffer($data);
            $buffer = $conn->getBuffer();
            if (str_contains($buffer, "\r\n\r\n")) {
                $handshake = self::buildHandshakeResponse($buffer);
                if ($handshake !== null) {
                    $socket = $conn->getSocket();
                    if (is_resource($socket)) {
                        @fwrite($socket, $handshake);
                    }
                    $conn->setHandshaken(true);
                    $conn->clearBuffer();
                    if ($this->onConnect !== null) {
                        ($this->onConnect)($conn);
                    }
                }
            }
            return;
        }

        $conn->appendBuffer($data);
        while (true) {
            $buffer = $conn->getBuffer();
            if (strlen($buffer) < 2) {
                break;
            }

            [$frame, $consumed] = WebSocketFrame::decode($buffer);
            if ($frame === null || $consumed === 0) {
                break;
            }

            $conn->consumeBuffer($consumed);
            $conn->updateHeartbeat();

            if ($frame->isPing()) {
                $conn->send(WebSocketFrame::pong($frame->getPayload()));
                continue;
            }

            if ($frame->isClose()) {
                $conn->close();
                $this->removeConnection($conn->getId());
                break;
            }

            if ($this->onMessage !== null) {
                ($this->onMessage)($conn, $frame->getPayload(), $frame);
            }
        }
    }
}
