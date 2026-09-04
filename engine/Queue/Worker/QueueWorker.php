<?php
declare(strict_types=1);

namespace Oshim\Queue\Worker;

use Oshim\Queue\QueueManager;
use Throwable;

class QueueWorker
{
    private QueueManager $manager;

    public function __construct(?QueueManager $manager = null)
    {
        $this->manager = $manager ?? new QueueManager();
    }

    public function runNext(string $queue = 'default'): bool
    {
        $job = $this->manager->pop($queue);
        if ($job === null) {
            return false;
        }

        try {
            $job->handle();
            return true;
        } catch (Throwable $e) {
            if ($job->getAttempts() < $job->getMaxRetries()) {
                // Exponential backoff retry
                $this->manager->later($job->getAttempts() * 5, $job, $queue);
            }
            return false;
        }
    }

    public function daemon(string $queue = 'default', int $sleepMs = 500000, ?int $maxJobs = null): void
    {
        $processed = 0;
        while (true) {
            $hasRun = $this->runNext($queue);
            if ($hasRun) {
                $processed++;
                if ($maxJobs !== null && $processed >= $maxJobs) {
                    break;
                }
            } else {
                usleep($sleepMs);
            }
        }
    }
}
