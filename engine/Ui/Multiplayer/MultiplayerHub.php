<?php
declare(strict_types=1);

namespace Oshim\Ui\Multiplayer;

use Throwable;

/**
 * High-Performance Central Hub for Real-Time Multiplayer, Live Cursors, and Presence over WebSockets.
 */
class MultiplayerHub
{
    /** @var array<string, MultiplayerRoom> */
    private array $rooms = [];

    /** @var array<string, array{roomId: string, peerId: string}> */
    private array $connections = [];

    public function getOrCreateRoom(string $roomId, string $name = ''): MultiplayerRoom
    {
        if (!isset($this->rooms[$roomId])) {
            $this->rooms[$roomId] = new MultiplayerRoom($roomId, $name);
        }
        return $this->rooms[$roomId];
    }

    public function getRoom(string $roomId): ?MultiplayerRoom
    {
        return $this->rooms[$roomId] ?? null;
    }

    public function removeRoom(string $roomId): bool
    {
        if (isset($this->rooms[$roomId])) {
            unset($this->rooms[$roomId]);
            return true;
        }
        return false;
    }

    public function bindConnection(string $connectionId, string $roomId, string $peerId): void
    {
        $this->connections[$connectionId] = [
            'roomId' => $roomId,
            'peerId' => $peerId,
        ];
    }

    /**
     * @return array{roomId: string, peerId: string}|null
     */
    public function getConnectionMapping(string $connectionId): ?array
    {
        return $this->connections[$connectionId] ?? null;
    }

    /**
     * Process an incoming message from a WebSocket client.
     *
     * @param string $connectionId Unique socket connection ID
     * @param string $rawJson JSON payload
     * @param callable(string $json): void $sendToSender Callback to reply to the sender
     * @param callable(string $roomId, string $json, ?string $excludePeerId): void $broadcastToRoom Callback to broadcast
     */
    public function handleMessage(
        string $connectionId,
        string $rawJson,
        callable $sendToSender,
        callable $broadcastToRoom
    ): void {
        try {
            $msg = MultiplayerMessage::fromJson($rawJson);
        } catch (Throwable $e) {
            $errMsg = MultiplayerMessage::create(
                MultiplayerMessage::TYPE_ERROR,
                'system',
                'server',
                ['error' => 'Malformed JSON: ' . $e->getMessage()]
            );
            $sendToSender($errMsg->toJson());
            return;
        }

        $roomId = $msg->roomId;
        $room = $this->getOrCreateRoom($roomId);
        $senderId = $msg->senderId;

        switch ($msg->type) {
            case MultiplayerMessage::TYPE_JOIN:
                $peerData = (array) ($msg->payload['peer'] ?? []);
                $peerData['id'] = $senderId;
                $peer = Peer::fromArray($peerData);

                $joinMsg = $room->join($peer);
                $this->bindConnection($connectionId, $roomId, $senderId);

                // 1. Send full state sync to joining peer
                $syncMsg = $room->createSyncMessage($senderId);
                $sendToSender($syncMsg->toJson());

                // 2. Broadcast join event to other peers in room
                $broadcastToRoom($roomId, $joinMsg->toJson(), $senderId);
                break;

            case MultiplayerMessage::TYPE_HEARTBEAT:
                $room->heartbeat($senderId);
                $ack = MultiplayerMessage::create(
                    MultiplayerMessage::TYPE_HEARTBEAT,
                    $roomId,
                    'server',
                    ['ack' => true, 'timestamp' => microtime(true)]
                );
                $sendToSender($ack->toJson());
                break;

            case MultiplayerMessage::TYPE_PRESENCE:
                $presenceMsg = $room->updatePresence($senderId, $msg->payload);
                if ($presenceMsg !== null) {
                    $broadcastToRoom($roomId, $presenceMsg->toJson(), $senderId);
                }
                break;

            case MultiplayerMessage::TYPE_STATE_MUTATE:
                $key = (string) ($msg->payload['key'] ?? '');
                $val = $msg->payload['value'] ?? null;
                if ($key !== '') {
                    $mutateMsg = $room->mutateState($senderId, $key, $val);
                    $broadcastToRoom($roomId, $mutateMsg->toJson(), null);
                }
                break;

            case MultiplayerMessage::TYPE_BROADCAST:
                // Forward custom event (reactions, live typing, annotations)
                $broadcastToRoom($roomId, $msg->toJson(), $senderId);
                break;

            case MultiplayerMessage::TYPE_LEAVE:
                $this->handleDisconnect($connectionId, $broadcastToRoom);
                break;
        }
    }

    /**
     * Handle client disconnect event.
     *
     * @param string $connectionId
     * @param callable(string $roomId, string $json, ?string $excludePeerId): void $broadcastToRoom
     * @return array{roomId: string, peerId: string}|null
     */
    public function handleDisconnect(string $connectionId, callable $broadcastToRoom): ?array
    {
        $mapping = $this->connections[$connectionId] ?? null;
        if ($mapping === null) {
            return null;
        }

        unset($this->connections[$connectionId]);
        $roomId = $mapping['roomId'];
        $peerId = $mapping['peerId'];

        $room = $this->getRoom($roomId);
        if ($room !== null) {
            $leaveMsg = $room->leave($peerId);
            if ($leaveMsg !== null) {
                $broadcastToRoom($roomId, $leaveMsg->toJson(), null);
            }
            if ($room->isEmpty()) {
                $this->removeRoom($roomId);
            }
        }

        return $mapping;
    }

    /**
     * Periodic sweep to prune stale peers from all rooms.
     *
     * @param callable(string $roomId, string $json, ?string $excludePeerId): void $broadcastToRoom
     * @return array<string, list<string>> Map of roomId => list of pruned peerIds
     */
    public function pruneAllRooms(callable $broadcastToRoom): array
    {
        $prunedMap = [];

        foreach ($this->rooms as $roomId => $room) {
            $pruned = $room->pruneStalePeers();
            if (!empty($pruned)) {
                $prunedMap[$roomId] = $pruned;
                foreach ($pruned as $stalePeerId) {
                    // Remove matching connection mapping if any
                    foreach ($this->connections as $connId => $map) {
                        if ($map['roomId'] === $roomId && $map['peerId'] === $stalePeerId) {
                            unset($this->connections[$connId]);
                        }
                    }

                    $leaveMsg = MultiplayerMessage::create(
                        MultiplayerMessage::TYPE_LEAVE,
                        $roomId,
                        $stalePeerId,
                        ['peerId' => $stalePeerId, 'reason' => 'timeout']
                    );
                    $broadcastToRoom($roomId, $leaveMsg->toJson(), null);
                }
            }

            if ($room->isEmpty()) {
                unset($this->rooms[$roomId]);
            }
        }

        return $prunedMap;
    }

    public function getActiveRoomCount(): int
    {
        return count($this->rooms);
    }

    public function getTotalPeerCount(): int
    {
        $total = 0;
        foreach ($this->rooms as $room) {
            $total += $room->getPeerCount();
        }
        return $total;
    }
}
