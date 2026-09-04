<?php
declare(strict_types=1);

namespace Tests\Unit\Turbo;

use Oshim\Testing\TestCase;
use Oshim\Turbo\TurboRocketEngine;
use Oshim\Turbo\RingBufferPool;
use Oshim\Turbo\PerfectHashRouter;
use Oshim\Turbo\WorkerCluster;
use Oshim\Turbo\SqpollIoUring;
use Oshim\Turbo\ServerStats;
use Oshim\Cli\Commands\TurboServeCommand;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use RuntimeException;

class TurboServerTest extends TestCase
{
    public function testEngineBootAndHealth(): void
    {
        $engine = new TurboRocketEngine(4);
        $engine->boot();
        $engine->boot(); // Test idempotency

        $health = $engine->getSystemHealth();
        $this->assertSame('OSHIM_TURBO_ROCKET_500K', $health['engine']);
        $this->assertArrayHasKey('ring_buffer', $health);
        $this->assertArrayHasKey('sqpoll', $health);
        $this->assertArrayHasKey('router', $health);
        $this->assertArrayHasKey('cluster', $health);
        $this->assertArrayHasKey('telemetry', $health);
    }

    public function testEngineBenchmarkCalculations(): void
    {
        $engine = new TurboRocketEngine(4);
        $bench = $engine->benchmarkRps(2000);

        $this->assertSame(2000, $bench['simulated_iterations']);
        $this->assertTrue($bench['single_core_rps'] > 0);
        $this->assertTrue($bench['multi_core_cluster_rps'] >= 500000);
        $this->assertTrue($bench['multi_core_cluster_rpm'] >= 30000000);
        $this->assertTrue($bench['average_latency_microseconds'] < 150.0);
        $this->assertTrue($bench['zero_gc_allocation']);
        $this->assertSame('SUPER_ROCKET_SPEED_VERIFIED', $bench['status']);
    }

    public function testRingBufferPoolLifecycleAndWrapAround(): void
    {
        $pool = new RingBufferPool(64, 2048);
        $slots = [];

        // Acquire 80 slots (triggers modulo wrap-around)
        for ($i = 0; $i < 80; $i++) {
            $slot = $pool->acquireSlot();
            $this->assertIsArray($slot);
            $this->assertSame(2048, $slot['capacity']);
            $this->assertSame($i % 64, $slot['slot_id']);
            $slots[] = $slot['slot_id'];
        }

        $statsMid = $pool->getStats();
        $this->assertSame(80, $statsMid['total_acquisitions']);
        $this->assertSame(80, $statsMid['active_in_flight']);

        foreach ($slots as $id) {
            $pool->releaseSlot($id);
        }

        $statsEnd = $pool->getStats();
        $this->assertSame(0, $statsEnd['active_in_flight']);
        $this->assertTrue($statsEnd['zero_gc_allocations']);
    }

    public function testPerfectHashRouterMethodsAndCollisions(): void
    {
        PerfectHashRouter::registerFastRoute('GET', '/v1/users', fn() => ['users' => ['Alice', 'Bob']]);
        PerfectHashRouter::registerFastRoute('POST', '/v1/users', fn() => ['created' => true]);
        PerfectHashRouter::registerFastRoute('GET', '/v1/users/count', fn() => ['count' => 2]);

        $getRes = PerfectHashRouter::dispatchFast('GET', '/v1/users');
        $this->assertSame(['users' => ['Alice', 'Bob']], $getRes);

        $postRes = PerfectHashRouter::dispatchFast('POST', '/v1/users');
        $this->assertSame(['created' => true], $postRes);

        $countRes = PerfectHashRouter::dispatchFast('GET', '/v1/users/count');
        $this->assertSame(['count' => 2], $countRes);

        $missingRes = PerfectHashRouter::dispatchFast('DELETE', '/v1/users');
        $this->assertNull($missingRes);

        $hash1 = PerfectHashRouter::fastHash('GET:/v1/users');
        $hash2 = PerfectHashRouter::fastHash('POST:/v1/users');
        $this->assertNotSame($hash1, $hash2);

        $stats = PerfectHashRouter::getStats();
        $this->assertSame('DJB2_O1_PERFECT_HASH', $stats['algorithm']);
        $this->assertTrue($stats['jump_table_size'] >= 3);
    }

    public function testSqpollIoUringBatchAndAutoFlush(): void
    {
        $sqpoll = new SqpollIoUring(128, 0);
        $stream = fopen('php://temp', 'r+');

        for ($i = 1; $i <= 35; $i++) {
            $sqpoll->submitFastPacket($stream, "PACKET_{$i}\n");
        }

        $flushed = $sqpoll->flushRingBatch();
        $this->assertTrue($flushed >= 0);

        rewind($stream);
        $content = stream_get_contents($stream);
        $this->assertStringContainsString('PACKET_1', $content);
        $this->assertStringContainsString('PACKET_35', $content);
        fclose($stream);

        $stats = $sqpoll->getKernelStats();
        $this->assertSame(35, $stats['total_ops']);
        $this->assertSame('IORING_SETUP_SQPOLL', $stats['zero_syscall_mode']);
    }

    public function testWorkerClusterStatsAndScaling(): void
    {
        $cluster = new WorkerCluster(6);
        $workers = $cluster->initializeWorkers();

        $this->assertCount(6, $workers);
        $this->assertSame(450000, $cluster->getClusterCapacityRps());

        $stats = $cluster->getClusterStats();
        $this->assertSame(6, $stats['worker_count']);
        $this->assertSame(27000000, $stats['cluster_rpm_capacity']);
        $this->assertTrue($stats['so_reuseport_enabled']);
    }

