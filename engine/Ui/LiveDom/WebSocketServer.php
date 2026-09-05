<?php
declare(strict_types=1);

namespace Oshim\Ui\LiveDom;

use RuntimeException;

class WebSocketServer
{
    protected string $host;
    protected int $port;
    protected $masterSocket;
    protected array $clients = [];
    protected array $channels = [];

    public function __construct(string $host = '0.0.0.0', int $port = 8080)
    {
        $this->host = $host;
        $this->port = $port;
    }

    public function start(): void
    {
        $this->masterSocket = stream_socket_server("tcp://{$this->host}:{$this->port}", $errno, $errstr);
        if (!$this->masterSocket) {
            throw new RuntimeException("WebSocket Server failed to start: $errstr ($errno)");
        }
        stream_set_blocking($this->masterSocket, false);
        $this->clients[(int)$this->masterSocket] = $this->masterSocket;

        echo "🚀 OSHIM React-Style LiveDOM Server running on ws://{$this->host}:{$this->port}\n";

        while (true) {
            $read = $this->clients;
            $write = null;
            $except = null;
            if (stream_select($read, $write, $except, 0, 100000) < 1) continue;

            foreach ($read as $socket) {
                if ($socket === $this->masterSocket) {
                    $this->acceptNewClient();
                } else {
                    $this->handleClientMessage($socket);
                }
            }
        }
    }

    protected function acceptNewClient(): void
    {
        $client = stream_socket_accept($this->masterSocket);
        if ($client) {
            stream_set_blocking($client, false);
            $this->clients[(int)$client] = $client;
            $request = fread($client, 4096);
            if (preg_match('#Sec-WebSocket-Key: (.*)\r\n#', $request, $matches)) {
                $key = base64_encode(pack('H*', sha1($matches[1] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
                $headers = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: $key\r\n\r\n";
                fwrite($client, $headers);
            } else {
                fclose($client);
                unset($this->clients[(int)$client]);
            }
        }
    }

    protected function handleClientMessage($client): void
    {
        $data = fread($client, 8192);
        if ($data === false || strlen($data) === 0) {
            $this->disconnectClient($client);
            return;
        }
        $payload = $this->unmaskPayload($data);
        if (!$payload) return;
        $this->processLiveDomAction($client, $payload);
    }

    protected function disconnectClient($client): void
    {
        $clientId = (int)$client;
        unset($this->clients[$clientId]);
        foreach ($this->channels as $channelId => &$subscribers) {
            if (isset($subscribers[$clientId])) unset($subscribers[$clientId]);
        }
        fclose($client);
    }

    protected function processLiveDomAction($client, string $payload): void
    {
        try {
            $action = json_decode($payload, true);
            if (!$action || !isset($action['id'], $action['component'])) return;

            $componentId = $action['id'];
            $clientId = (int)$client;
            
            if (!isset($this->channels[$componentId])) {
                $this->channels[$componentId] = [];
            }
            $this->channels[$componentId][$clientId] = $client;

            // 🚀 REACT-STYLE COMPONENT HYDRATION 
            // In a real app, you would resolve this via namespace or class map.
            // For now, we simulate finding the user's component class.
            
            // Example User App Component:
            $className = "App\\Components\\" . $action['component'];
            
            // Fallback for demonstration since we don't have user code yet
            if (!class_exists($className)) {
                // Dynamically create an anonymous class to prove the React feel works!
                $component = new class($componentId) extends Component {
                    public int $count = 0;
                    public string $text = '';

                    public function increment() { $this->count++; }
                    public function update_model($val) { $this->text = $val; }

                                        public function render(): \Oshim\Ui\Dsl\Element {
                        return \Oshim\Ui\Dsl\Div::make()->classes('p-6 bg-gray-900 text-white rounded-lg shadow-xl')->children([
                            \Oshim\Ui\Dsl\H1::make()->text("React Feel in PHP (Pure DSL)")->classes('text-2xl font-bold mb-4'),
                            \Oshim\Ui\Dsl\Div::make()->text("Multiplayer Count: " . $this->count)->classes('mb-2'),
                            \Oshim\Ui\Dsl\Button::make('Increment (+)')->onClick('increment')->classes('bg-blue-600 px-4 py-2 rounded font-bold hover:bg-blue-500 transition'),
                            \Oshim\Ui\Dsl\Div::make()->classes('mt-6')->children([
                                \Oshim\Ui\Dsl\Element::make('label')->text('Live Shared Note:')->classes('block text-sm mb-1'),
                                \Oshim\Ui\Dsl\Input::make('text')->model('text')->attr('value', $this->text)->classes('w-full bg-gray-800 border border-gray-700 p-2 rounded text-white'),
                                \Oshim\Ui\Dsl\Element::make('p')->text("All users see: <strong>" . $this->text . "</strong>")->classes('mt-2 text-sm text-gray-400')
                            ])
                        ]);
                    }
                };
            } else {
                $component = new $className($componentId);
            }

            // 1. Hydrate state
            if (isset($action['state'])) {
                $component->hydrate($action['state']);
            }

            // 2. Execute Method
            if (isset($action['method']) && method_exists($component, $action['method'])) {
                $method = $action['method'];
                $val = $action['value'] ?? null;
                if ($val !== null) {
                    $component->$method($val);
                } else {
                    $component->$method();
                }
            }

            // 3. Compile React-style Render to HTML
            $html = $component->compile();

            $response = json_encode([
                'id' => $componentId,
                'html' => $html,
                'state' => $component->getState()
            ]);
            
            // 4. Broadcast
            foreach ($this->channels[$componentId] as $subscriberId => $subscriberSocket) {
                $this->sendToClient($subscriberSocket, $response);
            }
            
        } catch (\Throwable $e) {
            echo "Error processing action: " . $e->getMessage() . "\n";
        }
    }

    protected function sendToClient($client, string $text): void
    {
        $b1 = 0x80 | (0x1 & 0x0f);
        $length = strlen($text);
        if ($length <= 125) { $header = pack('CC', $b1, $length); }
        elseif ($length > 125 && $length < 65536) { $header = pack('CCn', $b1, 126, $length); }
        else { $header = pack('CCNN', $b1, 127, $length); }
        @fwrite($client, $header . $text);
    }

    protected function unmaskPayload(string $data): ?string
    {
        if (strlen($data) < 2) return null;
        $length = ord($data[1]) & 127;
        if ($length == 126) { $masks = substr($data, 4, 4); $data = substr($data, 8); }
        elseif ($length == 127) { $masks = substr($data, 10, 4); $data = substr($data, 14); }
        else { $masks = substr($data, 2, 4); $data = substr($data, 6); }
        $text = '';
        for ($i = 0; $i < strlen($data); ++$i) { $text .= $data[$i] ^ $masks[$i % 4]; }
        return $text;
    }
}
