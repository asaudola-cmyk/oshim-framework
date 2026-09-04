<?php
declare(strict_types=1);

namespace Oshim\WebRtc;

use Oshim\Http\WebSocket\WebSocketServer;
use Oshim\Http\WebSocket\WebSocketConnection;
use Oshim\Async\EventLoop;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Sovereign Async WebRTC Signaling Server.
 * Coordinates multi-tenant media rooms, RFC 4566 SDP offer/answer state machines,
 * Trickle ICE candidate routing, live chat, and screen share negotiations.
 */
class WebRtcSignalingServer
{
    private WebSocketServer $wsServer;
    private MediaRoomManager $roomManager;
    private IceCandidateRouter $iceRouter;

    /** @var array<string, SdpSession> */
    private array $sessions = [];

    private ?EventLoop $loop;

    /** @var resource|null */
    private $serverSocket = null;
    private bool $running = false;

    /** @var array<string, array{peerId: string, roomId: string, userName: string}> */
    private array $connToPeer = [];

    /** @var array<string, WebSocketConnection> */
    private array $peerToConn = [];

    public function __construct(
        ?WebSocketServer $wsServer = null,
        ?MediaRoomManager $roomManager = null,
        ?IceCandidateRouter $iceRouter = null,
        ?EventLoop $loop = null
    ) {
        $this->wsServer = $wsServer ?? new WebSocketServer();
        $this->roomManager = $roomManager ?? new MediaRoomManager();
        $this->iceRouter = $iceRouter ?? new IceCandidateRouter();
        $this->loop = $loop;

        $this->wsServer->onMessage(function (WebSocketConnection $conn, string $rawMessage) {
            $this->handleMessage($conn, $rawMessage);
        });

        $this->wsServer->onClose(function (WebSocketConnection $conn) {
            $this->handleDisconnect($conn);
        });
    }

    /**
     * Get the active MediaRoomManager instance.
     */
    public function getRoomManager(): MediaRoomManager
    {
        return $this->roomManager;
    }

    /**
     * Get the active IceCandidateRouter instance.
     */
    public function getIceRouter(): IceCandidateRouter
    {
        return $this->iceRouter;
    }

    /**
     * Get the underlying WebSocketServer instance.
     */
    public function getWebSocketServer(): WebSocketServer
    {
        return $this->wsServer;
    }

    /**
     * Get all active SDP negotiation sessions.
     *
     * @return array<string, SdpSession>
     */
    public function getSessions(): array
    {
        return $this->sessions;
    }

    /**
     * Get a specific SDP negotiation session.
     */
    public function getSession(string $sessionId): ?SdpSession
    {
        return $this->sessions[$sessionId] ?? null;
    }

