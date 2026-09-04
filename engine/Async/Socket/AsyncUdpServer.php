<?php
declare(strict_types=1);

namespace Oshim\Async\Socket;

use Oshim\Async\EventLoop;
use RuntimeException;

class AsyncUdpServer
{
    /** @var resource|null */
    protected $socket = null;
    protected EventLoop $loop;
    /** @var list<callable> */
    protected array $messageCallbacks = [];
    protected string $host = '127.0.0.1';
    protected int $port = 0;

    public function __construct(?EventLoop $loop = null)
    {
        $this->loop = $loop ?? EventLoop::getInstance();
    }

    public function listen(string $host, int $port): self
    {
        $this->host = $host;
        $this->port = $port;

        $uri = "udp://{$host}:{$port}";
        $errno = 0;
        $errstr = '';

        $this->socket = @stream_socket_server(
            $uri,
            $errno,
            $errstr,
            STREAM_SERVER_BIND
        );

        if (!$this->socket) {
            throw new RuntimeException("Failed to bind UDP server on {$uri}: [{$errno}] {$errstr}");
        }

        stream_set_blocking($this->socket, false);

        $this->loop->addReadStream($this->socket, function ($socket) {
            $peer = null;
            $packet = @stream_socket_recvfrom($socket, 65535, 0, $peer);

            if ($packet !== false && $packet !== '' && $peer !== null) {
                foreach ($this->messageCallbacks as $callback) {
                    $callback($packet, $peer, $this);
                }
            }
        });

        return $this;
    }

    public function onMessage(callable $callback): self
    {
        $this->messageCallbacks[] = $callback;
        return $this;
    }

    public function sendTo(string $packet, string $peerAddress): int|bool
    {
        if (!$this->socket || !is_resource($this->socket)) {
            return false;
        }

        return @stream_socket_sendto($this->socket, $packet, 0, $peerAddress);
    }

    public function close(): void
    {
        if ($this->socket && is_resource($this->socket)) {
            $this->loop->removeReadStream($this->socket);
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    public function getPort(): int
    {
        if ($this->socket && is_resource($this->socket)) {
            $name = stream_socket_get_name($this->socket, false);
            if ($name && str_contains($name, ':')) {
                $parts = explode(':', $name);
                return (int)end($parts);
            }
        }
        return $this->port;
    }

    public function getHost(): string
    {
        return $this->host;
    }
}
