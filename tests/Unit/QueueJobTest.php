<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Queue\Job\BaseJob;
use Oshim\Queue\Drivers\SyncQueueDriver;
use Oshim\Queue\Drivers\FileQueueDriver;
use Oshim\Queue\QueueManager;
use Oshim\Queue\Queue;
use Oshim\Queue\Worker\QueueWorker;

class SampleEmailJob extends BaseJob
{
    public static bool $executed = false;

    public function handle(): void
    {
        self::$executed = true;
    }
}

final class QueueJobTest extends TestCase
{
    public function testSyncQueueExecution(): void
    {
        SampleEmailJob::$executed = false;
        $driver = new SyncQueueDriver();

        $job = new SampleEmailJob();
        $driver->push($job);

        $this->assertTrue(SampleEmailJob::$executed);
    }

    public function testFileQueueAndWorker(): void
    {
        $driver = new FileQueueDriver();
        $driver->clear('test-queue');

        $job = (new SampleEmailJob())->onQueue('test-queue');
        $driver->push($job, 'test-queue');

        $this->assertSame(1, $driver->size('test-queue'));

        $popped = $driver->pop('test-queue');
        $this->assertNotNull($popped);
        $this->assertInstanceOf(SampleEmailJob::class, $popped);
        $this->assertSame(1, $popped->getAttempts());

        $driver->clear('test-queue');
    }
}