    /**
     * Handle incoming WebSocket message string.
     */
    public function handleMessage(WebSocketConnection $conn, string $rawMessage): void
    {
        try {
            $payload = json_decode($rawMessage, true);
            if (!is_array($payload)) {
                $conn->sendJson([
                    'type' => 'error',
                    'message' => 'Malformed JSON signaling payload.',
                ]);
                return;
            }

            $response = $this->handleRawPayload($payload, $conn);
            if (!empty($response) && isset($response['send_to_caller']) && $response['send_to_caller'] === true) {
                $conn->sendJson($response['data']);
            }
        } catch (Throwable $e) {
            $conn->sendJson([
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Process raw signaling payload dictionary.
     *
     * @param array $payload
     * @param WebSocketConnection|null $conn
     * @return array{send_to_caller?: bool, data?: array}
     */
    public function handleRawPayload(array $payload, ?WebSocketConnection $conn = null): array
    {
        $action = (string)($payload['action'] ?? $payload['type'] ?? '');

        switch ($action) {
            case 'join_room':
            case 'join':
                return $this->handleJoinRoom($payload, $conn);

            case 'leave_room':
            case 'leave':
                return $this->handleLeaveRoom($payload, $conn);

            case 'offer':
                return $this->handleOffer($payload, $conn);

            case 'answer':
                return $this->handleAnswer($payload, $conn);

            case 'ice_candidate':
            case 'candidate':
                return $this->handleIceCandidate($payload, $conn);

            case 'screen_share':
                return $this->handleScreenShare($payload, $conn);

            case 'mute_toggle':
                return $this->handleMuteToggle($payload, $conn);

            case 'chat_message':
            case 'chat':
                return $this->handleChatMessage($payload, $conn);

            case 'ping':
                return [
                    'send_to_caller' => true,
                    'data' => [
                        'type' => 'pong',
                        'timestamp' => microtime(true),
                    ],
                ];

            default:
                return [
                    'send_to_caller' => true,
                    'data' => [
                        'type' => 'error',
                        'message' => "Unknown signaling action '{$action}'.",
                    ],
                ];
        }
    }

    /**
     * Handle join_room signaling action.
     */
    private function handleJoinRoom(array $payload, ?WebSocketConnection $conn): array
    {
        $roomId = (string)($payload['roomId'] ?? $payload['room'] ?? 'default-room');
        $peerId = (string)($payload['peerId'] ?? ($conn ? $conn->getId() : uniqid('peer_')));
        $userName = (string)($payload['userName'] ?? $payload['name'] ?? 'Guest');
        $metadata = (array)($payload['metadata'] ?? []);

        if ($conn !== null) {
            $connId = $conn->getId();
            $this->connToPeer[$connId] = [
                'peerId' => $peerId,
                'roomId' => $roomId,
                'userName' => $userName,
            ];
            $this->peerToConn[$peerId] = $conn;
            $this->wsServer->joinRoom($connId, $roomId);
        }

        $joinResult = $this->roomManager->joinRoom($roomId, $peerId, $userName, $metadata);

        // Notify other room participants of the new arrival
        $broadcastPayload = json_encode([
            'type' => 'peer_joined',
            'action' => 'peer_joined',
            'roomId' => $roomId,
            'peer' => $joinResult['participant'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $this->wsServer->broadcastToRoom($roomId, $broadcastPayload ?: '{}', $conn?->getId());

        return [
            'send_to_caller' => true,
            'data' => [
                'type' => 'room_joined',
                'action' => 'room_joined',
                'roomId' => $roomId,
                'participant' => $joinResult['participant'],
                'existingPeers' => $joinResult['existingPeers'],
                'room' => $joinResult['room'],
            ],
        ];
    }

    /**
     * Handle leave_room signaling action.
     */
    private function handleLeaveRoom(array $payload, ?WebSocketConnection $conn): array
    {
        $connId = $conn?->getId();
        $roomId = (string)($payload['roomId'] ?? ($connId && isset($this->connToPeer[$connId]) ? $this->connToPeer[$connId]['roomId'] : ''));
        $peerId = (string)($payload['peerId'] ?? ($connId && isset($this->connToPeer[$connId]) ? $this->connToPeer[$connId]['peerId'] : ''));

        $removed = $this->roomManager->leaveRoom($roomId, $peerId);

        if ($connId !== null) {
            unset($this->connToPeer[$connId]);
            unset($this->peerToConn[$peerId]);
            $this->wsServer->leaveRoom($connId, $roomId);
        }

        $broadcastPayload = json_encode([
            'type' => 'peer_left',
            'action' => 'peer_left',
            'roomId' => $roomId,
            'peerId' => $peerId,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $this->wsServer->broadcastToRoom($roomId, $broadcastPayload ?: '{}', $connId);

        return [
            'send_to_caller' => true,
            'data' => [
                'type' => 'left_room',
                'action' => 'left_room',
                'roomId' => $roomId,
                'peerId' => $peerId,
                'success' => $removed !== null,
            ],
        ];
    }

    /**
     * Handle SDP Offer dispatch.
     */
    private function handleOffer(array $payload, ?WebSocketConnection $conn): array
    {
        $sessionId = (string)($payload['sessionId'] ?? uniqid('sdp_sess_'));
        $fromPeer = (string)($payload['fromPeer'] ?? ($conn && isset($this->connToPeer[$conn->getId()]) ? $this->connToPeer[$conn->getId()]['peerId'] : ''));
        $toPeer = (string)($payload['toPeer'] ?? '');
        $sdp = (string)($payload['sdp'] ?? '');
        $roomId = (string)($payload['roomId'] ?? '');

        if ($sdp === '') {
            throw new InvalidArgumentException("Missing SDP string in offer payload.");
        }

        $session = $this->sessions[$sessionId] ?? new SdpSession($sessionId, $fromPeer, $toPeer);
        $session->setOffer($sdp);
        $this->sessions[$sessionId] = $session;

        $capabilities = SdpSession::extractMediaCapabilities($sdp);
        $targetConn = $this->peerToConn[$toPeer] ?? null;

        if ($targetConn !== null) {
            $targetConn->sendJson([
                'type' => 'offer',
                'action' => 'offer',
                'sessionId' => $sessionId,
                'fromPeer' => $fromPeer,
                'toPeer' => $toPeer,
                'sdp' => $sdp,
                'roomId' => $roomId,
                'capabilities' => $capabilities,
            ]);
        }

        return [
            'send_to_caller' => true,
            'data' => [
                'type' => 'offer_sent',
                'action' => 'offer_sent',
                'sessionId' => $sessionId,
                'fromPeer' => $fromPeer,
                'toPeer' => $toPeer,
                'capabilities' => $capabilities,
                'delivered' => $targetConn !== null,
            ],
        ];
    }

    /**
     * Handle SDP Answer dispatch and candidate flush.
     */
    private function handleAnswer(array $payload, ?WebSocketConnection $conn): array
    {
        $sessionId = (string)($payload['sessionId'] ?? '');
        $fromPeer = (string)($payload['fromPeer'] ?? ($conn && isset($this->connToPeer[$conn->getId()]) ? $this->connToPeer[$conn->getId()]['peerId'] : ''));
        $toPeer = (string)($payload['toPeer'] ?? '');
        $sdp = (string)($payload['sdp'] ?? '');
        $roomId = (string)($payload['roomId'] ?? '');

        if ($sdp === '') {
            throw new InvalidArgumentException("Missing SDP string in answer payload.");
        }

        $session = $this->sessions[$sessionId] ?? new SdpSession($sessionId, $toPeer, $fromPeer);
        $session->setAnswer($sdp);
        $this->sessions[$sessionId] = $session;

        $capabilities = SdpSession::extractMediaCapabilities($sdp);
        $targetConn = $this->peerToConn[$toPeer] ?? null;

        if ($targetConn !== null) {
            $targetConn->sendJson([
                'type' => 'answer',
                'action' => 'answer',
                'sessionId' => $sessionId,
                'fromPeer' => $fromPeer,
                'toPeer' => $toPeer,
                'sdp' => $sdp,
                'roomId' => $roomId,
                'capabilities' => $capabilities,
            ]);
        }

        // Flush queued Trickle ICE candidates
        $flushedCount = $this->iceRouter->flushBufferedCandidates($sessionId, function (array $cand) use ($toPeer, $fromPeer, $sessionId) {
            $destPeer = (string)($cand['toPeer'] ?? $toPeer);
            $originPeer = (string)($cand['fromPeer'] ?? $fromPeer);
            $target = $this->peerToConn[$destPeer] ?? null;

            if ($target !== null) {
                $target->sendJson([
                    'type' => 'ice_candidate',
                    'action' => 'ice_candidate',
                    'sessionId' => $sessionId,
                    'fromPeer' => $originPeer,
                    'toPeer' => $destPeer,
                    'candidate' => $cand['candidate'] ?? $cand,
                ]);
            }
        });

        return [
            'send_to_caller' => true,
            'data' => [
                'type' => 'answer_sent',
                'action' => 'answer_sent',
                'sessionId' => $sessionId,
                'fromPeer' => $fromPeer,
                'toPeer' => $toPeer,
                'capabilities' => $capabilities,
                'flushedCandidates' => $flushedCount,
                'delivered' => $targetConn !== null,
            ],
        ];
    }

    /**
     * Handle Trickle ICE candidate routing or buffering.
     */
    private function handleIceCandidate(array $payload, ?WebSocketConnection $conn): array
    {
        $sessionId = (string)($payload['sessionId'] ?? '');
        $fromPeer = (string)($payload['fromPeer'] ?? ($conn && isset($this->connToPeer[$conn->getId()]) ? $this->connToPeer[$conn->getId()]['peerId'] : ''));
        $toPeer = (string)($payload['toPeer'] ?? '');
        $candidate = (array)($payload['candidate'] ?? []);

        $delivered = false;
        $targetConn = $this->peerToConn[$toPeer] ?? null;

        if ($targetConn !== null) {
            $delivered = $this->iceRouter->routeCandidate($fromPeer, $toPeer, $candidate, function ($to, $cand, $from) use ($sessionId, $targetConn) {
                return $targetConn->sendJson([
                    'type' => 'ice_candidate',
                    'action' => 'ice_candidate',
                    'sessionId' => $sessionId,
                    'fromPeer' => $from,
                    'toPeer' => $to,
                    'candidate' => $cand,
                ]);
            });
        }

        if (!$delivered && $sessionId !== '') {
            $this->iceRouter->bufferCandidate($sessionId, [
                'sessionId' => $sessionId,
                'fromPeer' => $fromPeer,
                'toPeer' => $toPeer,
                'candidate' => $candidate,
            ]);
        }

        return [
            'send_to_caller' => true,
            'data' => [
                'type' => 'candidate_routed',
                'action' => 'candidate_routed',
                'sessionId' => $sessionId,
                'delivered' => $delivered,
                'buffered' => !$delivered,
            ],
        ];
    }

    /**
     * Handle screen share broadcasting.
     */
    private function handleScreenShare(array $payload, ?WebSocketConnection $conn): array
    {
        $roomId = (string)($payload['roomId'] ?? '');
        $peerId = (string)($payload['peerId'] ?? ($conn && isset($this->connToPeer[$conn->getId()]) ? $this->connToPeer[$conn->getId()]['peerId'] : ''));
        $active = (bool)($payload['active'] ?? false);

        $this->roomManager->updateParticipantMetadata($roomId, $peerId, ['screenSharing' => $active]);

        $broadcastPayload = json_encode([
            'type' => 'screen_share_changed',
            'action' => 'screen_share_changed',
            'roomId' => $roomId,
            'peerId' => $peerId,
            'active' => $active,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $this->wsServer->broadcastToRoom($roomId, $broadcastPayload ?: '{}', $conn?->getId());

        return [
            'send_to_caller' => true,
            'data' => [
                'type' => 'screen_share_ack',
                'action' => 'screen_share_ack',
                'roomId' => $roomId,
                'peerId' => $peerId,
                'active' => $active,
            ],
        ];
    }

    /**
     * Handle participant audio/video mute toggle.
     */
    private function handleMuteToggle(array $payload, ?WebSocketConnection $conn): array
    {
        $roomId = (string)($payload['roomId'] ?? '');
        $peerId = (string)($payload['peerId'] ?? ($conn && isset($this->connToPeer[$conn->getId()]) ? $this->connToPeer[$conn->getId()]['peerId'] : ''));
        $track = (string)($payload['track'] ?? 'audio');
        $muted = (bool)($payload['muted'] ?? false);

        $key = $track === 'video' ? 'mutedVideo' : 'mutedAudio';
        $this->roomManager->updateParticipantMetadata($roomId, $peerId, [$key => $muted]);

        $broadcastPayload = json_encode([
            'type' => 'peer_mute_status',
            'action' => 'peer_mute_status',
            'roomId' => $roomId,
            'peerId' => $peerId,
            'track' => $track,
            'muted' => $muted,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $this->wsServer->broadcastToRoom($roomId, $broadcastPayload ?: '{}', $conn?->getId());

        return [
            'send_to_caller' => true,
            'data' => [
                'type' => 'mute_toggle_ack',
                'action' => 'mute_toggle_ack',
                'roomId' => $roomId,
                'peerId' => $peerId,
                'track' => $track,
                'muted' => $muted,
            ],
        ];
    }

    /**
     * Handle live text chat broadcasting in media rooms.
     */
    private function handleChatMessage(array $payload, ?WebSocketConnection $conn): array
    {
        $roomId = (string)($payload['roomId'] ?? '');
        $peerId = (string)($payload['peerId'] ?? ($conn && isset($this->connToPeer[$conn->getId()]) ? $this->connToPeer[$conn->getId()]['peerId'] : ''));
        $userName = (string)($payload['userName'] ?? ($conn && isset($this->connToPeer[$conn->getId()]) ? $this->connToPeer[$conn->getId()]['userName'] : 'Guest'));
        $text = (string)($payload['text'] ?? $payload['message'] ?? '');

        $chatRecord = [
            'type' => 'chat_message',
            'action' => 'chat_message',
            'roomId' => $roomId,
            'peerId' => $peerId,
            'userName' => $userName,
            'text' => $text,
            'timestamp' => microtime(true),
        ];

        $broadcastPayload = json_encode($chatRecord, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->wsServer->broadcastToRoom($roomId, $broadcastPayload ?: '{}');

        return [
            'send_to_caller' => true,
            'data' => [
                'type' => 'chat_ack',
                'action' => 'chat_ack',
                'message' => $chatRecord,
            ],
        ];
    }

    /**
     * Automatic cleanup on client disconnection.
     */
    private function handleDisconnect(WebSocketConnection $conn): void
    {
        $connId = $conn->getId();
        if (isset($this->connToPeer[$connId])) {
            $info = $this->connToPeer[$connId];
            $roomId = $info['roomId'];
            $peerId = $info['peerId'];

            $this->roomManager->leaveRoom($roomId, $peerId);

            $broadcastPayload = json_encode([
                'type' => 'peer_left',
                'action' => 'peer_left',
                'roomId' => $roomId,
                'peerId' => $peerId,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $this->wsServer->broadcastToRoom($roomId, $broadcastPayload ?: '{}', $connId);

            unset($this->peerToConn[$peerId]);
            unset($this->connToPeer[$connId]);
        }
    }

    /**
     * Start the TCP listening socket for WebSocket signaling.
     */
    public function listen(string $host = '0.0.0.0', int $port = 9090): string
    {
        $errno = 0;
        $errstr = '';
        $flags = STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;
        $context = stream_context_create([
            'socket' => [
                'so_reuseport' => 1,
                'so_reuseaddr' => 1,
            ],
        ]);

        $this->serverSocket = @stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr, $flags, $context);
        if (!is_resource($this->serverSocket)) {
            $this->serverSocket = @stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr, $flags);
        }

        if (is_resource($this->serverSocket)) {
            stream_set_blocking($this->serverSocket, false);
            $this->running = true;
        }

        return "ws://{$host}:{$port}";
    }

    /**
     * Stop the server socket and disconnect clients.
     */
    public function stop(): void
    {
        $this->running = false;

        foreach ($this->peerToConn as $conn) {
            $conn->close();
        }

        $this->peerToConn = [];
        $this->connToPeer = [];

        if (is_resource($this->serverSocket)) {
            @fclose($this->serverSocket);
            $this->serverSocket = null;
        }
    }
}
