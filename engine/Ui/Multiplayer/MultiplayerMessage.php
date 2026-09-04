<?php
declare(strict_types=1);

namespace Oshim\Ui\Multiplayer;

/**
 * Standard Telemetry Envelope for Multiplayer WebSocket Protocol.
 */
class MultiplayerMessage
{
    public const TYPE_JOIN = 'join';
    public const TYPE_LEAVE = 'leave';
    public const TYPE_HEARTBEAT = 'heartbeat';
    public const TYPE_PRESENCE = 'presence';
    public const TYPE_STATE_MUTATE = 'state_mutate';
    public const TYPE_STATE_SYNC = 'state_sync';
    public const TYPE_BROADCAST = 'broadcast';
    public const TYPE_PEER_LIST = 'peer_list';
    public const TYPE_ERROR = 'error';

    public string $type;
    public string $roomId;
    public string $senderId;
    public array $payload;
    public float $timestamp;

    public function __construct(
        string $type,
        string $roomId,
        string $senderId,
        array $payload = [],
        ?float $timestamp = null
    ) {
        $this->type = $type;
        $this->roomId = $roomId;
        $this->senderId = $senderId;
        $this->payload = $payload;
        $this->timestamp = $timestamp ?? microtime(true);
    }

    public static function create(
        string $type,
        string $roomId,
        string $senderId,
        array $payload = []
    ): self {
        return new self($type, $roomId, $senderId, $payload);
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \InvalidArgumentException('Invalid JSON payload for MultiplayerMessage');
        }
        return self::fromArray($data);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['type'] ?? self::TYPE_BROADCAST),
            (string) ($data['roomId'] ?? 'default'),
            (string) ($data['senderId'] ?? 'anonymous'),
            (array) ($data['payload'] ?? []),
            isset($data['timestamp']) ? (float) $data['timestamp'] : null
        );
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'roomId' => $this->roomId,
            'senderId' => $this->senderId,
            'payload' => $this->payload,
            'timestamp' => $this->timestamp,
        ];
    }

    public function toJson(): string
    {
        return (string) json_encode($this->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
