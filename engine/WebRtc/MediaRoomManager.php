<?php
declare(strict_types=1);

namespace Oshim\WebRtc;

use RuntimeException;

/**
 * Multi-Tenant WebRTC Media Room Manager.
 * Handles room lifecycles, P2P Mesh vs. SFU negotiation, participant states, and broadcasting groups.
 */
class MediaRoomManager
{
    /**
     * @var array<string, array{
     *     id: string,
     *     name: string,
     *     topology: string,
     *     maxParticipants: int,
     *     password: ?string,
     *     createdAt: float,
     *     participants: array<string, array{
     *         peerId: string,
     *         userName: string,
     *         joinedAt: float,
     *         mutedAudio: bool,
     *         mutedVideo: bool,
     *         screenSharing: bool,
     *         metadata: array
     *     }>,
     *     metadata: array
     * }>
     */
    private array $rooms = [];

    /**
     * Create or retrieve a media room with specified topology.
     *
     * @param string $roomId Unique room identifier
     * @param string $name Human-readable room title
     * @param string $topology Mesh or SFU topology ('mesh' | 'sfu')
     * @param int $maxParticipants Maximum peer capacity
     * @param string|null $password Optional room protection passcode
     * @return array The created or existing room data
     */
    public function createRoom(
        string $roomId,
        string $name = '',
        string $topology = 'mesh',
        int $maxParticipants = 50,
        ?string $password = null
    ): array {
        if (isset($this->rooms[$roomId])) {
            return $this->rooms[$roomId];
        }

        $topology = in_array(strtolower($topology), ['sfu', 'mesh'], true) ? strtolower($topology) : 'mesh';

        $room = [
            'id' => $roomId,
            'name' => $name !== '' ? $name : $roomId,
            'topology' => $topology,
            'maxParticipants' => max(2, $maxParticipants),
            'password' => $password,
            'createdAt' => microtime(true),
            'participants' => [],
            'metadata' => [],
        ];

        $this->rooms[$roomId] = $room;
        return $room;
    }

    /**
     * Get a room by ID.
     */
    public function getRoom(string $roomId): ?array
    {
        return $this->rooms[$roomId] ?? null;
    }

    /**
     * Check if a room exists.
     */
    public function hasRoom(string $roomId): bool
    {
        return isset($this->rooms[$roomId]);
    }

    /**
     * Register a peer joining a room.
     *
     * @param string $roomId
     * @param string $peerId
     * @param string $userName
     * @param array $metadata
     * @return array{room: array, participant: array, existingPeers: list<array>}
     * @throws RuntimeException
     */
    public function joinRoom(string $roomId, string $peerId, string $userName, array $metadata = []): array
    {
        if (!isset($this->rooms[$roomId])) {
            $this->createRoom($roomId);
        }

        $room = &$this->rooms[$roomId];

        if (count($room['participants']) >= $room['maxParticipants'] && !isset($room['participants'][$peerId])) {
            throw new RuntimeException("Room '{$roomId}' is full (maximum {$room['maxParticipants']} participants).");
        }

        $participant = [
            'peerId' => $peerId,
            'userName' => $userName !== '' ? $userName : 'Peer-' . substr($peerId, 0, 6),
            'joinedAt' => microtime(true),
            'mutedAudio' => (bool)($metadata['mutedAudio'] ?? false),
            'mutedVideo' => (bool)($metadata['mutedVideo'] ?? false),
            'screenSharing' => (bool)($metadata['screenSharing'] ?? false),
            'metadata' => $metadata,
        ];

        $existingPeers = [];
        foreach ($room['participants'] as $pId => $pData) {
            if ($pId !== $peerId) {
                $existingPeers[] = $pData;
            }
        }

        $room['participants'][$peerId] = $participant;

        return [
            'room' => [
                'id' => $room['id'],
                'name' => $room['name'],
                'topology' => $room['topology'],
                'maxParticipants' => $room['maxParticipants'],
                'participantCount' => count($room['participants']),
            ],
            'participant' => $participant,
            'existingPeers' => $existingPeers,
        ];
    }

    /**
     * Remove a peer from a room.
     *
     * @return array|null The removed participant data, or null if not found
     */
    public function leaveRoom(string $roomId, string $peerId): ?array
    {
        if (!isset($this->rooms[$roomId])) {
            return null;
        }

        $participant = $this->rooms[$roomId]['participants'][$peerId] ?? null;
        if ($participant !== null) {
            unset($this->rooms[$roomId]['participants'][$peerId]);
        }

        return $participant;
    }

    /**
     * Get all participants in a room.
     *
     * @return list<array>
     */
    public function getParticipants(string $roomId): array
    {
        if (!isset($this->rooms[$roomId])) {
            return [];
        }

        return array_values($this->rooms[$roomId]['participants']);
    }

    /**
     * Update dynamic participant state (mute toggles, screen share, metadata).
     */
    public function updateParticipantMetadata(string $roomId, string $peerId, array $metadata): bool
    {
        if (!isset($this->rooms[$roomId]['participants'][$peerId])) {
            return false;
        }

        $participant = &$this->rooms[$roomId]['participants'][$peerId];

        if (array_key_exists('mutedAudio', $metadata)) {
            $participant['mutedAudio'] = (bool)$metadata['mutedAudio'];
        }
        if (array_key_exists('mutedVideo', $metadata)) {
            $participant['mutedVideo'] = (bool)$metadata['mutedVideo'];
        }
        if (array_key_exists('screenSharing', $metadata)) {
            $participant['screenSharing'] = (bool)$metadata['screenSharing'];
        }
        if (array_key_exists('userName', $metadata) && is_string($metadata['userName'])) {
            $participant['userName'] = $metadata['userName'];
        }

        $participant['metadata'] = array_merge($participant['metadata'] ?? [], $metadata);
        return true;
    }

    /**
     * Get total active room count.
     */
    public function getRoomCount(): int
    {
        return count($this->rooms);
    }

    /**
     * Get all active rooms.
     */
    public function getAllRooms(): array
    {
        return $this->rooms;
    }

    /**
     * Destroy a room and clear all participant records.
     */
    public function destroyRoom(string $roomId): void
    {
        unset($this->rooms[$roomId]);
    }
}
