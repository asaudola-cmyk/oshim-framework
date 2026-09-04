<?php
declare(strict_types=1);

namespace Oshim\Async;

use Fiber;

final class Task extends Promise
{
    private Fiber $fiber;
    private int $id;
    private static int $idSequence = 0;
    private bool $cancelled = false;

    public function __construct(Fiber $fiber)
    {
        parent::__construct();
        $this->fiber = $fiber;
        $this->id = ++self::$idSequence;
    }

    public function getFiber(): Fiber
    {
        return $this->fiber;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function isRunning(): bool
    {
        return $this->fiber->isRunning();
    }

    public function isStarted(): bool
    {
        return $this->fiber->isStarted();
    }

    public function isSuspended(): bool
    {
        return $this->fiber->isSuspended();
    }

    public function isTerminated(): bool
    {
        return $this->fiber->isTerminated();
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    public function cancel(): void
    {
        $this->cancelled = true;
        if ($this->isPending()) {
            $this->reject(new \RuntimeException("Task {$this->id} was cancelled."));
        }
    }
}
