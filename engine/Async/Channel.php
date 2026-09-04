<?php
declare(strict_types=1);

namespace Oshim\Async;

use SplQueue;
use RuntimeException;

/**
 * CSP Inter-Fiber Communication Channel.
 */
final class Channel
{
    private int $capacity;
    /** @var SplQueue<mixed> */
    private SplQueue $buffer;
    /** @var SplQueue<array{task: Task, value: mixed}> */
    private SplQueue $sendWaiters;
    /** @var SplQueue<Task> */
    private SplQueue $receiveWaiters;
    private bool $closed = false;

    public function __construct(int $capacity = 0)
    {
        $this->capacity = max(0, $capacity);
        $this->buffer = new SplQueue();
        $this->sendWaiters = new SplQueue();
        $this->receiveWaiters = new SplQueue();
    }

    public function send(mixed $value): void
    {
        if ($this->closed) {
            throw new RuntimeException("Cannot send to a closed channel.");
        }

        // If there is a waiting receiver
        if (!$this->receiveWaiters->isEmpty()) {
            $receiverTask = $this->receiveWaiters->dequeue();
            FiberScheduler::getInstance()->resume($receiverTask, $value);
            return;
        }

        // If buffer has room
        if ($this->capacity > 0 && $this->buffer->count() < $this->capacity) {
            $this->buffer->enqueue($value);
            return;
        }

        // Suspend until space or receiver available
        $currentTask = FiberScheduler::getInstance()->getCurrentTask();
        if ($currentTask === null) {
            throw new RuntimeException("Channel::send() called outside a Fiber context.");
        }

        $this->sendWaiters->enqueue([
            'task'  => $currentTask,
            'value' => $value,
        ]);

        \Fiber::suspend();
    }

    public function receive(): mixed
    {
        // If items exist in buffer
        if (!$this->buffer->isEmpty()) {
            $val = $this->buffer->dequeue();

            // Resume any blocked sender
            if (!$this->sendWaiters->isEmpty()) {
                $sender = $this->sendWaiters->dequeue();
                $this->buffer->enqueue($sender['value']);
                FiberScheduler::getInstance()->resume($sender['task']);
            }

            return $val;
        }

        // If closed and empty
        if ($this->closed) {
            return null;
        }

        // If a sender is waiting (for unbuffered channel)
        if (!$this->sendWaiters->isEmpty()) {
            $sender = $this->sendWaiters->dequeue();
            FiberScheduler::getInstance()->resume($sender['task']);
            return $sender['value'];
        }

        // Suspend until sender sends data
        $currentTask = FiberScheduler::getInstance()->getCurrentTask();
        if ($currentTask === null) {
            throw new RuntimeException("Channel::receive() called outside a Fiber context.");
        }

        $this->receiveWaiters->enqueue($currentTask);

        return \Fiber::suspend();
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;

        // Wake all waiting receivers with null
        while (!$this->receiveWaiters->isEmpty()) {
            $receiver = $this->receiveWaiters->dequeue();
            FiberScheduler::getInstance()->resume($receiver, null);
        }

        // Wake all waiting senders with exception
        while (!$this->sendWaiters->isEmpty()) {
            $sender = $this->sendWaiters->dequeue();
            FiberScheduler::getInstance()->throw($sender['task'], new RuntimeException("Channel closed while sending."));
        }
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function isEmpty(): bool
    {
        return $this->buffer->isEmpty();
    }

    public function isFull(): bool
    {
        return $this->capacity > 0 && $this->buffer->count() >= $this->capacity;
    }

    public function count(): int
    {
        return $this->buffer->count();
    }

    public function capacity(): int
    {
        return $this->capacity;
    }
}
