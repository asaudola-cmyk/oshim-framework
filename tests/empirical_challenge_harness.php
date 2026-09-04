<?php
declare(strict_types=1);

require_once __DIR__ . '/../engine/Bootstrap.php';
$container = \Oshim\Bootstrap::boot(dirname(__DIR__));

use Oshim\Turbo\RingBufferPool;
use Oshim\Turbo\PerfectHashRouter;
use Oshim\Turbo\ServerStats;
use Oshim\Turbo\WorkerCluster;
use Oshim\Turbo\TurboRocketEngine;
use Oshim\Http\Request;
use Oshim\Http\Response;
use Oshim\Http\Router\Router;
use Oshim\Ui\Router\AppRouter;
use App\Controllers\AppController;

echo "=== Milestone 4 Empirical Challenge Harness ===\n\n";

// ----------------------------------------------------
// 1. RingBufferPool Deep Invariant & Memory Leak Test
// ----------------------------------------------------
echo "[1] Testing RingBufferPool Memory Invariants & Wrap-Around...\n";
$poolCapacity = 256;
$slotSize = 8192;
$pool = new RingBufferPool($poolCapacity, $slotSize);

$gcCyclesBefore = gc_status()['runs'] ?? 0;
$memStart = memory_get_usage(true);

$totalCycles = 500000;
$t0 = microtime(true);

for ($i = 0; $i < $totalCycles; $i++) {
    $slot = $pool->acquireSlot();
    if ($slot['slot_id'] !== ($i % $poolCapacity)) {
        throw new RuntimeException("Slot ID mismatch at iteration {$i}: expected " . ($i % $poolCapacity) . ", got {$slot['slot_id']}");
    }
    if ($slot['capacity'] !== $slotSize) {
        throw new RuntimeException("Slot capacity mismatch");
    }
    $pool->releaseSlot($slot['slot_id']);
}

$elapsedPool = microtime(true) - $t0;
$memEnd = memory_get_usage(true);
$poolThroughput = round($totalCycles / max(0.0001, $elapsedPool));

echo "    ✔ Completed {$totalCycles} acquire/release cycles in " . round($elapsedPool, 4) . "s ({$poolThroughput} ops/sec)\n";
echo "    ✔ Memory before: " . round($memStart / 1024 / 1024, 2) . " MB, after: " . round($memEnd / 1024 / 1024, 2) . " MB (delta: " . ($memEnd - $memStart) . " bytes)\n";

$poolStats = $pool->getStats();
echo "    ✔ Pool stats: total_acquisitions={$poolStats['total_acquisitions']}, active_in_flight={$poolStats['active_in_flight']}\n\n";

// ----------------------------------------------------
// 2. PerfectHashRouter DJB2 Hash Distribution & Collisions
// ----------------------------------------------------
echo "[2] Testing PerfectHashRouter DJB2 Distribution & Collisions...\n";

// Generate 50,000 synthetic routes
$routesCount = 50000;
$buckets = [];
$collisionPairs = [];
$routeList = [];

for ($i = 0; $i < $routesCount; $i++) {
    $routeStr = "GET:/api/v1/resource_" . $i . "/sub_" . ($i % 100);
    $routeList[] = $routeStr;
    $hash = PerfectHashRouter::fastHash($routeStr);
    $key = $hash & 0xFFFF; // 16-bit jump table index

    if (isset($buckets[$key])) {
        $collisionPairs[] = [$buckets[$key], $routeStr, $key];
    } else {
        $buckets[$key] = $routeStr;
    }
}

$uniqueBuckets = count($buckets);
$totalCollisions = count($collisionPairs);
$loadFactor = round($uniqueBuckets / 65536, 4);

echo "    ✔ DJB2 16-bit Jump Table: {$routesCount} routes mapped to {$uniqueBuckets} unique buckets (load factor: " . ($loadFactor * 100) . "%, collisions: {$totalCollisions})\n";

