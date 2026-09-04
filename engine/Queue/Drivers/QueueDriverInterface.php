<?php
declare(strict_types=1);

namespace Oshim\Queue\Drivers;

use Oshim\Queue\Job\JobInterface;

interface QueueDriverInterface
{
    public function push(JobInterface $job, string $queue = 'default'): void;
    public function later(int $delaySeconds, JobInterface $job, string $queue = 'default'): void;
    public function pop(string $queue = 'default'): ?JobInterface;
    public function size(string $queue = 'default'): int;
    public function clear(string $queue = 'default'): void;
}
