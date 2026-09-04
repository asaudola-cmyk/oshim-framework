<?php
declare(strict_types=1);

namespace Oshim\Queue;

use Oshim\Queue\Drivers\QueueDriverInterface;
use Oshim\Queue\Drivers\SyncQueueDriver;
use Oshim\Queue\Drivers\FileQueueDriver;
use Oshim\Queue\Job\JobInterface;
use InvalidArgumentException;

class QueueManager
{
    /** @var array<string, QueueDriverInterface> */
    private array $drivers = [];
    private string $defaultDriver = 'file';

    public function __construct(string $defaultDriver = 'file')
    {
        $this->defaultDriver = $defaultDriver;
    }

    public function driver(?string $name = null): QueueDriverInterface
    {
        $name = $name ?? $this->defaultDriver;

        if (isset($this->drivers[$name])) {
            return $this->drivers[$name];
        }

        return $this->drivers[$name] = match ($name) {
            'sync' => new SyncQueueDriver(),
            'file' => new FileQueueDriver(),
            default => throw new InvalidArgumentException("Unsupported queue driver: {$name}"),
        };
    }

    public function push(JobInterface $job, string $queue = 'default'): void
    {
        $this->driver()->push($job, $queue);
    }

    public function later(int $delaySeconds, JobInterface $job, string $queue = 'default'): void
    {
        $this->driver()->later($delaySeconds, $job, $queue);
    }

    public function pop(string $queue = 'default'): ?JobInterface
    {
        return $this->driver()->pop($queue);
    }

    public function size(string $queue = 'default'): int
    {
        return $this->driver()->size($queue);
    }
}
