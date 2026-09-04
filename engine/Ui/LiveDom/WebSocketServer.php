<?php
declare(strict_types=1);

namespace Oshim\Ui\LiveDom;

use RuntimeException;

/**
 * 👑 Sovereign OSHIM WebSocket Server (Multiplayer LiveDOM Engine)
 * 
 * ADVANCED: This is not just a 1-to-1 connection. This engine supports 
 * Channels & Broadcasting for Real-Time Collaborative UIs (like Figma/Google Docs).
 * State changes by one user are instantly broadcasted and morphed on all other clients.
 */
class WebSocketServer
{
    protected string $host;
    protected int $port;
    protected $masterSocket;
    
    // Tracks all active TCP connections
    protected array $clients = [];
    
    // Tracks which clients are subscribed to which components (Rooms/Channels)
    // Format: ['ComponentID' => [clientId => clientSocket]]
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

        echo "🚀 OSHIM Multiplayer LiveDOM Server running on ws://{$this->host}:{$this->port}\n";

        while (true) {
            $read = $this->clients;
            $write = null;
            $except = null;

            if (stream_select($read, $write, $except, 0, 100000) < 1) {
                continue;
            }

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
                
                $headers = "HTTP/1.1 101 Switching Protocols\r\n"
                         . "Upgrade: websocket\r\n"
                         . "Connection: Upgrade\r\n"
                         . "Sec-WebSocket-Accept: $key\r\n\r\n";
                         
                fwrite($client, $headers);
                echo "✔ Client Connected (ID: " . (int)$client . ")\n";
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
        echo "✖ Client Disconnected (ID: {$clientId})\n";
        
        // Remove from global list
        unset($this->clients[$clientId]);
        
        // Edge Case: Remove from all subscribed channels to prevent memory leaks
        foreach ($this->channels as $channelId => &$subscribers) {
            if (isset($subscribers[$clientId])) {
                unset($subscribers[$clientId]);
            }
        }
        
        fclose($client);
    }

    protected function processLiveDomAction($client, string $payload): void
    {
        try {
            $action = json_decode($payload, true);
            if (!$action || !isset($action['id'])) return;

            $componentId = $action['id'];
            $clientId = (int)$client;

            // Subscribe client to this component's channel automatically
            if (!isset($this->channels[$componentId])) {
                $this->channels[$componentId] = [];
            }
            $this->channels[$componentId][$clientId] = $client;

            // 1. Process State
            $newState = $action['state'] ?? [];
            if (isset($action['method']) && $action['method'] === 'increment') {
                $newState['count'] = ($newState['count'] ?? 0) + 1;
            } elseif (isset($action['method']) && $action['method'] === 'update_model') {
                $newState['text'] = $action['value'] ?? '';
            }

            // 2. Re-render HTML (Advanced VDOM Diff target)
            $text = htmlspecialchars($newState['text'] ?? '');
            $count = $newState['count'] ?? 0;
            
            $html = "<div oshim-component='CollaborationBoard' id='{$componentId}'>";
            $html .= "<h1 class='text-xl'>Multiplayer Count: {$count}</h1>";
            $html .= "<button oshim-click='increment' class='btn'>Increment (+)</button>";
            $html .= "<div class='mt-4'>";
            $html .= "<label>Live Shared Note:</label>";
            $html .= "<input type='text' oshim-model='text' value='{$text}' class='input' />";
            $html .= "<p class='mt-2'>All users see: <strong>{$text}</strong></p>";
            $html .= "</div></div>";

            $response = json_encode([
                'id' => $componentId,
                'html' => $html,
                'state' => $newState
            ]);
            
            // 3. BROADCAST TO ALL CLIENTS IN THE CHANNEL
            // WHY: This creates instant multiplayer UI synchronization!
            foreach ($this->channels[$componentId] as $subscriberId => $subscriberSocket) {
                // We send it to everyone, including the sender, to ensure pure state sync
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
        
        if ($length <= 125) {
            $header = pack('CC', $b1, $length);
        } elseif ($length > 125 && $length < 65536) {
            $header = pack('CCn', $b1, 126, $length);
        } else {
            $header = pack('CCNN', $b1, 127, $length);
        }
        
        // Suppress warnings in case the socket closed between check and write
        @fwrite($client, $header . $text);
    }

    protected function unmaskPayload(string $data): ?string
    {
        if (strlen($data) < 2) return null;
        
        $length = ord($data[1]) & 127;
        
        if ($length == 126) {
            $masks = substr($data, 4, 4);
            $data = substr($data, 8);
        } elseif ($length == 127) {
            $masks = substr($data, 10, 4);
            $data = substr($data, 14);
        } else {
            $masks = substr($data, 2, 4);
            $data = substr($data, 6);
        }
        
        $text = '';
        for ($i = 0; $i < strlen($data); ++$i) {
            $text .= $data[$i] ^ $masks[$i % 4];
        }
        return $text;
    }
}