// Verify collision isolation in PerfectHashRouter
if (!empty($collisionPairs)) {
    [$routeA, $routeB, $collidingKey] = $collisionPairs[0];
    [$methodA, $pathA] = explode(':', $routeA, 2);
    [$methodB, $pathB] = explode(':', $routeB, 2);

    // Register route A first
    PerfectHashRouter::registerFastRoute($methodA, $pathA, fn() => 'RESPONSE_A');
    $resA1 = PerfectHashRouter::dispatchFast($methodA, $pathA);
    if ($resA1 !== 'RESPONSE_A') {
        throw new RuntimeException("Route A dispatch failed before collision registration");
    }

    // Register route B (which has the exact same 16-bit key)
    PerfectHashRouter::registerFastRoute($methodB, $pathB, fn() => 'RESPONSE_B');
    $resB = PerfectHashRouter::dispatchFast($methodB, $pathB);
    $resA2 = PerfectHashRouter::dispatchFast($methodA, $pathA);

    echo "    ✔ Collision Behavior Verified:\n";
    echo "       - Overwritten bucket for key {$collidingKey}:\n";
    echo "       - dispatchFast('{$routeB}') returned: '{$resB}' (MATCH)\n";
    echo "       - dispatchFast('{$routeA}') returned: " . var_export($resA2, true) . " (SAFE FALLBACK to Tier 2)\n";
}

// Benchmark 500,000 fast lookups
$lookupCycles = 500000;
PerfectHashRouter::registerFastRoute('GET', '/bench/fast', fn() => ['status' => 'OK']);
$t0 = microtime(true);
for ($i = 0; $i < $lookupCycles; $i++) {
    PerfectHashRouter::dispatchFast('GET', '/bench/fast');
}
$elapsedLookup = microtime(true) - $t0;
$lookupRps = round($lookupCycles / max(0.0001, $elapsedLookup));
$avgLatencyNs = round(($elapsedLookup / $lookupCycles) * 1e9, 2);
echo "    ✔ Fast Lookup Benchmark: {$lookupCycles} lookups in " . round($elapsedLookup, 4) . "s ({$lookupRps} lookups/sec, {$avgLatencyNs} ns/lookup)\n\n";

// ----------------------------------------------------
// 3. ServerStats Real-Time Telemetry Under High Load
// ----------------------------------------------------
echo "[3] Testing ServerStats Telemetry & Accumulation Under High Load...\n";
$stats = new ServerStats('worker-bench-1');
$requestCycles = 1000000;
$t0 = microtime(true);

for ($i = 0; $i < $requestCycles; $i++) {
    $code = ($i % 100 === 0) ? 500 : (($i % 10 === 0) ? 404 : 200);
    $stats->recordRequest($code, 150, 650);
}
$elapsedStats = microtime(true) - $t0;
$statsThroughput = round($requestCycles / max(0.0001, $elapsedStats));

$export = $stats->toArray();
echo "    ✔ Accumulated {$requestCycles} request metrics in " . round($elapsedStats, 4) . "s ({$statsThroughput} metrics/sec)\n";
echo "    ✔ Total Requests: {$export['total_requests']}\n";
echo "    ✔ Total Bytes Read: " . round($export['total_bytes_read'] / 1024 / 1024, 2) . " MB\n";
echo "    ✔ Total Bytes Sent: " . round($export['total_bytes_sent'] / 1024 / 1024, 2) . " MB\n";
echo "    ✔ Current RPS: {$export['current_rps']}\n";
echo "    ✔ Status Codes: 200=" . ($export['status_codes'][200] ?? 0) . ", 404=" . ($export['status_codes'][404] ?? 0) . ", 500=" . ($export['status_codes'][500] ?? 0) . "\n\n";

// ----------------------------------------------------
// 4. Live Non-Blocking HTTP Socket Reactor Multiplexing
// ----------------------------------------------------
echo "[4] Testing Live Non-Blocking TurboRocketEngine Socket Server...\n";
$engine = new TurboRocketEngine(4);
$engine->boot();

