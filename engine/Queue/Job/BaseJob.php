<?php
declare(strict_types=1);

namespace Oshim\Queue\Job;

abstract class BaseJob implements JobInterface
{
    protected string $queue = 'default';
    protected int $attempts = 0;
    protected int $maxRetries = 3;
    protected int $delaySeconds = 0;

    public function getQueue(): string
    {
        return $this->queue;
    }

    public function onQueue(string $queue): static
    {
        $this->queue = $queue;
        return $this;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function incrementAttempts(): void
    {
        $this->attempts++;
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    public function delay(int $seconds): static
    {
        $this->delaySeconds = $seconds;
        return $this;
    }

    public function getDelay(): int
    {
        return $this->delaySeconds;
    }
}
