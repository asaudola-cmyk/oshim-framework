<?php
declare(strict_types=1);

namespace Oshim\Queue\Job;

interface JobInterface
{
    public function handle(): void;
    public function getQueue(): string;
    public function getAttempts(): int;
    public function incrementAttempts(): void;
    public function getMaxRetries(): int;
}