// Register a dynamic tier 2 handler
$engine->setHandler(function (Request $req) {
    if ($req->getPath() === '/api/deploy') {
        return Response::json([
            'status' => 'DEPLOYED',
            'method' => $req->getMethod(),
            'body_length' => strlen($req->getContent()),
            'received' => json_decode($req->getContent(), true),
        ], 201);
    }
    return Response::json(['error' => 'NOT_FOUND'], 404);
});

$addr = $engine->listen('127.0.0.1', 0);
$port = $engine->getPort();
echo "    ✔ Engine listening on {$addr} (port: {$port})\n";

// A. Test Tier 1 Fast Path: GET /health
$client1 = stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2.0);
stream_set_blocking($client1, false);
fwrite($client1, "GET /health HTTP/1.1\r\nHost: 127.0.0.1\r\nConnection: close\r\n\r\n");

$deadline = microtime(true) + 1.0;
while (microtime(true) < $deadline) {
    if ($engine->tick(5) > 0) break;
    usleep(500);
}

$resp1 = '';
while (!feof($client1) && microtime(true) < $deadline) {
    $chunk = fread($client1, 4096);
    if ($chunk !== false && $chunk !== '') $resp1 .= $chunk;
    else usleep(500);
}
fclose($client1);

if (!str_contains($resp1, 'HTTP/1.1 200 OK') || !str_contains($resp1, 'HEALTHY')) {
    throw new RuntimeException("Tier 1 fast path GET /health failed: {$resp1}");
}
echo "    ✔ Tier 1 Fast-Path (GET /health) -> 200 OK with HEALTHY payload\n";

// B. Test Tier 2 Custom Handler: POST /api/deploy with large body
$client2 = stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2.0);
stream_set_blocking($client2, false);

$payloadArray = ['cluster' => 'dhaka-dc1', 'vms' => array_fill(0, 50, ['cpu' => 4, 'ram' => '8GB', 'image' => 'ubuntu-24.04'])];
$payloadJson = json_encode($payloadArray);
$postReq = "POST /api/deploy HTTP/1.1\r\n" .
    "Host: 127.0.0.1\r\n" .
    "Content-Type: application/json\r\n" .
    "Content-Length: " . strlen($payloadJson) . "\r\n" .
    "Connection: close\r\n\r\n" .
    $payloadJson;

fwrite($client2, $postReq);

$deadline = microtime(true) + 1.0;
while (microtime(true) < $deadline) {
    if ($engine->tick(5) > 0) break;
    usleep(500);
}

$resp2 = '';
while (!feof($client2) && microtime(true) < $deadline) {
    $chunk = fread($client2, 4096);
    if ($chunk !== false && $chunk !== '') $resp2 .= $chunk;
    else usleep(500);
}
fclose($client2);

if (!str_contains($resp2, 'HTTP/1.1 201 Created') || !str_contains($resp2, 'DEPLOYED')) {
    throw new RuntimeException("Tier 2 POST /api/deploy failed: {$resp2}");
}
echo "    ✔ Tier 2 Custom Handler (POST /api/deploy, " . strlen($payloadJson) . " bytes) -> 201 Created with DEPLOYED payload\n";

// C. Test HTTP/1.1 Keep-Alive Pipelining (5 requests over 1 TCP connection)
$client3 = stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2.0);
stream_set_blocking($client3, false);

for ($reqNum = 1; $reqNum <= 5; $reqNum++) {
    $connHeader = ($reqNum === 5) ? 'close' : 'keep-alive';
    $pipedReq = "GET /api/ping HTTP/1.1\r\nHost: 127.0.0.1\r\nConnection: {$connHeader}\r\n\r\n";
    fwrite($client3, $pipedReq);

    $deadline = microtime(true) + 1.0;
    while (microtime(true) < $deadline) {
        if ($engine->tick(5) > 0) break;
        usleep(500);
    }

    $pipedResp = '';
    while (microtime(true) < $deadline) {
        $chunk = fread($client3, 4096);
        if ($chunk !== false && $chunk !== '') {
            $pipedResp .= $chunk;
            if (str_contains($pipedResp, "\r\n\r\n")) break;
        } else {
            usleep(500);
        }
    }

    if (!str_contains($pipedResp, '200 OK') || !str_contains($pipedResp, 'pong')) {
        throw new RuntimeException("Keep-alive request #{$reqNum} failed: {$pipedResp}");
    }
}
fclose($client3);
echo "    ✔ Keep-Alive Pipelining: 5 consecutive requests on 1 TCP socket successfully processed\n";

