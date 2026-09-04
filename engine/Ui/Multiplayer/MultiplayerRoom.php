<?php
declare(strict_types=1);

namespace Oshim\Ui\Multiplayer;

/**
 * Real-Time Collaboration Room managing connected peers, presence heartbeats, and shared state.
 */
class MultiplayerRoom
{
    public string $id;
    public string $name;
    public SharedStateStore $state;
    public float $createdAt;
    public float $heartbeatTimeout = 15.0;

    /** @var array<string, Peer> */
    private array $peers = [];

    public function __construct(string $id, string $name = '', float $heartbeatTimeout = 15.0)
    {
        $this->id = $id;
        $this->name = $name !== '' ? $name : $id;
        $this->state = new SharedStateStore();
        $this->createdAt = microtime(true);
        $this->heartbeatTimeout = $heartbeatTimeout;
    }

    public static function create(string $id, string $name = '', float $heartbeatTimeout = 15.0): self
    {
        return new self($id, $name, $heartbeatTimeout);
    }

    /**
     * Add a peer to this room and return the join notification message.
     */
    public function join(Peer $peer): MultiplayerMessage
    {
        $peer->touch();
        $this->peers[$peer->id] = $peer;

        return MultiplayerMessage::create(
            MultiplayerMessage::TYPE_JOIN,
            $this->id,
            $peer->id,
            [
                'peer' => $peer->toArray(),
                'totalPeers' => count($this->peers),
            ]
        );
    }

    /**
     * Remove a peer from this room.
     */
    public function leave(string $peerId): ?MultiplayerMessage
    {
        if (!isset($this->peers[$peerId])) {
            return null;
        }

        $peer = $this->peers[$peerId];
        unset($this->peers[$peerId]);

        return MultiplayerMessage::create(
            MultiplayerMessage::TYPE_LEAVE,
            $this->id,
            $peerId,
            [
                'peerId' => $peerId,
                'name' => $peer->name,
                'totalPeers' => count($this->peers),
            ]
        );
    }

    /**
     * Update peer live presence (cursors, hover, active selection, viewport).
     */
    public function updatePresence(string $peerId, array $presenceData): ?MultiplayerMessage
    {
        if (!isset($this->peers[$peerId])) {
            return null;
        }

        $peer = $this->peers[$peerId];
        $peer->updatePresence($presenceData);

        return MultiplayerMessage::create(
            MultiplayerMessage::TYPE_PRESENCE,
            $this->id,
            $peerId,
            [
                'peerId' => $peerId,
                'name' => $peer->name,
                'color' => $peer->color,
                'presence' => $peer->presence->toArray(),
            ]
        );
    }

    /**
     * Record a heartbeat from a connected peer.
     */
    public function heartbeat(string $peerId): bool
    {
        if (!isset($this->peers[$peerId])) {
            return false;
        }

        $this->peers[$peerId]->touch();
        return true;
    }

    /**
     * Prune peers that have timed out without a heartbeat.
     *
     * @return list<string> IDs of pruned peers
     */
    public function pruneStalePeers(): array
    {
        $pruned = [];
        foreach ($this->peers as $peerId => $peer) {
            if ($peer->isStale($this->heartbeatTimeout)) {
                $pruned[] = $peerId;
                unset($this->peers[$peerId]);
            }
        }
        return $pruned;
    }

    /**
     * Mutate a shared key-value pair and return broadcast mutation message.
     */
    public function mutateState(string $peerId, string $key, mixed $value): MultiplayerMessage
    {
        $record = $this->state->set($key, $value, $peerId);

        return MultiplayerMessage::create(
            MultiplayerMessage::TYPE_STATE_MUTATE,
            $this->id,
            $peerId,
            $record
        );
    }

    /**
     * Generate complete snapshot sync for a newly connected peer.
     */
    public function createSyncMessage(string $recipientId): MultiplayerMessage
    {
        $peersList = [];
        foreach ($this->peers as $p) {
            $peersList[] = $p->toArray();
        }

        return MultiplayerMessage::create(
            MultiplayerMessage::TYPE_STATE_SYNC,
            $this->id,
            'server',
            [
                'roomId' => $this->id,
                'peers' => $peersList,
                'state' => $this->state->getSnapshot(),
            ]
        );
    }

    public function getPeer(string $peerId): ?Peer
    {
        return $this->peers[$peerId] ?? null;
    }

    /**
     * @return array<string, Peer>
     */
    public function getPeers(): array
    {
        return $this->peers;
    }

    public function getPeerCount(): int
    {
        return count($this->peers);
    }

    public function isEmpty(): bool
    {
        return empty($this->peers);
    }
}
