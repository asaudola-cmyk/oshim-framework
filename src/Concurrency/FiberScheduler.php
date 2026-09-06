<?php
declare(strict_types=1);

namespace Oshim\Concurrency;

use Fiber;
use SplQueue;
use Throwable;
use RuntimeException;

/**
 * 🧵 Sovereign Cooperative Green Thread Scheduler (Goroutine Replacement)
 * 
 * WHY: Replaces OS thread context-switching overhead and Go's runtime runtime scheduler
 * with direct cooperative Zend VM Fibers. Thousands of concurrent green threads can run
 * with minimal RAM overhead (~4KB per Fiber vs 2MB per OS thread).
 */
final class FiberScheduler
{
    private SplQueue $readyQueue;
    private bool $running = false;
    private int $completedCount = 0;

    public function __construct()
    {
        $this->readyQueue = new SplQueue();
    }

    /**
     * Spawns a new concurrent cooperative green thread (Fiber).
     * 
     * @param callable $task The coroutine function to execute.
     * @return Fiber The instantiated fiber.
     */
    public function spawn(callable $task): Fiber
    {
        $fiber = new Fiber($task);
        $this->readyQueue->enqueue($fiber);
        return $fiber;
    }

    /**
     * Yields current execution slice to the next ready fiber in queue.
     */
    public static function yield(): void
    {
        $current = Fiber::getCurrent();
        if ($current !== null) {
            Fiber::suspend();
        }
    }

    /**
     * Runs the event loop, driving all spawned coroutines to completion.
     */
    public function run(): int
    {
        if ($this->running) {
            throw new RuntimeException('Fiber scheduler is already running');
        }

        $this->running = true;
        $this->completedCount = 0;

        while (!$this->readyQueue->isEmpty()) {
            /** @var Fiber $fiber */
            $fiber = $this->readyQueue->dequeue();

            try {
                if (!$fiber->isStarted()) {
                    $fiber->start();
                } elseif ($fiber->isSuspended()) {
                    $fiber->resume();
                }

                if ($fiber->isTerminated()) {
                    $this->completedCount++;
                } else {
                    // Still suspended, re-enqueue for subsequent round-robin slice
                    $this->readyQueue->enqueue($fiber);
                }
            } catch (Throwable $e) {
                fprintf(stderr, "\033[1;31m[Fiber Error]\033[0m Coroutine failed: %s\n", $e->getMessage());
            }
        }

        $this->running = false;
        return $this->completedCount;
    }

    public function getPendingCount(): int
    {
        return $this->readyQueue->count();
    }

    public function getCompletedCount(): int
    {
        return $this->completedCount;
    }
}
