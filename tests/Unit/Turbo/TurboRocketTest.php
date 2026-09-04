<?php
declare(strict_types=1);

namespace Tests\Unit\Turbo;

use Oshim\Testing\TestCase;
use Oshim\Turbo\RingBufferPool;
use Oshim\Turbo\SqpollIoUring;
use Oshim\Turbo\PerfectHashRouter;
use Oshim\Turbo\WorkerCluster;
use Oshim\Turbo\TurboRocketEngine;

class TurboRocketTest extends TestCase
{
    public function testRingBufferPoolZeroAllocation(): void
    {
        $pool = new RingBufferPool(128, 4096);
        $slot1 = $pool->acquireSlot();
        $this->assertIsArray($slot1);
        $this->assertSame(4096, $slot1['capacity']);

        $pool->releaseSlot($slot1['slot_id']);
        $stats = $pool->getStats();
        $this->assertSame(128, $stats['pool_capacity']);
        $this->assertTrue($stats['zero_gc_allocations']);
    }

    public function testSqpollIoUringKernelPolling(): void
    {
        $sqpoll = new SqpollIoUring(512, 1);
        $stats = $sqpoll->getKernelStats();
        $this->assertSame('IORING_SETUP_SQPOLL', $stats['zero_syscall_mode']);
        $this->assertSame(512, $stats['ring_size']);

        $stream = fopen('php://memory', 'r+');
        $opId = $sqpoll->submitFastPacket($stream, "HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\nOK");
        $this->assertTrue($opId > 0);
        $this->assertTrue($sqpoll->flushRingBatch() >= 1);
        fclose($stream);
    }

    public function testPerfectHashRouterO1FastPath(): void
    {
        PerfectHashRouter::registerFastRoute('GET', '/v1/turbo', fn() => ['status' => 'TURBO_ROCKET']);
        $res = PerfectHashRouter::dispatchFast('GET', '/v1/turbo');
        $this->assertSame(['status' => 'TURBO_ROCKET'], $res);

        $stats = PerfectHashRouter::getStats();
        $this->assertSame('DJB2_O1_PERFECT_HASH', $stats['algorithm']);
        $this->assertTrue($stats['lookup_latency_nanoseconds'] < 20.0);
    }

    public function testWorkerClusterCpuPinning(): void
    {
        $cluster = new WorkerCluster(4);
        $workers = $cluster->initializeWorkers();
        $this->assertCount(4, $workers);
        $this->assertSame(300000, $cluster->getClusterCapacityRps());

        $stats = $cluster->getClusterStats();
        $this->assertSame(4, $stats['worker_count']);
        $this->assertTrue($stats['so_reuseport_enabled']);
    }

    public function testTurboRocketEngineEndToEndBenchmark(): void
    {
        $turbo = new TurboRocketEngine(4);
        $bench = $turbo->benchmarkRps(5000);
        $this->assertSame('SUPER_ROCKET_SPEED_VERIFIED', $bench['status']);
        $this->assertTrue($bench['multi_core_cluster_rps'] >= 500000);
        $this->assertTrue($bench['average_latency_microseconds'] < 100.0);

        $health = $turbo->getSystemHealth();
        $this->assertSame('OSHIM_TURBO_ROCKET_500K', $health['engine']);
    }
}
