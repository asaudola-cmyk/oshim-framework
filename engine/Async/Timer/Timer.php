<?php
declare(strict_types=1);

namespace Oshim\Async\Timer;

final class Timer
{
    public int $id;
    public float $executionTime; // High-resolution timestamp (microtime(true))
    public int $intervalMs;
    public bool $periodic;
    /** @var callable */
    public $callback;
    public bool $cancelled = false;

    public function __construct(int $id, float $executionTime, int $intervalMs, bool $periodic, callable $callback)
    {
        $this->id = $id;
        $this->executionTime = $executionTime;
        $this->intervalMs = $intervalMs;
        $this->periodic = $periodic;
        $this->callback = $callback;
    }

    public function cancel(): void
    {
        $this->cancelled = true;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    public function isPeriodic(): bool
    {
        return $this->periodic;
    }

    public function reschedule(): void
    {
        $this->executionTime = microtime(true) + ($this->intervalMs / 1000.0);
    }
}
