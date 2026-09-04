<?php
declare(strict_types=1);

namespace Tests\Unit\Cron;

use Oshim\Testing\TestCase;
use Oshim\Cron\Scheduler;

class CronSchedulerTest extends TestCase
{
    public function testSchedulerRegisterAndRunDue(): void
    {
        $scheduler = Scheduler::getInstance();
        $scheduler->clear();

        $executed = false;
        $scheduler::call(function() use (&$executed) {
            $executed = true;
        }, 'Test Closure Task')->everyMinute();

        $this->assertCount(1, $scheduler->getEvents());

        $results = $scheduler->runDue();
        $this->assertTrue($executed);
        $this->assertCount(1, $results);
        $this->assertSame('Test Closure Task', $results[0]['description']);
        $this->assertSame('SUCCESS', $results[0]['status']);
    }
}
