<?php
declare(strict_types=1);

namespace Oshim\Queue\Drivers;

use Oshim\Queue\Job\JobInterface;

class FileQueueDriver implements QueueDriverInterface
{
    private string $queueDir;

    public function __construct(?string $queueDir = null)
    {
        $this->queueDir = $queueDir ?? (dirname(__DIR__, 3) . '/storage/framework/queue');
        if (!is_dir($this->queueDir)) {
            @mkdir($this->queueDir, 0755, true);
        }
    }

    public function push(JobInterface $job, string $queue = 'default'): void
    {
        $dir = $this->getQueueDir($queue);
        $id = sprintf('%.6f_%s', microtime(true), uniqid('job_', true));
        $file = $dir . '/' . $id . '.job';

        $payload = serialize([
            'job' => $job,
            'available_at' => time(),
        ]);

        file_put_contents($file, $payload, LOCK_EX);
    }

    public function later(int $delaySeconds, JobInterface $job, string $queue = 'default'): void
    {
        $dir = $this->getQueueDir($queue);
        $id = sprintf('%.6f_%s', microtime(true), uniqid('job_', true));
        $file = $dir . '/' . $id . '.job';

        $payload = serialize([
            'job' => $job,
            'available_at' => time() + $delaySeconds,
        ]);

        file_put_contents($file, $payload, LOCK_EX);
    }

    public function pop(string $queue = 'default'): ?JobInterface
    {
        $dir = $this->getQueueDir($queue);
        $files = glob($dir . '/*.job') ?: [];
        sort($files);

        $now = time();
        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if ($content === false) continue;

            $payload = @unserialize($content);
            if (!is_array($payload) || !isset($payload['job'])) {
                @unlink($file);
                continue;
            }

            if (($payload['available_at'] ?? 0) <= $now) {
                // Atomic delete / take job
                if (@unlink($file)) {
                    $job = $payload['job'];
                    if ($job instanceof JobInterface) {
                        $job->incrementAttempts();
                        return $job;
                    }
                }
            }
        }

        return null;
    }

    public function size(string $queue = 'default'): int
    {
        $dir = $this->getQueueDir($queue);
        $files = glob($dir . '/*.job') ?: [];
        return count($files);
    }

    public function clear(string $queue = 'default'): void
    {
        $dir = $this->getQueueDir($queue);
        $files = glob($dir . '/*.job') ?: [];
        foreach ($files as $file) {
            @unlink($file);
        }
    }

    private function getQueueDir(string $queue): string
    {
        $dir = $this->queueDir . '/' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $queue);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }
}
