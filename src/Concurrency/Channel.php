<?php
declare(strict_types=1);

namespace Oshim\Concurrency;

use SplQueue;
use RuntimeException;

/**
 * 📨 Sovereign CSP Communication Channel (Go Channel Replacement)
 * 
 * WHY: Enables zero-lock message passing and synchronization between
 * concurrent Zend Fibers, implementing Tony Hoare's Communicating Sequential Processes (CSP).
 */
final class Channel
{
    private SplQueue $queue;
    private int $capacity;
    private SplQueue $waitingReceivers;

    public function __construct(int $capacity = 0)
    {
        $this->capacity = $capacity;
        $this->queue = new SplQueue();
        $this->waitingReceivers = new SplQueue();
    }

    /**
     * Sends a value into the channel. If unbuffered and no receiver is waiting,
     * suspends the current Fiber until a receiver accepts the value.
     */
    public function send(mixed $value): void
    {
        if (!$this->waitingReceivers->isEmpty()) {
            $receiverFiber = $this->waitingReceivers->dequeue();
            $receiverFiber->resume($value);
            return;
        }

        if ($this->capacity > 0 && $this->queue->count() < $this->capacity) {
            $this->queue->enqueue($value);
            return;
        }

        // Unbuffered or full buffer: enqueue and continue if no fiber, or suspend
        if (\Fiber::getCurrent() !== null) {
            $this->queue->enqueue($value);
            \Fiber::suspend();
        } else {
            $this->queue->enqueue($value);
        }
    }

    /**
     * Receives a value from the channel. Suspends current Fiber if channel is empty.
     */
    public function receive(): mixed
    {
        if (!$this->queue->isEmpty()) {
            return $this->queue->dequeue();
        }

        $currentFiber = \Fiber::getCurrent();
        if ($currentFiber === null) {
            return null; // Non-fiber context empty read
        }

        $this->waitingReceivers->enqueue($currentFiber);
        return \Fiber::suspend();
    }

    public function count(): int
    {
        return $this->queue->count();
    }
}