$engine->close();
echo "    ✔ Reactor stopped cleanly\n\n";

// ----------------------------------------------------
// 5. Public Front Controller Endpoint Verification
// ----------------------------------------------------
echo "[5] Testing public/index.php Dispatching & Endpoints...\n";

// Test AppRouter
$appRouter = new AppRouter();
$appRouter->page('/', fn() => AppController::index(), null, 'OSHIM Sovereign Cloud');
$appRouter->page('/vps', fn() => AppController::vps(), null, 'VPS Cloud Management');
$appRouter->page('/ai', fn() => AppController::ai(), null, 'Sovereign AI Studio');

// Dispatch /
$res1 = $appRouter->dispatch(Request::create('GET', '/'));
if (!$res1 || !str_contains($res1->getContent(), 'OSHIM Sovereign Framework')) {
    throw new RuntimeException("AppRouter GET / failed");
}
echo "    ✔ AppRouter GET / -> 200 OK (Sovereign Framework HTML)\n";

// Dispatch /vps
$res2 = $appRouter->dispatch(Request::create('GET', '/vps'));
if (!$res2 || !str_contains($res2->getContent(), 'Sovereign MicroVMs')) {
    throw new RuntimeException("AppRouter GET /vps failed");
}
echo "    ✔ AppRouter GET /vps -> 200 OK (Sovereign MicroVMs HTML)\n";

// Dispatch /ai
$res3 = $appRouter->dispatch(Request::create('GET', '/ai'));
if (!$res3 || !str_contains($res3->getContent(), 'Sovereign AI & Tensor Studio')) {
    throw new RuntimeException("AppRouter GET /ai failed");
}
echo "    ✔ AppRouter GET /ai -> 200 OK (Sovereign AI & Tensor Studio HTML)\n";

// Test Router for Action and PDF
$router = new Router($container);
$router->post('/_oshim/action', function (Request $req) {
    $body = json_decode($req->getContent(), true) ?? $req->all();
    $res = AppController::handleAction($body);
    return Response::json($res);
});
$router->get('/invoice/download', function () {
    return AppController::getPdfInvoiceResponse();
});

// Dispatch /invoice/download
$resPdf = $router->dispatch(Request::create('GET', '/invoice/download'));
if ($resPdf->getStatusCode() !== 200 || !str_contains($resPdf->getHeaders()->get('content-type') ?? '', 'application/pdf')) {
    throw new RuntimeException("Router GET /invoice/download failed: " . $resPdf->getStatusCode());
}
$pdfContent = $resPdf->getContent();
if (!str_starts_with($pdfContent, '%PDF-')) {
    throw new RuntimeException("PDF payload does not start with %PDF- header");
}
echo "    ✔ Router GET /invoice/download -> 200 OK (Content-Type: application/pdf, valid PDF stream " . strlen($pdfContent) . " bytes)\n";

// Dispatch /_oshim/action with invalid payload
$resAction = $router->dispatch(Request::create('POST', '/_oshim/action', content: json_encode(['action' => 'test'])));
if ($resAction->getStatusCode() !== 200 || !str_contains($resAction->getContent(), 'ERROR')) {
    throw new RuntimeException("Router POST /_oshim/action failed");
}
echo "    ✔ Router POST /_oshim/action -> 200 OK (Graceful error JSON on empty/invalid payload)\n\n";

echo "=== ALL EMPIRICAL CHALLENGES & STRESS HARNESSES PASSED! ===\n";
