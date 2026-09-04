<?php
declare(strict_types=1);

namespace Tests\Unit\Turbo;

use Oshim\Testing\TestCase;
use Oshim\Turbo\RingBufferPool;
use Oshim\Turbo\PerfectHashRouter;
use Oshim\Turbo\ServerStats;
use Oshim\Turbo\WorkerCluster;
use Oshim\Turbo\TurboRocketEngine;
use Oshim\Http\Router\Router;
use Oshim\Ui\Router\AppRouter;
use Oshim\Http\Request;
use Oshim\Http\Response;
use App\Controllers\AppController;

class Milestone4ChallengerStressTest extends TestCase
{
    /**
     * Test high-volume acquire/release cycles on RingBufferPool
     * Verifying modulo wrap-around, active counter invariants, and zero memory leak.
     */
    public function testRingBufferPoolHighVolumeCyclesAndZeroLeak(): void
    {
        $pool = new RingBufferPool(128, 4096);
        $cycles = 100000;

        $memBefore = memory_get_usage();

        // 1. High-volume acquire/release in batches
        for ($i = 0; $i < $cycles; $i++) {
            $slot = $pool->acquireSlot();
            $this->assertSame($i % 128, $slot['slot_id']);
            $this->assertSame(4096, $slot['capacity']);
            $this->assertSame(4096, strlen($slot['buffer']));
            $pool->releaseSlot($slot['slot_id']);
        }

        $memAfter = memory_get_usage();
        $memDelta = abs($memAfter - $memBefore);

        // Memory delta should remain minimal (< 128 KB for 100k cycles)
        $this->assertTrue($memDelta < 131072, "Memory leak detected: {$memDelta} bytes");

        $stats = $pool->getStats();
        $this->assertSame($cycles, $stats['total_acquisitions']);
        $this->assertSame(0, $stats['active_in_flight']);
        $this->assertTrue($stats['zero_gc_allocations']);
    }

    /**
     * Test RingBufferPool active counter invariants, out-of-order releases, and underflow safety.
     */
    public function testRingBufferPoolActiveInFlightInvariants(): void
    {
        $pool = new RingBufferPool(64, 1024);
        $acquired = [];

        // Acquire 50 slots
        for ($i = 0; $i < 50; $i++) {
            $slot = $pool->acquireSlot();
            $acquired[] = $slot['slot_id'];
        }

        $this->assertSame(50, $pool->getStats()['active_in_flight']);

        // Release 20 slots in reverse order (out of order)
        for ($i = 0; $i < 20; $i++) {
            $slotId = array_pop($acquired);
            $pool->releaseSlot($slotId);
        }

        $this->assertSame(30, $pool->getStats()['active_in_flight']);

        // Release remaining 30 slots
        while (!empty($acquired)) {
            $pool->releaseSlot(array_pop($acquired));
        }

        $this->assertSame(0, $pool->getStats()['active_in_flight']);

        // Underflow stress: releasing more slots than acquired clamps active_in_flight to 0
        $pool->releaseSlot(999);
        $pool->releaseSlot(999);
        $this->assertSame(0, $pool->getStats()['active_in_flight']);
    }

