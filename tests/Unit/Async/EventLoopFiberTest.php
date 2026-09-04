<?php
declare(strict_types=1);

namespace Tests\Unit\Async;

use Oshim\Testing\TestCase;
use Oshim\Async\EventLoop;
use Oshim\Async\Promise;
use Oshim\Async\Async;
use Oshim\Async\FiberScheduler;
use Oshim\Async\Channel;

class EventLoopFiberTest extends TestCase
{
    public function testPromiseChainingAndCombinators(): void
    {
        $promise = new Promise(function ($resolve) {
            $resolve(10);
        });

        $doubled = $promise->then(fn($v) => $v * 2);
        $this->assertEquals(20, $doubled->getResult());

        // Test Promise::all
        $allPromise = Promise::all([
            Promise::resolved('A'),
            Promise::resolved('B'),
            Promise::resolved('C'),
        ]);
        $this->assertEquals(['A', 'B', 'C'], $allPromise->getResult());

        // Test Promise::race
        $racePromise = Promise::race([
            Promise::resolved('winner'),
            new Promise(), // pending
        ]);
        $this->assertEquals('winner', $racePromise->getResult());
    }

    public function testAsyncRunAndAwaitInsideFiber(): void
    {
        $task = Async::run(function () {
            $val1 = Async::await(Promise::resolved(50));
            $val2 = Async::await(Promise::resolved(25));
            return $val1 + $val2;
        });

        $result = Async::await($task);
        $this->assertEquals(75, $result);
    }

    public function testAsyncAllRunsConcurrentTasks(): void
    {
        $results = Async::all([
            fn() => 100,
            fn() => 200,
            fn() => 300,
        ]);

        $this->assertEquals([100, 200, 300], $results);
    }

    public function testAsyncSleepExecution(): void
    {
        $start = microtime(true);

        Async::run(function () {
            Async::sleep(0.02); // 20ms
        });

        $loop = EventLoop::getInstance();
        $loop->run();

        $elapsed = microtime(true) - $start;
        $this->assertTrue($elapsed >= 0.015, "Elapsed time should be at least 15ms.");
    }

    public function testCspChannelMessagePassing(): void
    {
        $channel = new Channel(2); // Buffered channel with capacity 2

        $producer = Async::run(function () use ($channel) {
            $channel->send('message_alpha');
            $channel->send('message_beta');
        });

        $consumer = Async::run(function () use ($channel) {
            $msg1 = $channel->receive();
            $msg2 = $channel->receive();
            return "{$msg1}:{$msg2}";
        });

        $result = Async::await($consumer);
        $this->assertEquals('message_alpha:message_beta', $result);
    }

    public function testCspChannelSuspensionWithBackpressure(): void
    {
        $channel = new Channel(0); // Unbuffered channel requires sender and receiver to synchronize
        $events = [];

        Async::run(function () use ($channel, &$events) {
            $events[] = 'producer_send_1';
            $channel->send(100);
            $events[] = 'producer_send_2';
            $channel->send(200);
            $events[] = 'producer_done';
        });

        Async::run(function () use ($channel, &$events) {
            $events[] = 'consumer_rec_1';
            $v1 = $channel->receive();
            $events[] = "consumer_got_{$v1}";
            $events[] = 'consumer_rec_2';
            $v2 = $channel->receive();
            $events[] = "consumer_got_{$v2}";
        });

        $loop = EventLoop::getInstance();
        $loop->run();

        $this->assertContains('producer_send_1', $events);
        $this->assertContains('consumer_got_100', $events);
        $this->assertContains('consumer_got_200', $events);
        $this->assertContains('producer_done', $events);
    }
}
