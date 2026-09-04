<?php
declare(strict_types=1);

namespace Oshim\Async\Timer;

final class TimerQueue
{
    /** @var array<int, Timer> */
    private array $timers = [];
    private int $idCounter = 0;

    public function add(int $intervalMs, bool $periodic, callable $callback): Timer
    {
        $id = ++$this->idCounter;
        $executionTime = microtime(true) + ($intervalMs / 1000.0);
        $timer = new Timer($id, $executionTime, $intervalMs, $periodic, $callback);
        $this->timers[$id] = $timer;
        return $timer;
    }

    public function remove(int $timerId): bool
    {
        if (isset($this->timers[$timerId])) {
            $this->timers[$timerId]->cancel();
            unset($this->timers[$timerId]);
            return true;
        }
        return false;
    }

    public function cancel(Timer $timer): void
    {
        $timer->cancel();
        unset($this->timers[$timer->id]);
    }

    /**
     * Seconds until next expiration for stream_select timeout.
     */
    public function getTimeout(): ?float
    {
        if (empty($this->timers)) {
            return null;
        }

        $now = microtime(true);
        $minTime = null;

        foreach ($this->timers as $timer) {
            if ($timer->isCancelled()) {
                continue;
            }
            if ($minTime === null || $timer->executionTime < $minTime) {
                $minTime = $timer->executionTime;
            }
        }

        if ($minTime === null) {
            return null;
        }

        return max(0.0, $minTime - $now);
    }

    /**
     * Executes due timers and reschedules periodic ones.
     */
    public function tick(): int
    {
        if (empty($this->timers)) {
            return 0;
        }

        $now = microtime(true);
        $executed = 0;
        $due = [];

        foreach ($this->timers as $id => $timer) {
            if ($timer->isCancelled()) {
                unset($this->timers[$id]);
                continue;
            }
            if ($timer->executionTime <= $now) {
                $due[] = $timer;
                if ($timer->isPeriodic()) {
                    $timer->reschedule();
                } else {
                    unset($this->timers[$id]);
                }
            }
        }

        foreach ($due as $timer) {
            if (!$timer->isCancelled()) {
                ($timer->callback)($timer);
                $executed++;
            }
        }

        return $executed;
    }

    public function count(): int
    {
        return count($this->timers);
    }

    public function clear(): void
    {
        foreach ($this->timers as $timer) {
            $timer->cancel();
        }
        $this->timers = [];
    }
}