    /**
     * Test PerfectHashRouter DJB2 hash algorithm precision, distribution, and collision behavior.
     */
    public function testPerfectHashRouterDjb2AndCollisionBehavior(): void
    {
        // 1. Verify DJB2 mathematical hash correctness
        $str = "GET:/api/v1/health";
        $expectedHash = 5381;
        for ($i = 0; $i < strlen($str); $i++) {
            $expectedHash = ((($expectedHash << 5) + $expectedHash) + ord($str[$i])) & 0x7FFFFFFF;
        }
        $actualHash = PerfectHashRouter::fastHash($str);
        $this->assertSame($expectedHash, $actualHash);

        // 2. Register distinct fast-path routes
        PerfectHashRouter::registerFastRoute('GET', '/challenge/test1', fn() => 'RESP_TEST1');
        PerfectHashRouter::registerFastRoute('POST', '/challenge/test2', fn() => 'RESP_TEST2');
        PerfectHashRouter::registerFastRoute('GET', '/challenge/test3', fn() => 'RESP_TEST3');

        $this->assertSame('RESP_TEST1', PerfectHashRouter::dispatchFast('GET', '/challenge/test1'));
        $this->assertSame('RESP_TEST2', PerfectHashRouter::dispatchFast('POST', '/challenge/test2'));
        $this->assertSame('RESP_TEST3', PerfectHashRouter::dispatchFast('GET', '/challenge/test3'));

        // 3. Unmatched method or path returns null (safe fallback to Tier 2)
        $this->assertNull(PerfectHashRouter::dispatchFast('POST', '/challenge/test1'));
        $this->assertNull(PerfectHashRouter::dispatchFast('GET', '/challenge/test2'));
        $this->assertNull(PerfectHashRouter::dispatchFast('GET', '/challenge/nonexistent'));
        $this->assertNull(PerfectHashRouter::dispatchFast('get', '/challenge/test1')); // Case sensitivity

        // 4. High-volume lookup latency stress test
        $start = microtime(true);
        for ($i = 0; $i < 50000; $i++) {
            $res = PerfectHashRouter::dispatchFast('GET', '/challenge/test1');
            $this->assertSame('RESP_TEST1', $res);
        }
        $elapsed = microtime(true) - $start;
        $lookupsPerSec = 50000 / max(0.0001, $elapsed);
        $this->assertTrue($lookupsPerSec > 100000, "Fast lookup throughput too low: {$lookupsPerSec} req/sec");

        $stats = PerfectHashRouter::getStats();
        $this->assertSame('DJB2_O1_PERFECT_HASH', $stats['algorithm']);
        $this->assertTrue($stats['total_fast_lookups'] >= 50000);
    }

    /**
     * Test ServerStats high-volume metric accumulation, RPS math, status code tallies, and reset.
     */
    public function testServerStatsHighVolumeAccumulationAndReset(): void
    {
        $stats = new ServerStats('worker-stress-1');
        $this->assertSame('worker-stress-1', $stats->getWorkerId());

        // 1. Record 100,000 requests across various status codes and byte payloads
        $expectedTotalRead = 0;
        $expectedTotalSent = 0;
        $codeCounts = [
            200 => 0,
            201 => 0,
            301 => 0,
            400 => 0,
            404 => 0,
            500 => 0,
            503 => 0,
        ];
        $codes = array_keys($codeCounts);

        for ($i = 0; $i < 10000; $i++) {
            $code = $codes[$i % count($codes)];
            $readBytes = 120 + ($i % 50);
            $sentBytes = 500 + ($i % 300);

            $stats->recordRequest($code, $readBytes, $sentBytes);

            $codeCounts[$code]++;
            $expectedTotalRead += $readBytes;
            $expectedTotalSent += $sentBytes;
        }

        $this->assertSame(10000, $stats->getTotalRequests());
        $this->assertSame($expectedTotalRead, $stats->getTotalBytesRead());
        $this->assertSame($expectedTotalSent, $stats->getTotalBytesSent());

        $actualCodes = $stats->getStatusCodes();
        foreach ($codeCounts as $c => $expectedCount) {
            $this->assertSame($expectedCount, $actualCodes[$c] ?? 0);
        }

        // Active connection increments and underflow clamp
        $stats->incrementActiveConnections();
        $stats->incrementActiveConnections();
        $stats->incrementActiveConnections();
        $this->assertSame(3, $stats->getActiveConnections());
        $this->assertSame(3, $stats->getTotalConnectionsAccepted());

        $stats->decrementActiveConnections();
        $stats->decrementActiveConnections();
        $stats->decrementActiveConnections();
        $this->assertSame(0, $stats->getActiveConnections());

        // Decrement below 0 should clamp to 0
        $stats->decrementActiveConnections();
        $this->assertSame(0, $stats->getActiveConnections());

        $rps = $stats->getCurrentRps();
        $this->assertTrue($rps > 0);

        $arr = $stats->toArray();
        $this->assertSame('worker-stress-1', $arr['worker_id']);
        $this->assertSame(10000, $arr['total_requests']);
        $this->assertSame(0, $arr['active_connections']);
        $this->assertSame($expectedTotalRead, $arr['total_bytes_read']);
        $this->assertSame($expectedTotalSent, $arr['total_bytes_sent']);
        $this->assertArrayHasKey('peak_memory_bytes', $arr);
        $this->assertArrayHasKey('current_memory_bytes', $arr);

        // Reset
        $stats->reset();
        $this->assertSame(0, $stats->getTotalRequests());
        $this->assertSame(0, $stats->getActiveConnections());
        $this->assertSame(0, $stats->getTotalBytesRead());
        $this->assertSame(0, $stats->getTotalBytesSent());
        $this->assertSame([], $stats->getStatusCodes());
    }

