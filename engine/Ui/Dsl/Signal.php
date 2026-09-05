<?php
declare(strict_types=1);

namespace Oshim\Ui\Dsl;

/**
 * 👑 Sovereign OSHIM Reactive Signal
 * 
 * WHY: Traditional state re-renders the entire component (React style).
 * Signals (like Solid.js or Vue 3) provide Fine-Grained Reactivity.
 * When a Signal changes, ONLY the exact DOM node bound to it updates.
 */
class Signal
{
    public mixed $value;

    public function __construct(mixed $initialValue = null)
    {
        $this->value = $initialValue;
    }

    public static function create(mixed $value = null): self
    {
        return new self($value);
    }

    public function set(mixed $newValue): void
    {
        // In a full implementation, this triggers the specific DOM node update via WebSocket
        $this->value = $newValue;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return (string)$this->value;
    }
}
