<?php
declare(strict_types=1);

namespace Oshim\Http\WebSocket;

use JsonSerializable;

/**
 * Encapsulates an active WebSocket client connection.
 */
class WebSocketConnection
{
    private string $id;
    /** @var resource|null */
    private $socket;
    private bool $handshaken = false;
    private array $rooms = [];
    private float $lastHeartbeat;
    private array $attributes = [];
    private string $buffer = '';

    public function __construct(string $id, $socket = null)
    {
        $this->id = $id;
        $this->socket = $socket;
        $this->lastHeartbeat = microtime(true);
    }

    public function getId(): string { return $this->id; }
    public function isHandshaken(): bool { return $this->handshaken; }
    public function setHandshaken(bool $status): void { $this->handshaken = $status; }
    public function getSocket() { return $this->socket; }
    public function getBuffer(): string { return $this->buffer; }
    public function appendBuffer(string $data): void { $this->buffer .= $data; }
    public function consumeBuffer(int $length): void { $this->buffer = substr($this->buffer, $length); }
    public function clearBuffer(): void { $this->buffer = ''; }

    public function updateHeartbeat(): void { $this->lastHeartbeat = microtime(true); }
    public function getLastHeartbeat(): float { return $this->lastHeartbeat; }

    public function setAttribute(string $key, mixed $value): void { $this->attributes[$key] = $value; }
    public function getAttribute(string $key, mixed $default = null): mixed { return $this->attributes[$key] ?? $default; }

    public function join(string $room): void { $this->rooms[$room] = true; }
    public function leave(string $room): void { unset($this->rooms[$room]); }
    public function isInRoom(string $room): bool { return isset($this->rooms[$room]); }
    public function getRooms(): array { return array_keys($this->rooms); }

    public function send(WebSocketFrame $frame): bool
    {
        if (!is_resource($this->socket)) {
            return false;
        }
        $raw = $frame->encode(false);
        $written = @fwrite($this->socket, $raw);
        return $written !== false && $written > 0;
    }

    public function sendText(string $message): bool
    {
        return $this->send(WebSocketFrame::text($message));
    }

    public function sendJson(mixed $data): bool
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $this->sendText($json !== false ? $json : '{}');
    }

    public function ping(string $data = ''): bool
    {
        return $this->send(WebSocketFrame::ping($data));
    }

    public function pong(string $data = ''): bool
    {
        return $this->send(WebSocketFrame::pong($data));
    }

    public function close(int $code = 1000, string $reason = ''): void
    {
        if (is_resource($this->socket)) {
            $this->send(WebSocketFrame::close($code, $reason));
            @fclose($this->socket);
            $this->socket = null;
        }
    }
}