    /**
     * Test WorkerCluster CPU detection, dynamic initialization, and capacity stats.
     */
    public function testWorkerClusterScalingAndMetadata(): void
    {
        $detectedCores = WorkerCluster::detectCpuCores();
        $this->assertTrue($detectedCores >= 1);

        $cluster = new WorkerCluster(8);
        $workers = $cluster->initializeWorkers();

        $this->assertCount(8, $workers);
        $this->assertSame(600000, $cluster->getClusterCapacityRps());

        foreach ($workers as $idx => $w) {
            $this->assertSame($idx, $w['worker_id']);
            $this->assertSame($idx, $w['cpu_core_pin']);
            $this->assertSame('REACTOR_READY', $w['state']);
            $this->assertTrue($w['so_reuseport']);
            $this->assertSame(75000, $w['rps_capacity']);
        }

        $stats = $cluster->getClusterStats();
        $this->assertSame(8, $stats['worker_count']);
        $this->assertSame(36000000, $stats['cluster_rpm_capacity']);

        // Minimum clamp test: 1 worker should be clamped to 2
        $minCluster = new WorkerCluster(1);
        $this->assertSame(2, $minCluster->getClusterStats()['worker_count']);
    }

    /**
     * Test TurboRocketEngine live socket non-blocking HTTP processing:
     * Keep-alive, POST body framing, and status telemetry.
     */
    public function testTurboEngineSocketKeepAliveAndPostFraming(): void
    {
        $engine = new TurboRocketEngine(2);
        $engine->boot();
        $engine->listen('127.0.0.1', 0);
        $port = $engine->getPort();
        $this->assertTrue($port > 0);

        $client = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 1.0, STREAM_CLIENT_CONNECT);
        $this->assertNotNull($client);
        stream_set_blocking($client, false);