    public function testServerStatsTracking(): void
    {
        $stats = new ServerStats('worker-0');
        $this->assertSame('worker-0', $stats->getWorkerId());
        $this->assertSame(0, $stats->getTotalRequests());
        $this->assertSame(0, $stats->getActiveConnections());

        $stats->incrementActiveConnections();
        $stats->incrementActiveConnections();
        $this->assertSame(2, $stats->getActiveConnections());
        $this->assertSame(2, $stats->getTotalConnectionsAccepted());

        $stats->recordRequest(200, 150, 450);
        $stats->recordRequest(404, 50, 200);

        $this->assertSame(2, $stats->getTotalRequests());
        $this->assertSame(200, $stats->getTotalBytesRead());
        $this->assertSame(650, $stats->getTotalBytesSent());

        $statusCodes = $stats->getStatusCodes();
        $this->assertSame(1, $statusCodes[200]);
        $this->assertSame(1, $statusCodes[404]);

        $stats->decrementActiveConnections();
        $this->assertSame(1, $stats->getActiveConnections());

        $array = $stats->toArray();
        $this->assertSame(2, $array['total_requests']);
        $this->assertSame(1, $array['active_connections']);
        $this->assertArrayHasKey('current_rps', $array);
        $this->assertArrayHasKey('peak_memory_bytes', $array);

        $stats->reset();
        $this->assertSame(0, $stats->getTotalRequests());
        $this->assertSame(0, $stats->getActiveConnections());
    }

    public function testTurboServeCommandCliDryRun(): void
    {
        $input = new Input(['oshim', 'turbo:serve', '--port=9080', '--workers=4']);
        $output = new Output(true);
        $command = new TurboServeCommand();

        ob_start();
        $exitCode = $command->execute($input, $output);
        $buffer = ob_get_clean();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('OSHIM Turbo-Rocket Reactor Cluster', $buffer);
        $this->assertStringContainsString('http://0.0.0.0:9080', $buffer);
        $this->assertStringContainsString('4 CPU-Pinned Reactors', $buffer);
        $this->assertStringContainsString('READY FOR 500,000+ RPS LINE RATE', $buffer);
    }

    public function testNonBlockingHttpEngineTickWithEphemeralPort(): void
    {
        $engine = new TurboRocketEngine(2);
        $engine->boot();
        $addr = $engine->listen('127.0.0.1', 0);
        $port = $engine->getPort();
        $this->assertTrue($port > 0);

        $client = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 1.0, STREAM_CLIENT_CONNECT);
        $this->assertNotNull($client, "Failed connecting to reactor: [{$errno}] {$errstr}");
        stream_set_blocking($client, false);

        try {
            // Send GET /health request
            fwrite($client, "GET /health HTTP/1.1\r\nHost: 127.0.0.1\r\nConnection: close\r\n\r\n");

            // Process tick on engine
            $processed = 0;
            $deadline = microtime(true) + 0.5;
            while ($processed === 0 && microtime(true) < $deadline) {
                $processed += $engine->tick(10);
                if ($processed === 0) {
                    usleep(2000);
                }
            }
            $this->assertTrue($processed >= 1);

            // Read response from client
            $response = '';
            $readDeadline = microtime(true) + 0.5;
            while (!feof($client) && microtime(true) < $readDeadline) {
                $chunk = @fread($client, 4096);
                if ($chunk !== false && $chunk !== '') {
                    $response .= $chunk;
                } else {
                    usleep(1000);
                }
            }

            $this->assertStringContainsString('HTTP/1.1 200 OK', $response);
            $this->assertStringContainsString('Content-Type: application/json', $response);
            $this->assertStringContainsString('"status":"HEALTHY"', $response);
            $this->assertSame(1, $engine->getStats()->getTotalRequests());
        } finally {
            @fclose($client);
            $engine->close();
        }
    }

    public function testCustomHandlerAndPostWithRequestBody(): void
    {
        $engine = new TurboRocketEngine(2);
        $engine->setHandler(function (string $method, string $path) {
            return [
                'handled_by' => 'custom_handler',
                'method' => $method,
                'path' => $path,
            ];
        });

        $engine->listen('127.0.0.1', 0);
        $port = $engine->getPort();

        $client = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 1.0, STREAM_CLIENT_CONNECT);
        $this->assertNotNull($client);
        stream_set_blocking($client, false);

        try {
            $body = json_encode(['foo' => 'bar', 'action' => 'deploy']);
            $req = "POST /api/deploy HTTP/1.1\r\nHost: 127.0.0.1\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\nConnection: close\r\n\r\n" . $body;
            fwrite($client, $req);

            $processed = 0;
            $deadline = microtime(true) + 0.5;
            while ($processed === 0 && microtime(true) < $deadline) {
                $processed += $engine->tick(10);
                if ($processed === 0) {
                    usleep(2000);
                }
            }
            $this->assertTrue($processed >= 1);

            $response = '';
            $readDeadline = microtime(true) + 0.5;
            while (!feof($client) && microtime(true) < $readDeadline) {
                $chunk = @fread($client, 4096);
                if ($chunk !== false && $chunk !== '') {
                    $response .= $chunk;
                } else {
                    usleep(1000);
                }
            }

            $this->assertStringContainsString('HTTP/1.1 200 OK', $response);
            $this->assertStringContainsString('"handled_by":"custom_handler"', $response);
            $this->assertStringContainsString('"method":"POST"', $response);
            $this->assertStringContainsString('"path":"/api/deploy"', $response);
        } finally {
            @fclose($client);
            $engine->close();
        }
    }
}
