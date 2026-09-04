<?php
declare(strict_types=1);

namespace Oshim\Async;

use Fiber;
use SplQueue;
use Throwable;
use SplObjectStorage;

/**
 * Cooperative Coroutine Scheduler for PHP 8.3 Fibers.
 */
final class FiberScheduler
{
    private static ?self $instance = null;
    private EventLoop $loop;
    /** @var SplQueue<array{task: Task, value: mixed, exception: ?Throwable}> */
    private SplQueue $readyQueue;
    /** @var SplObjectStorage<Task, Fiber> */
    private SplObjectStorage $activeTasks;
    private ?Task $currentTask = null;

    public function __construct(?EventLoop $loop = null)
    {
        $this->loop = $loop ?? EventLoop::getInstance();
        $this->readyQueue = new SplQueue();
        $this->activeTasks = new SplObjectStorage();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function setInstance(?self $instance): void
    {
        self::$instance = $instance;
    }

    public function getCurrentTask(): ?Task
    {
        return $this->currentTask;
    }

    public function spawn(callable $callable, mixed ...$args): Task
    {
        $fiber = new Fiber(function () use ($callable, $args) {
            return $callable(...$args);
        });

        $task = new Task($fiber);
        $this->activeTasks->attach($task, $fiber);

        $this->readyQueue->enqueue([
            'task'      => $task,
            'value'     => null,
            'exception' => null,
        ]);

        // Schedule loop tick to advance tasks
        $this->loop->defer(function () {
            $this->run();
        });

        return $task;
    }

    public function resume(Task $task, mixed $value = null): void
    {
        if ($task->isCancelled() || $task->isTerminated()) {
            return;
        }

        $this->readyQueue->enqueue([
            'task'      => $task,
            'value'     => $value,
            'exception' => null,
        ]);

        $this->loop->defer(function () {
            $this->run();
        });
    }

    public function throw(Task $task, Throwable $exception): void
    {
        if ($task->isCancelled() || $task->isTerminated()) {
            return;
        }

        $this->readyQueue->enqueue([
            'task'      => $task,
            'value'     => null,
            'exception' => $exception,
        ]);

        $this->loop->defer(function () {
            $this->run();
        });
    }

    public function yield(): mixed
    {
        if (Fiber::getCurrent() === null) {
            return null;
        }

        $currentTask = $this->currentTask;
        if ($currentTask !== null) {
            $this->readyQueue->enqueue([
                'task'      => $currentTask,
                'value'     => null,
                'exception' => null,
            ]);
        }

        return Fiber::suspend();
    }

    /**
     * Drain ready queue by stepping tasks.
     */
    public function run(): void
    {
        while (!$this->readyQueue->isEmpty()) {
            $item = $this->readyQueue->dequeue();
            $task = $item['task'];
            $value = $item['value'];
            $exception = $item['exception'];

            if ($task->isCancelled()) {
                $this->activeTasks->detach($task);
                continue;
            }

            $this->step($task, $value, $exception);
        }
    }

    public function step(Task $task, mixed $value = null, ?Throwable $exception = null): void
    {
        $fiber = $task->getFiber();
        $this->currentTask = $task;

        try {
            if (!$fiber->isStarted()) {
                $fiber->start();
            } elseif ($fiber->isSuspended()) {
                if ($exception !== null) {
                    $fiber->throw($exception);
                } else {
                    $fiber->resume($value);
                }
            }

            if ($fiber->isTerminated()) {
                $this->activeTasks->detach($task);
                $task->resolve($fiber->getReturn());
            }
        } catch (Throwable $e) {
            $this->activeTasks->detach($task);
            $task->reject($e);
        } finally {
            $this->currentTask = null;
        }
    }

    public function count(): int
    {
        return $this->activeTasks->count();
    }
}
