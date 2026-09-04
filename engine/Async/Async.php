<?php
declare(strict_types=1);

namespace Oshim\Async;

use Fiber;
use Throwable;
use RuntimeException;

/**
 * High-Level Coroutine & Async Facade.
 */
final class Async
{
    public static function run(callable $fn, mixed ...$args): Task
    {
        return FiberScheduler::getInstance()->spawn($fn, ...$args);
    }

    public static function await(Promise|Task|Fiber $awaitable): mixed
    {
        if ($awaitable instanceof Fiber) {
            $awaitable = new Task($awaitable);
            FiberScheduler::getInstance()->resume($awaitable);
        }

        $currentFiber = Fiber::getCurrent();

        // 1. Inside Fiber execution context
        if ($currentFiber !== null) {
            if ($awaitable->isFulfilled()) {
                return $awaitable->getResult();
            }

            if ($awaitable->isRejected()) {
                $err = $awaitable->getResult();
                if ($err instanceof Throwable) {
                    throw $err;
                }
                throw new RuntimeException((string)$err);
            }

            $scheduler = FiberScheduler::getInstance();
            $currentTask = $scheduler->getCurrentTask();

            if ($currentTask === null) {
                // If task not tracked directly, create on the fly
                $currentTask = new Task($currentFiber);
            }

            $awaitable->then(
                function ($value) use ($scheduler, $currentTask) {
                    $scheduler->resume($currentTask, $value);
                },
                function ($reason) use ($scheduler, $currentTask) {
                    $exception = $reason instanceof Throwable ? $reason : new RuntimeException((string)$reason);
                    $scheduler->throw($currentTask, $exception);
                }
            );

            return Fiber::suspend();
        }

        // 2. Outside Fiber (Top-level synchronous wait via EventLoop pump)
        $loop = EventLoop::getInstance();
        $scheduler = FiberScheduler::getInstance();

        while ($awaitable->isPending()) {
            $scheduler->run();
            $loop->tick(0.005);
        }

        if ($awaitable->isFulfilled()) {
            return $awaitable->getResult();
        }

        $err = $awaitable->getResult();
        if ($err instanceof Throwable) {
            throw $err;
        }

        throw new RuntimeException((string)$err);
    }

    public static function sleep(float $seconds): void
    {
        $ms = (int)round($seconds * 1000);
        $loop = EventLoop::getInstance();

        $promise = new Promise();
        $loop->setTimeout(function () use ($promise) {
            $promise->resolve(null);
        }, max(1, $ms));

        self::await($promise);
    }

    public static function all(array $tasks): array
    {
        $promises = [];
        foreach ($tasks as $task) {
            if (is_callable($task) && !($task instanceof Promise)) {
                $promises[] = self::run($task);
            } else {
                $promises[] = $task;
            }
        }

        return self::await(Promise::all($promises));
    }

    public static function race(array $tasks): mixed
    {
        $promises = [];
        foreach ($tasks as $task) {
            if (is_callable($task) && !($task instanceof Promise)) {
                $promises[] = self::run($task);
            } else {
                $promises[] = $task;
            }
        }

        return self::await(Promise::race($promises));
    }

    public static function yield(): mixed
    {
        return FiberScheduler::getInstance()->yield();
    }
}
