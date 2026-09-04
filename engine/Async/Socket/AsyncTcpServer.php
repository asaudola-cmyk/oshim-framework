<?php
declare(strict_types=1);

namespace Oshim\Async\Socket;

use Oshim\Async\EventLoop;
use RuntimeException;

class AsyncTcpServer
{
    /** @var resource|null */
    protected $server = null;
    protected EventLoop $loop;
    /** @var list<callable> */
    protected array $connectCallbacks = [];
    protected string $host = '127.0.0.1';
    protected int $port = 0;

    public function __construct(?EventLoop $loop = null)
    {
        $this->loop = $loop ?? EventLoop::getInstance();
    }

    public function listen(string $host, int $port, array $contextOptions = []): self
    {
        $this->host = $host;
        $this->port = $port;

        $context = stream_context_create($contextOptions);
        $uri = "tcp://{$host}:{$port}";

        $errno = 0;
        $errstr = '';
        $this->server = @stream_socket_server(
            $uri,
            $errno,
            $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $context
        );

        if (!$this->server) {
            throw new RuntimeException("Failed to bind TCP server on {$uri}: [{$errno}] {$errstr}");
        }

        stream_set_blocking($this->server, false);

        $this->loop->addReadStream($this->server, function ($server) {
            $clientSocket = @stream_socket_accept($server, 0);
            if ($clientSocket) {
                $connection = new StreamConnection($clientSocket, $this->loop);
                foreach ($this->connectCallbacks as $callback) {
                    $callback($connection, $this);
                }
            }
        });

        return $this;
    }

    public function onConnect(callable $callback): self
    {
        $this->connectCallbacks[] = $callback;
        return $this;
    }

    public function close(): void
    {
        if ($this->server && is_resource($this->server)) {
            $this->loop->removeReadStream($this->server);
            @fclose($this->server);
            $this->server = null;
        }
    }

    public function getPort(): int
    {
        if ($this->server && is_resource($this->server)) {
            $name = stream_socket_get_name($this->server, false);
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
