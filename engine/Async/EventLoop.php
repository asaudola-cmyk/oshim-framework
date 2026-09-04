<?php
declare(strict_types=1);

namespace Oshim\Async;

use Oshim\Async\Timer\Timer;
use Oshim\Async\Timer\TimerQueue;
use SplQueue;

/**
 * Reactor Pattern Event Loop using stream_select and PHP Fibers.
 */
final class EventLoop
{
    private static ?self $instance = null;

    /** @var array<int, resource> */
    private array $readStreams = [];
    /** @var array<int, callable> */
    private array $readCallbacks = [];

    /** @var array<int, resource> */
    private array $writeStreams = [];
    /** @var array<int, callable> */
    private array $writeCallbacks = [];

    private TimerQueue $timerQueue;
    /** @var SplQueue<callable> */
    private SplQueue $microtasks;
    /** @var array<int, callable> */
    private array $signalHandlers = [];
    private bool $running = false;
    private bool $stopRequested = false;

    public function __construct()
    {
        $this->timerQueue = new TimerQueue();
        $this->microtasks = new SplQueue();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function reset(): void
    {
        if (self::$instance !== null) {
            foreach (self::$instance->readStreams as $stream) {
                if (is_resource($stream)) {
                    @fclose($stream);
                }
            }
            foreach (self::$instance->writeStreams as $stream) {
                if (is_resource($stream)) {
                    @fclose($stream);
                }
            }
            self::$instance->readStreams = [];
            self::$instance->readCallbacks = [];
            self::$instance->writeStreams = [];
            self::$instance->writeCallbacks = [];
            self::$instance->timerQueue = new TimerQueue();
            self::$instance->microtasks = new SplQueue();
            self::$instance->running = false;
            self::$instance->stopRequested = false;
            self::$instance = null;
        }
        if (class_exists(FiberScheduler::class)) {
            FiberScheduler::setInstance(null);
        }
    }

    public static function setInstance(?self $instance): void
    {
        if ($instance === null && self::$instance !== null) {
            self::reset();
            return;
        }
        self::$instance = $instance;
    }

    public function addReadStream(mixed $stream, callable $callback): void
    {
        if (!is_resource($stream)) {
            return;
        }
        $id = (int)$stream;
        $this->readStreams[$id] = $stream;
        $this->readCallbacks[$id] = $callback;
    }

    public function removeReadStream(mixed $stream): void
    {
        if (!is_resource($stream)) {
            return;
        }
        $id = (int)$stream;
        unset($this->readStreams[$id], $this->readCallbacks[$id]);
    }

    public function addWriteStream(mixed $stream, callable $callback): void
    {
        if (!is_resource($stream)) {
            return;
        }
        $id = (int)$stream;
        $this->writeStreams[$id] = $stream;
        $this->writeCallbacks[$id] = $callback;
    }

    public function removeWriteStream(mixed $stream): void
    {
        if (!is_resource($stream)) {
            return;
        }
        $id = (int)$stream;
        unset($this->writeStreams[$id], $this->writeCallbacks[$id]);
    }

    public function removeStream(mixed $stream): void
    {
        $this->removeReadStream($stream);
        $this->removeWriteStream($stream);
    }

    public function defer(callable $callback): void
    {
        $this->microtasks->enqueue($callback);
    }

    public function setTimeout(callable $callback, int $ms): Timer
    {
        return $this->timerQueue->add($ms, false, $callback);
    }

    public function setInterval(callable $callback, int $ms): Timer
    {
        return $this->timerQueue->add($ms, true, $callback);
    }

    public function cancelTimer(Timer $timer): void
    {
        $this->timerQueue->cancel($timer);
    }

    public function addSignal(int $signal, callable $handler): void
    {
        if (function_exists('pcntl_signal')) {
            $this->signalHandlers[$signal] = $handler;
            pcntl_signal($signal, $handler);
        }
    }

    public function removeSignal(int $signal): void
    {
        if (function_exists('pcntl_signal')) {
            unset($this->signalHandlers[$signal]);
            pcntl_signal($signal, SIG_DFL);
        }
    }

    /**
     * Run a single tick of the event loop.
     *
     * @param float $maxTimeout Max seconds to block in stream_select (0 = non-blocking)
     * @return bool True if active watchers remain
     */
    public function tick(float $maxTimeout = 0.0): bool
    {
        // 1. Dispatch signals
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }

        // 2. Process microtasks
        while (!$this->microtasks->isEmpty()) {
            $task = $this->microtasks->dequeue();
            $task();
        }

        // 3. Process timers
        $this->timerQueue->tick();

        // 4. Determine stream_select timeout
        $timerTimeout = $this->timerQueue->getTimeout();
        $timeout = $maxTimeout;

        if ($timerTimeout !== null) {
            $timeout = $maxTimeout > 0.0 ? min($maxTimeout, $timerTimeout) : $timerTimeout;
        }

        if (!$this->microtasks->isEmpty()) {
            $timeout = 0.0;
        }

        // 5. Multiplex I/O streams via stream_select
        $read = $this->readStreams;
        $write = $this->writeStreams;
        $except = null;

        if (!empty($read) || !empty($write)) {
            $tvSec = (int)floor($timeout);
            $tvUsec = (int)(($timeout - $tvSec) * 1000000);

            $numChanged = @stream_select($read, $write, $except, $tvSec, $tvUsec);

            if ($numChanged !== false && $numChanged > 0) {
                // Process readable streams
                foreach ($read as $stream) {
                    $id = (int)$stream;
                    if (isset($this->readCallbacks[$id])) {
                        ($this->readCallbacks[$id])($stream);
                    }
                }

                // Process writable streams
                foreach ($write as $stream) {
                    $id = (int)$stream;
                    if (isset($this->writeCallbacks[$id])) {
                        ($this->writeCallbacks[$id])($stream);
                    }
                }
            }
        } elseif ($timeout > 0.0) {
            usleep((int)($timeout * 1000000));
        }

        return $this->hasActiveWatchers();
    }

    /**
     * Run the event loop until no active watchers remain or stop() is requested.
     */
    public function run(): void
    {
        $this->running = true;
        $this->stopRequested = false;

        while ($this->running && !$this->stopRequested && $this->hasActiveWatchers()) {
            $this->tick(0.01);
        }

        $this->running = false;
    }

    public function stop(): void
    {
        $this->stopRequested = true;
        $this->running = false;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function hasActiveWatchers(): bool
    {
        return !empty($this->readStreams)
            || !empty($this->writeStreams)
            || $this->timerQueue->count() > 0
            || !$this->microtasks->isEmpty();
    }

    public function streamCount(): int
    {
        return count($this->readStreams) + count($this->writeStreams);
    }
}
