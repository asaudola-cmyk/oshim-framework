<?php
declare(strict_types=1);

namespace Oshim\Async\Socket;

use Oshim\Async\EventLoop;
use Oshim\Async\Promise;
use RuntimeException;

class AsyncTcpClient
{
    public static function connect(
        string $host,
        int $port,
        float $timeout = 5.0,
        array $contextOptions = [],
        ?EventLoop $loop = null
    ): Promise {
        $loop = $loop ?? EventLoop::getInstance();
        $promise = new Promise();

        $context = stream_context_create($contextOptions);
        $uri = "tcp://{$host}:{$port}";

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            $uri,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT,
            $context
        );

        if (!$socket) {
            $promise->reject(new RuntimeException("Could not connect to {$uri}: [{$errno}] {$errstr}"));
            return $promise;
        }

        stream_set_blocking($socket, false);

        // Timer for connection timeout
        $timeoutTimer = $loop->setTimeout(function () use ($socket, $promise, $loop, $uri) {
            $loop->removeWriteStream($socket);
            @fclose($socket);
            $promise->reject(new RuntimeException("Connection timeout to {$uri}"));
        }, (int)($timeout * 1000));

        // When socket is writable, connection is established
        $loop->addWriteStream($socket, function ($sock) use ($promise, $loop, $timeoutTimer) {
            $loop->removeWriteStream($sock);
            $loop->cancelTimer($timeoutTimer);

            // Check if connection succeeded
            $peer = @stream_socket_get_name($sock, true);
            if (!$peer) {
                @fclose($sock);
                $promise->reject(new RuntimeException("Socket connection refused."));
                return;
            }

            $connection = new StreamConnection($sock, $loop);
            $promise->resolve($connection);
        });

        return $promise;
    }
}
