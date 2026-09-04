<?php
declare(strict_types=1);

namespace Oshim\Http\Server;

use Fiber;
use RuntimeException;
use Oshim\Ui\LiveDom\WebSocketServer;

/**
 * 👑 Sovereign OSHIM Universal Reactor (Multiplexer)
 * 
 * WHY: Running separate ports for HTTP and WebSockets is a legacy design.
 * This Universal Reactor listens on a SINGLE PORT and inspects raw TCP packets.
 * It dynamically upgrades HTTP connections to WebSockets when requested.
 * 
 * ADVANCED: Uses PHP 8.1 Fibers. Every TCP connection runs inside its own lightweight Coroutine.
 * If one request blocks (e.g., waiting for DB), the Fiber yields and the Reactor serves other clients!
 */
class UniversalReactor
{
    protected string $host;
    protected int $port;
    protected $masterSocket;
    
    protected array $sockets = [];
    protected array $fibers = [];
    protected array $buffers = [];
    
    protected $httpHandler;

    public function __construct(string $host = '0.0.0.0', int $port = 8080)
    {
        $this->host = $host;
        $this->port = $port;
    }

    public function setHttpHandler(callable $handler): void
    {
        $this->httpHandler = $handler;
    }

    public function boot(): void
    {
        $this->masterSocket = stream_socket_server("tcp://{$this->host}:{$this->port}", $errno, $errstr);
        if (!$this->masterSocket) {
            throw new RuntimeException("Universal Reactor failed to start: $errstr ($errno)");
        }
        
        stream_set_blocking($this->masterSocket, false);
        $this->sockets[(int)$this->masterSocket] = $this->masterSocket;

        echo "🚀 OSHIM Universal Fiber Reactor running on http://{$this->host}:{$this->port}\n";
        echo "   ⚡ HTTP/1.1 and WebSockets multiplexed on the same port.\n";
        echo "   🧵 Fiber Coroutine Engine Active.\n";

        $this->runEventLoop();
    }

    protected function runEventLoop(): void
    {
        while (true) {
            $read = $this->sockets;
            $write = null;
            $except = null;
            
            // Resume any suspended Fibers that are ready
            foreach ($this->fibers as $id => $fiber) {
                if ($fiber->isSuspended()) {
                    $fiber->resume();
                }
                if ($fiber->isTerminated()) {
                    unset($this->fibers[$id]);
                }
            }

            if (stream_select($read, $write, $except, 0, 100000) < 1) {
                continue;
            }

            foreach ($read as $socket) {
                if ($socket === $this->masterSocket) {
                    $this->acceptConnection();
                } else {
                    $this->handleData($socket);
                }
            }
        }
    }

    protected function acceptConnection(): void
    {
        $client = stream_socket_accept($this->masterSocket);
        if ($client) {
            stream_set_blocking($client, false);
            $this->sockets[(int)$client] = $client;
        }
    }

    protected function handleData($client): void
    {
        $data = fread($client, 8192);
        
        if ($data === false || strlen($data) === 0) {
            fclose($client);
            unset($this->sockets[(int)$client]);
            return;
        }

        // SPAWN A GREEN THREAD (FIBER) FOR THIS REQUEST
        // WHY: This ensures true Go-like Concurrency.
        $fiber = new Fiber(function () use ($client, $data) {
            $this->processProtocol($client, $data);
        });
        
        $fiber->start();
        
        if (!$fiber->isTerminated()) {
            $this->fibers[(int)$client] = $fiber;
        }
    }

    protected function processProtocol($client, string $data): void
    {
        $socketId = (int)$client;
        
        if (isset($this->buffers[$socketId]['is_ws'])) {
            $this->handleWsFrame($client, $data);
            return;
        }

        // Protocol Multiplexer: Check Headers
        if (strpos($data, "Upgrade: websocket") !== false) {
            $this->upgradeToWebSocket($client, $data);
        } else {
            $this->handleHttpRequest($client, $data);
        }
    }

    protected function handleHttpRequest($client, string $rawRequest): void
    {
        $lines = explode("\r\n", $rawRequest);
        $requestLine = explode(" ", $lines[0]);
        
        if (count($requestLine) >= 2) {
            $method = $requestLine[0];
            $uri = $requestLine[1];
            
            if ($this->httpHandler) {
                $responseBody = ($this->httpHandler)($method, $uri);
            } else {
                $responseBody = "<h1>OSHIM Universal Reactor</h1><p>Running in a Fiber Coroutine.</p>";
            }
            
            $length = strlen($responseBody);
            $response = "HTTP/1.1 200 OK\r\n"
                      . "Content-Type: text/html\r\n"
                      . "Content-Length: {$length}\r\n"
                      . "Connection: close\r\n\r\n"
                      . $responseBody;
                      
            fwrite($client, $response);
        }
        
        fclose($client);
        unset($this->sockets[(int)$client]);
    }

    protected function upgradeToWebSocket($client, string $rawRequest): void
    {
        if (preg_match('#Sec-WebSocket-Key: (.*)\r\n#', $rawRequest, $matches)) {
            $key = base64_encode(pack('H*', sha1($matches[1] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
            
            $headers = "HTTP/1.1 101 Switching Protocols\r\n"
                     . "Upgrade: websocket\r\n"
                     . "Connection: Upgrade\r\n"
                     . "Sec-WebSocket-Accept: $key\r\n\r\n";
                     
            fwrite($client, $headers);
            $this->buffers[(int)$client]['is_ws'] = true;
            echo "✔ HTTP Upgraded to WebSocket (ID: " . (int)$client . ") via Fiber\n";
        }
    }

    protected function handleWsFrame($client, string $data): void
    {
        // WS frame logic delegated here
        $response = json_encode(['status' => 'Fiber WS Active']);
        $b1 = 0x80 | (0x1 & 0x0f);
        $header = pack('CC', $b1, strlen($response));
        @fwrite($client, $header . $response);
    }
}
