<?php
declare(strict_types=1);

namespace Oshim\Async\Socket;

use Oshim\Async\EventLoop;
use Oshim\Async\Promise;

class StreamConnection
{
    /** @var resource */
    protected $stream;
    protected EventLoop $loop;
    protected string $writeBuffer = '';
    /** @var list<callable> */
    protected array $dataCallbacks = [];
    /** @var list<callable> */
    protected array $closeCallbacks = [];
    /** @var list<callable> */
    protected array $errorCallbacks = [];
    protected bool $closed = false;

    public function __construct(mixed $stream, ?EventLoop $loop = null)
    {
        $this->stream = $stream;
        $this->loop = $loop ?? EventLoop::getInstance();

        stream_set_blocking($this->stream, false);

        $this->loop->addReadStream($this->stream, function ($stream) {
            $this->handleRead();
        });
    }

    public function onData(callable $callback): self
    {
        $this->dataCallbacks[] = $callback;
        return $this;
    }

    public function onClose(callable $callback): self
    {
        $this->closeCallbacks[] = $callback;
        return $this;
    }

    public function onError(callable $callback): self
    {
        $this->errorCallbacks[] = $callback;
        return $this;
    }

    public function write(string $data): Promise
    {
        $promise = new Promise();

        if ($this->closed || !is_resource($this->stream)) {
            $promise->reject(new \RuntimeException("Connection is already closed."));
            return $promise;
        }

        $this->writeBuffer .= $data;

        // Try writing immediately
        $written = @fwrite($this->stream, $this->writeBuffer);
        if ($written !== false && $written > 0) {
            $this->writeBuffer = substr($this->writeBuffer, $written);
        }

        if ($this->writeBuffer === '') {
            $promise->resolve(true);
            return $promise;
        }

        // Register write watcher to drain remaining buffer
        $this->loop->addWriteStream($this->stream, function ($stream) use ($promise) {
            if ($this->closed || !is_resource($this->stream)) {
                $this->loop->removeWriteStream($this->stream);
                $promise->reject(new \RuntimeException("Connection closed during write."));
                return;
            }

            $bytes = @fwrite($this->stream, $this->writeBuffer);
            if ($bytes !== false && $bytes > 0) {
                $this->writeBuffer = substr($this->writeBuffer, $bytes);
            }

            if ($this->writeBuffer === '') {
                $this->loop->removeWriteStream($this->stream);
                $promise->resolve(true);
            }
        });

        return $promise;
    }

    public function read(int $length = 65536): Promise
    {
        $promise = new Promise();

        $callback = function ($chunk) use ($promise, &$callback) {
            $promise->resolve($chunk);
            // Remove one-time reader
            $this->dataCallbacks = array_filter($this->dataCallbacks, fn($cb) => $cb !== $callback);
        };

        $this->onData($callback);
        return $promise;
    }

    protected function handleRead(): void
    {
        if (!is_resource($this->stream)) {
            $this->close();
            return;
        }

        $chunk = @fread($this->stream, 65536);

        if ($chunk === false || $chunk === '' || feof($this->stream)) {
            $this->close();
            return;
        }

        foreach ($this->dataCallbacks as $callback) {
            $callback($chunk, $this);
        }
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;

        if (is_resource($this->stream)) {
            $this->loop->removeStream($this->stream);
            @fclose($this->stream);
        }

        foreach ($this->closeCallbacks as $callback) {
            $callback($this);
        }
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function getRemoteAddress(): ?string
    {
        if (!is_resource($this->stream)) {
            return null;
        }
        return @stream_socket_get_name($this->stream, true) ?: null;
    }

    public function getLocalAddress(): ?string
    {
        if (!is_resource($this->stream)) {
            return null;
        }
        return @stream_socket_get_name($this->stream, false) ?: null;
    }

    public function getStream(): mixed
    {
        return $this->stream;
    }
}
