<?php
declare(strict_types=1);

namespace Oshim\Queue\Drivers;

use Oshim\Queue\Job\JobInterface;

class SyncQueueDriver implements QueueDriverInterface
{
    public function push(JobInterface $job, string $queue = 'default'): void
    {
        $job->incrementAttempts();
        $job->handle();
    }

    public function later(int $delaySeconds, JobInterface $job, string $queue = 'default'): void
    {
        if ($delaySeconds > 0) {
            sleep($delaySeconds);
        }
        $this->push($job, $queue);
    }

    public function pop(string $queue = 'default'): ?JobInterface
    {
        return null;
    }

    public function size(string $queue = 'default'): int
    {
        return 0;
    }

    public function clear(string $queue = 'default'): void
    {
    }
}
