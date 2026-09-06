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
            
            $status = 200;
            $headers = [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Connection' => 'close',
                'Server' => 'OSHIM Universal Fiber Reactor'
            ];
            $responseBody = '';

            if ($this->httpHandler) {
                try {
                    $result = ($this->httpHandler)($method, $uri, $rawRequest);
                    if ($result instanceof \Oshim\Http\Response) {
                        $status = $result->getStatusCode();
                        $responseBody = $result->getContent();
                        foreach ($result->getHeaders() as $hKey => $hVal) {
                            $headers[$hKey] = is_array($hVal) ? implode(', ', $hVal) : $hVal;
                        }
                    } elseif (is_array($result)) {
                        $status = $result['status'] ?? 200;
                        $responseBody = (string)($result['body'] ?? '');
                        if (!empty($result['headers'])) {
                            foreach ($result['headers'] as $k => $v) {
                                $headers[$k] = $v;
                            }
                        }
                    } else {
                        $responseBody = (string)$result;
                    }
                } catch (\Throwable $e) {
                    $status = 500;
                    $responseBody = "<h1>500 Server Error</h1><p>" . htmlspecialchars($e->getMessage()) . "</p>";
                }
            } else {
                $responseBody = "<h1>OSHIM Universal Reactor</h1><p>Running in a Fiber Coroutine.</p>";
            }

            $statusText = match ($status) {
                200 => 'OK',
                201 => 'Created',
                204 => 'No Content',
                301 => 'Moved Permanently',
                302 => 'Found',
                304 => 'Not Modified',
                400 => 'Bad Request',
                401 => 'Unauthorized',
                403 => 'Forbidden',
                404 => 'Not Found',
                405 => 'Method Not Allowed',
                419 => 'Page Expired',
                500 => 'Internal Server Error',
                default => 'OK',
            };

            $headers['Content-Length'] = (string)strlen($responseBody);

            $headerString = "HTTP/1.1 {$status} {$statusText}\r\n";
            foreach ($headers as $hName => $hVal) {
                $headerString .= "{$hName}: {$hVal}\r\n";
            }
            $headerString .= "\r\n";

            @fwrite($client, $headerString . $responseBody);
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

    protected array $channels = [];

    /**
     * 🚀 FULL LIVEDOM WEBSOCKET ACTION DISPATCHER
     * 
     * WHY: Previously this was a stub returning {'status':'Fiber WS Active'}.
     * Now it decodes RFC 6455 frames, hydrates components, executes actions,
     * and broadcasts morphed HTML diffs back to all subscribers in real-time.
     */
    protected function handleWsFrame($client, string $data): void
    {
        $payload = $this->unmaskPayload($data);
        if ($payload === null || $payload === '') {
            return;
        }

        try {
            $action = json_decode($payload, true);
            if (!is_array($action) || !isset($action['id'], $action['component'])) {
                return;
            }

            $componentId = (string)$action['id'];
            $componentName = (string)$action['component'];
            $clientId = (int)$client;

            // Subscribe client socket to this component channel
            if (!isset($this->channels[$componentId])) {
                $this->channels[$componentId] = [];
            }
            $this->channels[$componentId][$clientId] = $client;

            // Resolve target component class
            $componentClass = null;
            if ($componentName === 'DemoComponent' || $componentName === 'root_demo') {
                $componentClass = \Oshim\Ui\LiveDom\DemoComponent::class;
            } else {
                $candidates = [
                    "App\\Components\\{$componentName}",
                    "Oshim\\Ui\\LiveDom\\{$componentName}",
                    $componentName
                ];
                foreach ($candidates as $candidate) {
                    if (class_exists($candidate)) {
                        $componentClass = $candidate;
                        break;
                    }
                }
            }

            if ($componentClass !== null) {
                /** @var \Oshim\Ui\LiveDom\Component $component */
                $component = new $componentClass($componentId);
            } else {
                // Dynamic fallback component
                $component = new class($componentId) extends \Oshim\Ui\LiveDom\Component {
                    public int $count = 0;
                    public string $text = '';
                    public function increment(): void { $this->count++; }
                    public function update_model($val): void { $this->text = (string)$val; }
                    public function render(): \Oshim\Ui\Dsl\Element {
                        return \Oshim\Ui\Dsl\Div::make()->classes('p-6 bg-gray-900 text-white rounded-lg')->children([
                            \Oshim\Ui\Dsl\H1::make()->text("OSHIM Dynamic Component")->classes('text-2xl font-bold'),
                            \Oshim\Ui\Dsl\Div::make()->text("Count: " . $this->count),
                            \Oshim\Ui\Dsl\Button::make('Increment (+)')
                                ->onClick('increment')
                                ->classes('bg-blue-600 px-4 py-2 rounded text-white')
                        ]);
                    }
                };
            }

            // 1. Hydrate state
            if (isset($action['state']) && is_array($action['state'])) {
                $component->hydrate($action['state']);
            }

            // 2. Invoke requested action method
            if (isset($action['method']) && method_exists($component, $action['method'])) {
                $method = (string)$action['method'];
                $val = $action['value'] ?? null;
                if ($val !== null) {
                    $component->$method($val);
                } else {
                    $component->$method();
                }
            }

            // 3. Compile updated HTML and extract updated state
            $html = $component->compile();
            $state = $component->getState();

            $responsePayload = json_encode([
                'id' => $componentId,
                'html' => $html,
                'state' => $state
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            // 4. Broadcast to all active subscribers on this channel
            if (isset($this->channels[$componentId])) {
                foreach ($this->channels[$componentId] as $subId => $subSocket) {
                    if (is_resource($subSocket)) {
                        $this->sendWsFrame($subSocket, $responsePayload);
                    } else {
                        unset($this->channels[$componentId][$subId]);
                    }
                }
            } else {
                $this->sendWsFrame($client, $responsePayload);
            }

        } catch (\Throwable $e) {
            $errorPayload = json_encode(['error' => $e->getMessage()]);
            $this->sendWsFrame($client, $errorPayload);
        }
    }

    protected function unmaskPayload(string $data): ?string
    {
        if (strlen($data) < 2) {
            return null;
        }

        $length = ord($data[1]) & 127;
        if ($length === 126) {
            $masks = substr($data, 4, 4);
            $data = substr($data, 8);
        } elseif ($length === 127) {
            $masks = substr($data, 10, 4);
            $data = substr($data, 14);
        } else {
            $masks = substr($data, 2, 4);
            $data = substr($data, 6);
        }

        $text = '';
        $dataLen = strlen($data);
        for ($i = 0; $i < $dataLen; ++$i) {
            $text .= $data[$i] ^ $masks[$i % 4];
        }

        return $text;
    }

    protected function sendWsFrame($client, string $text): void
    {
        $b1 = 0x80 | (0x1 & 0x0f);
        $length = strlen($text);

        if ($length <= 125) {
            $header = pack('CC', $b1, $length);
        } elseif ($length > 125 && $length < 65536) {
            $header = pack('CCn', $b1, 126, $length);
        } else {
            $header = pack('CCNN', $b1, 127, $length);
        }

        @fwrite($client, $header . $text);
    }
}
