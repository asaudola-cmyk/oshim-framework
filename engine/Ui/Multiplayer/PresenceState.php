<?php
declare(strict_types=1);

namespace Oshim\Ui\Multiplayer;

/**
 * Value Object representing a peer's real-time presence telemetry (cursor, focus, viewport).
 */
class PresenceState
{
    public float $cursorX;
    public float $cursorY;
    public bool $cursorActive;
    public string $cursorState; // 'default', 'pointer', 'dragging', 'clicking'
    public ?string $targetSelector;
    public ?string $activeTab;
    public array $viewport;
    public string $status; // 'online', 'idle', 'busy', 'offline'
    public float $latencyMs;

    public function __construct(
        float $cursorX = 0.0,
        float $cursorY = 0.0,
        bool $cursorActive = true,
        string $cursorState = 'default',
        ?string $targetSelector = null,
        ?string $activeTab = null,
        array $viewport = ['width' => 1920, 'height' => 1080, 'scrollX' => 0, 'scrollY' => 0],
        string $status = 'online',
        float $latencyMs = 0.0
    ) {
        $this->cursorX = $cursorX;
        $this->cursorY = $cursorY;
        $this->cursorActive = $cursorActive;
        $this->cursorState = $cursorState;
        $this->targetSelector = $targetSelector;
        $this->activeTab = $activeTab;
        $this->viewport = $viewport;
        $this->status = $status;
        $this->latencyMs = $latencyMs;
    }

    public static function create(): self
    {
        return new self();
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (float) ($data['cursorX'] ?? $data['x'] ?? 0.0),
            (float) ($data['cursorY'] ?? $data['y'] ?? 0.0),
            (bool) ($data['cursorActive'] ?? true),
            (string) ($data['cursorState'] ?? 'default'),
            isset($data['targetSelector']) ? (string) $data['targetSelector'] : null,
            isset($data['activeTab']) ? (string) $data['activeTab'] : null,
            (array) ($data['viewport'] ?? ['width' => 1920, 'height' => 1080, 'scrollX' => 0, 'scrollY' => 0]),
            (string) ($data['status'] ?? 'online'),
            (float) ($data['latencyMs'] ?? 0.0)
        );
    }

    public function toArray(): array
    {
        return [
            'cursorX' => $this->cursorX,
            'cursorY' => $this->cursorY,
            'cursorActive' => $this->cursorActive,
            'cursorState' => $this->cursorState,
            'targetSelector' => $this->targetSelector,
            'activeTab' => $this->activeTab,
            'viewport' => $this->viewport,
            'status' => $this->status,
            'latencyMs' => $this->latencyMs,
        ];
    }
}