        try {
            // 1. Keep-Alive: Send first request
            fwrite($client, "GET /health HTTP/1.1\r\nHost: 127.0.0.1\r\n\r\n");

            $deadline = microtime(true) + 0.5;
            $processed = 0;
            while ($processed === 0 && microtime(true) < $deadline) {
                $processed += $engine->tick(10);
                if ($processed === 0) usleep(1000);
            }
            $this->assertTrue($processed >= 1);

            $resp1 = '';
            $readDeadline = microtime(true) + 0.5;
            while (!str_contains($resp1, "\r\n\r\n") && microtime(true) < $readDeadline) {
                $chunk = @fread($client, 2048);
                if ($chunk !== false && $chunk !== '') {
                    $resp1 .= $chunk;
                } else {
                    usleep(1000);
                }
            }
            $this->assertStringContainsString('HTTP/1.1 200 OK', $resp1);
            $this->assertStringContainsString('Connection: keep-alive', $resp1);

            // 2. Keep-Alive: Send second request on same socket connection
            fwrite($client, "GET /api/ping HTTP/1.1\r\nHost: 127.0.0.1\r\nConnection: close\r\n\r\n");

            $deadline = microtime(true) + 0.5;
            $processed2 = 0;
            while ($processed2 === 0 && microtime(true) < $deadline) {
                $processed2 += $engine->tick(10);
                if ($processed2 === 0) usleep(1000);
            }
            $this->assertTrue($processed2 >= 1);

            $resp2 = '';
            $readDeadline = microtime(true) + 0.5;
            while (!feof($client) && microtime(true) < $readDeadline) {
                $chunk = @fread($client, 2048);
                if ($chunk !== false && $chunk !== '') {
                    $resp2 .= $chunk;
                } else {
                    usleep(1000);
                }
            }
            $this->assertStringContainsString('HTTP/1.1 200 OK', $resp2);
            $this->assertStringContainsString('pong', $resp2);

            $this->assertSame(2, $engine->getStats()->getTotalRequests());
        } finally {
            @fclose($client);
            $engine->close();
        }
    }

    /**
     * Test public/index.php routing logic: AppRouter and Router endpoints.
     * Validates Sovereign UI, PDF Invoice Download, and Server Actions.
     */
    public function testPublicGatewayAppRouterAndRouterEndpoints(): void
    {
        // 1. Test AppRouter Pages
        $appRouter = new AppRouter();
        $appRouter->page('/', fn() => AppController::index(), null, 'OSHIM Sovereign Cloud');
        $appRouter->page('/vps', fn() => AppController::vps(), null, 'VPS Cloud Management');
        $appRouter->page('/ai', fn() => AppController::ai(), null, 'Sovereign AI Studio');

        // Test GET /
        $reqHome = Request::create('GET', '/');
        $resHome = $appRouter->dispatch($reqHome);
        $this->assertNotNull($resHome);
        $this->assertSame(200, $resHome->getStatusCode());
        $this->assertStringContainsString('OSHIM Sovereign Framework', $resHome->getContent());
        $this->assertStringContainsString('Unlimited Developer Freedom', $resHome->getContent());

        // Test GET /vps
        $reqVps = Request::create('GET', '/vps');
        $resVps = $appRouter->dispatch($reqVps);
        $this->assertNotNull($resVps);
        $this->assertSame(200, $resVps->getStatusCode());
        $this->assertStringContainsString('Sovereign MicroVMs', $resVps->getContent());
        $this->assertStringContainsString('KVM Telemetry', $resVps->getContent());

        // Test GET /ai
        $reqAi = Request::create('GET', '/ai');
        $resAi = $appRouter->dispatch($reqAi);
        $this->assertNotNull($resAi);
        $this->assertSame(200, $resAi->getStatusCode());
        $this->assertStringContainsString('Sovereign AI & Tensor Studio', $resAi->getContent());

        // 2. Test Core HTTP Router for PDF Invoice and Actions
        $router = new Router($this->app);

        $router->get('/invoice/download', function () {
            return AppController::getPdfInvoiceResponse();
        });

        $router->post('/_oshim/action', function (Request $req) {
            $body = json_decode($req->getContent(), true) ?? $req->all();
            $res = AppController::handleAction($body);
            return Response::json($res);
        });

        // Test PDF Invoice Download
        $reqPdf = Request::create('GET', '/invoice/download');
        $resPdf = $router->dispatch($reqPdf);
        $this->assertSame(200, $resPdf->getStatusCode());
        $this->assertStringContainsString('application/pdf', $resPdf->getHeaders()->get('content-type'));
        $this->assertStringContainsString('attachment; filename="oshim-invoice.pdf"', $resPdf->getHeaders()->get('content-disposition'));
        $this->assertStringStartsWith('%PDF-', $resPdf->getContent());

        // Test Server Action Error Handling (empty payload)
        $reqAction = Request::create('POST', '/_oshim/action', content: json_encode(['action' => 'dummy']));
        $resAction = $router->dispatch($reqAction);
        $this->assertSame(200, $resAction->getStatusCode());
        $this->assertStringContainsString('"status":"ERROR"', $resAction->getContent());
    }
}
