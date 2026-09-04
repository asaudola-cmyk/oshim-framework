<?php
declare(strict_types=1);

namespace Oshim\Ui\Multiplayer;

/**
 * Peer Model representing a connected collaborator in a real-time room.
 */
class Peer
{
    public string $id;
    public string $name;
    public string $color;
    public ?string $avatar;
    public string $role;
    public PresenceState $presence;
    public float $joinedAt;
    public float $lastSeen;
    public array $metadata;

    private static array $defaultPalette = [
        '#00f2fe', '#4facfe', '#00e5ff', '#10b981', '#34d399',
        '#f59e0b', '#ec4899', '#8b5cf6', '#6366f1', '#f43f5e',
    ];

    public function __construct(
        string $id,
        string $name = 'Collaborator',
        ?string $color = null,
        ?string $avatar = null,
        string $role = 'member',
        ?PresenceState $presence = null,
        array $metadata = []
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->color = $color ?? self::generateColor($id);
        $this->avatar = $avatar;
        $this->role = $role;
        $this->presence = $presence ?? new PresenceState();
        $this->joinedAt = microtime(true);
        $this->lastSeen = microtime(true);
        $this->metadata = $metadata;
    }

    public static function create(
        string $id,
        string $name = 'Collaborator',
        ?string $color = null,
        ?string $avatar = null,
        string $role = 'member',
        array $metadata = []
    ): self {
        return new self($id, $name, $color, $avatar, $role, null, $metadata);
    }

    public static function generateColor(string $seed): string
    {
        $hash = crc32($seed);
        $index = abs($hash) % count(self::$defaultPalette);
        return self::$defaultPalette[$index];
    }

    public function touch(): void
    {
        $this->lastSeen = microtime(true);
    }

    public function isStale(float $timeoutSeconds = 15.0): bool
    {
        return (microtime(true) - $this->lastSeen) > $timeoutSeconds;
    }

    public function updatePresence(array $presenceData): void
    {
        $this->presence = PresenceState::fromArray($presenceData);
        $this->touch();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            'avatar' => $this->avatar,
            'role' => $this->role,
            'presence' => $this->presence->toArray(),
            'joinedAt' => $this->joinedAt,
            'lastSeen' => $this->lastSeen,
            'metadata' => $this->metadata,
        ];
    }

    public static function fromArray(array $data): self
    {
        $presence = isset($data['presence']) && is_array($data['presence'])
            ? PresenceState::fromArray($data['presence'])
            : new PresenceState();

        $peer = new self(
            (string) ($data['id'] ?? bin2hex(random_bytes(4))),
            (string) ($data['name'] ?? 'Collaborator'),
            isset($data['color']) ? (string) $data['color'] : null,
            isset($data['avatar']) ? (string) $data['avatar'] : null,
            (string) ($data['role'] ?? 'member'),
            $presence,
            (array) ($data['metadata'] ?? [])
        );

        if (isset($data['joinedAt'])) {
            $peer->joinedAt = (float) $data['joinedAt'];
        }
        if (isset($data['lastSeen'])) {
            $peer->lastSeen = (float) $data['lastSeen'];
        }

        return $peer;
    }
}
