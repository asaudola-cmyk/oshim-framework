<?php
declare(strict_types=1);

namespace Oshim\Queue;

use Oshim\Queue\Job\JobInterface;

class Queue
{
    private static ?QueueManager $manager = null;

    public static function getManager(): QueueManager
    {
        if (self::$manager === null) {
            self::$manager = new QueueManager();
        }
        return self::$manager;
    }

    public static function push(JobInterface $job, string $queue = 'default'): void
    {
        self::getManager()->push($job, $queue);
    }

    public static function dispatch(JobInterface $job): void
    {
        self::getManager()->push($job, $job->getQueue());
    }

    public static function later(int $delaySeconds, JobInterface $job, string $queue = 'default'): void
    {
        self::getManager()->later($delaySeconds, $job, $queue);
    }

    public static function pop(string $queue = 'default'): ?JobInterface
    {
        return self::getManager()->pop($queue);
    }

    public static function size(string $queue = 'default'): int
    {
        return self::getManager()->size($queue);
    }
}
