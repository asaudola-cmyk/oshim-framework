<?php
declare(strict_types=1);

$basePath = dirname(__DIR__);
require_once $basePath . '/engine/Bootstrap.php';
\Oshim\Bootstrap::boot($basePath);

use Oshim\Turbo\TurboRocketEngine;
use Oshim\Turbo\ServerStats;
use Oshim\Turbo\PerfectHashRouter;
use Oshim\Turbo\RingBufferPool;
use Oshim\Turbo\SqpollIoUring;
use Oshim\Turbo\WorkerCluster;
use Oshim\Http\Router\Router;
use Oshim\Http\Request;
use Oshim\Http\Response;
use Oshim\Http\Middleware\MiddlewareInterface;
use Oshim\Container\Container;

echo "============================================================\n";
echo "  EMPIRICAL ADVERSARIAL STRESS SUITE: MILESTONE 4\n";
echo "============================================================\n\n";

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $description, ?string $extra = null): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$description}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$description}\n";
        if ($extra) {
            echo "         DETAILS: {$extra}\n";
        }
    }
}

// -----------------------------------------------------------------------------
// SECTION 1: Ephemeral Socket Listener & Rapid Burst Connections (Stress Test)
// -----------------------------------------------------------------------------
echo "\n--- 1. Ephemeral Socket Listener & Rapid Burst Connections ---\n";
try {
    $engine = new TurboRocketEngine(4);
    $engine->boot();
    $addr = $engine->listen('127.0.0.1', 0);
    $port = $engine->getPort();
    assertTest($port > 0 && $port < 65536, "Ephemeral port bound successfully: {$port}");

    $numClients = 30;
    $clients = [];
    for ($i = 0; $i < $numClients; $i++) {
        $c = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2.0, STREAM_CLIENT_CONNECT);
        if ($c) {
            stream_set_blocking($c, false);
            $clients[$i] = $c;
        }
    }
    assertTest(count($clients) === $numClients, "Established {$numClients} concurrent socket connections to ephemeral reactor");

    // Half of the clients send requests immediately, some send later
    for ($i = 0; $i < $numClients; $i++) {
        if ($i % 3 !== 0) {
            fwrite($clients[$i], "GET /health HTTP/1.1\r\nHost: 127.0.0.1\r\nConnection: close\r\n\r\n");
        }
    }

    // Step reactor for 0.8s
    $deadline = microtime(true) + 0.8;
    $processed = 0;
    while (microtime(true) < $deadline) {
        $processed += $engine->tick(10);
        usleep(2000);
    }

    // Now remaining clients send requests
    for ($i = 0; $i < $numClients; $i++) {
        if ($i % 3 === 0) {
            fwrite($clients[$i], "GET /api/ping HTTP/1.1\r\nHost: 127.0.0.1\r\nConnection: close\r\n\r\n");
        }
    }

    $deadline = microtime(true) + 0.8;
    while (microtime(true) < $deadline) {
        $processed += $engine->tick(10);
        usleep(2000);
    }

    assertTest($processed === $numClients, "Reactor processed all {$numClients} requests across concurrent burst ({$processed} processed)");

    // Read responses and verify
    $validResponses = 0;
    foreach ($clients as $i => $c) {
        $resp = '';
        $rDeadline = microtime(true) + 0.3;
        while (!feof($c) && microtime(true) < $rDeadline) {
            $chunk = @fread($c, 4096);
            if ($chunk !== false && $chunk !== '') {
                $resp .= $chunk;
            } else {
                usleep(1000);
            }
        }
        if (str_contains($resp, 'HTTP/1.1 200 OK')) {
            $validResponses++;
        }
        @fclose($c);
    }
    assertTest($validResponses === $numClients, "All {$numClients} clients received HTTP/1.1 200 OK valid response frames");

    $stats = $engine->getStats();
    assertTest($stats->getTotalRequests() === $numClients, "ServerStats accurately records {$numClients} total requests");
    assertTest($stats->getActiveConnections() === 0, "All connections closed cleanly with active_connections = 0");

    $engine->close();
} catch (\Throwable $e) {
    assertTest(false, "Socket listener burst test threw exception", $e->getMessage() . "\n" . $e->getTraceAsString());
}

// -----------------------------------------------------------------------------
// SECTION 2: Pipelined Requests & HTTP/1.1 Keep-Alive Connection Reuse
// -----------------------------------------------------------------------------
echo "\n--- 2. Pipelined Requests & HTTP/1.1 Keep-Alive Connection Reuse ---\n";
try {
    $engine = new TurboRocketEngine(2);
    $engine->listen('127.0.0.1', 0);
    $port = $engine->getPort();

    $client = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2.0, STREAM_CLIENT_CONNECT);
    stream_set_blocking($client, false);

    // Send 5 pipelined requests in a single buffer
    $pipelinedReq = "GET /health HTTP/1.1\r\nHost: 127.0.0.1\r\n\r\n" .
                    "GET /api/ping HTTP/1.1\r\nHost: 127.0.0.1\r\n\r\n" .
                    "GET /health HTTP/1.1\r\nHost: 127.0.0.1\r\n\r\n" .
                    "GET /api/ping HTTP/1.1\r\nHost: 127.0.0.1\r\n\r\n" .
                    "GET /health HTTP/1.1\r\nHost: 127.0.0.1\r\nConnection: keep-alive\r\n\r\n";

    fwrite($client, $pipelinedReq);

    $processed = 0;
    $deadline = microtime(true) + 1.0;
    while ($processed < 5 && microtime(true) < $deadline) {
        $processed += $engine->tick(10);
        usleep(2000);
    }
    assertTest($processed === 5, "Processed 5 pipelined requests from a single TCP write buffer");

    // Read responses
    $buf = '';
    $rDeadline = microtime(true) + 0.5;
    while (microtime(true) < $rDeadline) {
        $chunk = @fread($client, 8192);
        if ($chunk !== false && $chunk !== '') {
            $buf .= $chunk;
        }
        if (substr_count($buf, 'HTTP/1.1 200 OK') >= 5) {
            break;
        }
        usleep(2000);
    }

    $responseCount = substr_count($buf, 'HTTP/1.1 200 OK');
    assertTest($responseCount === 5, "Received exactly 5 HTTP 200 responses in pipeline");
    assertTest(str_contains($buf, 'Connection: keep-alive'), "Pipeline responses retain Connection: keep-alive");

    // Send 6th request on the same connection with Connection: close
    fwrite($client, "GET /api/ping HTTP/1.1\r\nHost: 127.0.0.1\r\nConnection: close\r\n\r\n");

    $deadline = microtime(true) + 0.5;
    while (microtime(true) < $deadline) {
        $engine->tick(10);
        usleep(2000);
    }

    $lastResp = '';
    $rDeadline = microtime(true) + 0.5;
    while (!feof($client) && microtime(true) < $rDeadline) {
        $chunk = @fread($client, 4096);
        if ($chunk !== false && $chunk !== '') {
            $lastResp .= $chunk;
        }
        usleep(2000);
    }
    assertTest(str_contains($lastResp, 'Connection: close'), "Final request with Connection: close returned Connection: close header");
    @fclose($client);

    // Test HTTP/1.0 default close vs keep-alive
    $c10 = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2.0, STREAM_CLIENT_CONNECT);
    stream_set_blocking($c10, false);
    fwrite($c10, "GET /health HTTP/1.0\r\nHost: 127.0.0.1\r\n\r\n");
    $deadline = microtime(true) + 0.5;
    while (microtime(true) < $deadline) {
        $engine->tick(10);
        usleep(2000);
    }
    $r10 = '';
    $rDeadline = microtime(true) + 0.5;
    while (!feof($c10) && microtime(true) < $rDeadline) {
        $chunk = @fread($c10, 4096);
        if ($chunk !== false && $chunk !== '') {
            $r10 .= $chunk;
        }
        usleep(2000);
    }
    assertTest(str_contains($r10, 'Connection: close'), "HTTP/1.0 defaults to Connection: close");
    @fclose($c10);

    $engine->close();
} catch (\Throwable $e) {
    assertTest(false, "Pipelined / Keep-alive test threw exception", $e->getMessage());
}

// -----------------------------------------------------------------------------
// SECTION 3: Malformed HTTP Requests & Protocol Edge Cases
// -----------------------------------------------------------------------------
echo "\n--- 3. Malformed HTTP Requests & Protocol Edge Cases ---\n";
try {
    $engine = new TurboRocketEngine(2);
    $engine->listen('127.0.0.1', 0);
    $port = $engine->getPort();

    // 3.1 Fragmented / Chunked delivery (1 byte at a time)
    $c = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2.0, STREAM_CLIENT_CONNECT);
    stream_set_blocking($c, false);
    $fullReq = "GET /health HTTP/1.1\r\nHost: 127.0.0.1\r\nConnection: close\r\n\r\n";
    for ($i = 0; $i < strlen($fullReq); $i++) {
        fwrite($c, $fullReq[$i]);
        $engine->tick(1);
        usleep(100);
    }
    $deadline = microtime(true) + 0.5;
    while (microtime(true) < $deadline) {
        $engine->tick(5);
        usleep(2000);
    }
    $resp = '';
    $rDeadline = microtime(true) + 0.5;
    while (!feof($c) && microtime(true) < $rDeadline) {
        $chunk = @fread($c, 4096);
        if ($chunk !== false && $chunk !== '') {
            $resp .= $chunk;
        }
        usleep(2000);
    }
    assertTest(str_contains($resp, 'HTTP/1.1 200 OK') && str_contains($resp, 'HEALTHY'), "Fragmented byte-by-byte request parsed cleanly across tick loop");
    @fclose($c);

    // 3.2 Incomplete headers followed by disconnect (3 ticks: accept -> read chunk -> read EOF)
    $cIncomplete = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2.0, STREAM_CLIENT_CONNECT);
    fwrite($cIncomplete, "GET /incomplete HTTP/1.1\r\nHost: 127.0.0.1"); // No trailing \r\n\r\n
    fclose($cIncomplete); // Immediate disconnect
    for ($t = 0; $t < 4; $t++) {
        $engine->tick(10);
    }
    assertTest($engine->getActiveConnectionsCount() === 0, "Abruptly disconnected incomplete client purged without lingering connections");

    // 3.3 Headers with \n\n (Unix style) instead of \r\n\r\n
    $cUnix = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2.0, STREAM_CLIENT_CONNECT);
    stream_set_blocking($cUnix, false);
    fwrite($cUnix, "GET /health HTTP/1.1\nHost: 127.0.0.1\nConnection: close\n\n");
    $deadline = microtime(true) + 0.5;
    while (microtime(true) < $deadline) {
        $engine->tick(10);
        usleep(2000);
    }
    $respUnix = '';
    $rDeadline = microtime(true) + 0.5;
    while (!feof($cUnix) && microtime(true) < $rDeadline) {
        $chunk = @fread($cUnix, 4096);
        if ($chunk !== false && $chunk !== '') {
            $respUnix .= $chunk;
        }
        usleep(2000);
    }
    assertTest(str_contains($respUnix, 'HTTP/1.1 200 OK'), "Unix delimiter \\n\\n accepted and parsed correctly");
    @fclose($cUnix);

    // 3.4 Custom non-standard / weird HTTP methods
    $cVerb = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2.0, STREAM_CLIENT_CONNECT);
    stream_set_blocking($cVerb, false);
    fwrite($cVerb, "PROPFIND /health HTTP/1.1\r\nHost: 127.0.0.1\r\nConnection: close\r\n\r\n");
    $deadline = microtime(true) + 0.5;
    while (microtime(true) < $deadline) {
        $engine->tick(10);
        usleep(2000);
    }
    $respVerb = '';
    $rDeadline = microtime(true) + 0.5;
    while (!feof($cVerb) && microtime(true) < $rDeadline) {
        $chunk = @fread($cVerb, 4096);
        if ($chunk !== false && $chunk !== '') {
            $respVerb .= $chunk;
        }
        usleep(2000);
    }
    assertTest(str_contains($respVerb, 'HTTP/1.1 200 OK'), "Custom HTTP verb (PROPFIND) handled gracefully without server crash");
    @fclose($cVerb);

    $engine->close();
} catch (\Throwable $e) {
    assertTest(false, "Malformed HTTP test threw exception", $e->getMessage());
}

// -----------------------------------------------------------------------------
// SECTION 4: POST / PUT Bodies, Large Payloads & HTTP Status Codes
// -----------------------------------------------------------------------------
echo "\n--- 4. POST / PUT Bodies, Large Payloads & HTTP Status Codes ---\n";
try {
    $router = new Router();
    $router->post('/api/echo-large', function (Request $req) {
        $body = $req->getContent();
        $decoded = json_decode($body, true);
        return Response::json([
            'received_bytes' => strlen($body),
            'items_count' => count($decoded['items'] ?? []),
            'first_item' => $decoded['items'][0] ?? null,
            'last_item' => end($decoded['items']) ?? null,
        ], 201);
    });

    $router->put('/api/status/{code}', function (Request $req, string $code) {
        $statusCode = (int)$code;
        return match ($statusCode) {
            204 => Response::make('', 204),
            301 => Response::redirect('/redirected-url', 301),
            400 => Response::json(['error' => 'Bad Request'], 400),
            422 => Response::json(['errors' => ['field' => 'Invalid value']], 422),
            500 => throw new RuntimeException("Simulated catastrophic kernel fault"),
            default => Response::json(['status' => 'custom', 'code' => $statusCode], $statusCode),
        };
    });

    $router->post('/api/form-submit', function (Request $req) {
        return Response::json([
            'user' => $req->input('user'),
            'role' => $req->input('role'),
            'all' => $req->all(),
        ]);
    });

    $engine = new TurboRocketEngine(2, $router);
    $engine->listen('127.0.0.1', 0);
    $port = $engine->getPort();

    // 4.1 Large JSON payload (64KB+)
    $largeArray = [];
    for ($i = 0; $i < 2000; $i++) {
        $largeArray[] = ['id' => $i, 'sku' => "SKU-{$i}-TEST", 'hash' => md5("item-{$i}")];
    }
    $largeJson = json_encode(['items' => $largeArray]);
    $largeLen = strlen($largeJson);

    $cL = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2.0, STREAM_CLIENT_CONNECT);
    stream_set_blocking($cL, false);
    $reqL = "POST /api/echo-large HTTP/1.1\r\nHost: 127.0.0.1\r\nContent-Type: application/json\r\nContent-Length: {$largeLen}\r\nConnection: close\r\n\r\n" . $largeJson;
    fwrite($cL, $reqL);

    $deadline = microtime(true) + 1.0;
    while (microtime(true) < $deadline) {
        $engine->tick(10);
        usleep(2000);
    }
    $respL = '';
    $rDeadline = microtime(true) + 0.8;
    while (!feof($cL) && microtime(true) < $rDeadline) {
        $chunk = @fread($cL, 16384);
        if ($chunk !== false && $chunk !== '') {
            $respL .= $chunk;
        }
        usleep(2000);
    }
    assertTest(str_contains($respL, 'HTTP/1.1 201 Created'), "Large POST request returned HTTP/1.1 201 Created");
    assertTest(str_contains($respL, "\"received_bytes\":{$largeLen}"), "Large payload body ({$largeLen} bytes) preserved and parsed without corruption");
    assertTest(str_contains($respL, '"items_count":2000'), "All 2000 items deserialized accurately");
    @fclose($cL);

    // 4.2 Status codes: 204 No Content
    $c204 = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2.0, STREAM_CLIENT_CONNECT);
    stream_set_blocking($c204, false);
    fwrite($c204, "PUT /api/status/204 HTTP/1.1\r\nHost: 127.0.0.1\r\nConnection: close\r\n\r\n");
    $deadline = microtime(true) + 0.5;
    while (microtime(true) < $deadline) { $engine->tick(10); usleep(2000); }
    $r204 = '';
    $rDeadline = microtime(true) + 0.5;
    while (!feof($c204) && microtime(true) < $rDeadline) {
        $chunk = @fread($c204, 4096);
        if ($chunk !== false && $chunk !== '') $r204 .= $chunk;
        usleep(2000);
    }
    assertTest(str_contains($r204, 'HTTP/1.1 204 No Content'), "HTTP 204 No Content handled cleanly");
    @fclose($c204);

    // 4.3 Status codes: 301 Redirect
    $c301 = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2.0, STREAM_CLIENT_CONNECT);
    stream_set_blocking($c301, false);
    fwrite($c301, "PUT /api/status/301 HTTP/1.1\r\nHost: 127.0.0.1\r\nConnection: close\r\n\r\n");
    $deadline = microtime(true) + 0.5;
    while (microtime(true) < $deadline) { $engine->tick(10); usleep(2000); }
    $r301 = '';
    $rDeadline = microtime(true) + 0.5;
    while (!feof($c301) && microtime(true) < $rDeadline) {
        $chunk = @fread($c301, 4096);
        if ($chunk !== false && $chunk !== '') $r301 .= $chunk;
        usleep(2000);
    }
    assertTest(str_contains($r301, 'HTTP/1.1 301 Moved Permanently') && stripos($r301, 'Location: /redirected-url') !== false, "HTTP 301 Redirect returned Location header");
    @fclose($c301);

    // 4.4 Status codes: 500 Server Error on exception
    $c500 = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2.0, STREAM_CLIENT_CONNECT);
    stream_set_blocking($c500, false);
    fwrite($c500, "PUT /api/status/500 HTTP/1.1\r\nHost: 127.0.0.1\r\nConnection: close\r\n\r\n");
    $deadline = microtime(true) + 0.5;
    while (microtime(true) < $deadline) { $engine->tick(10); usleep(2000); }
    $r500 = '';
    $rDeadline = microtime(true) + 0.5;
    while (!feof($c500) && microtime(true) < $rDeadline) {
        $chunk = @fread($c500, 4096);
        if ($chunk !== false && $chunk !== '') $r500 .= $chunk;
        usleep(2000);
    }
    assertTest(str_contains($r500, 'HTTP/1.1 500 Internal Server Error'), "Unhandled router exception caught and formatted as HTTP 500");
    assertTest(str_contains($r500, 'Simulated catastrophic kernel fault'), "Exception error message rendered in 500 body");
    @fclose($c500);

    // 4.5 Form URL-encoded POST body
    $cForm = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2.0, STREAM_CLIENT_CONNECT);
    stream_set_blocking($cForm, false);
    $formBody = "user=admin_master&role=sovereign_architect";
    $reqForm = "POST /api/form-submit HTTP/1.1\r\nHost: 127.0.0.1\r\nContent-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($formBody) . "\r\nConnection: close\r\n\r\n" . $formBody;
    fwrite($cForm, $reqForm);
    $deadline = microtime(true) + 0.5;
    while (microtime(true) < $deadline) { $engine->tick(10); usleep(2000); }
    $rForm = '';
    $rDeadline = microtime(true) + 0.5;
    while (!feof($cForm) && microtime(true) < $rDeadline) {
        $chunk = @fread($cForm, 4096);
        if ($chunk !== false && $chunk !== '') $rForm .= $chunk;
        usleep(2000);
    }
    assertTest(str_contains($rForm, '"user":"admin_master"'), "Form URL-encoded POST parameters parsed into Request input");
    assertTest(str_contains($rForm, '"role":"sovereign_architect"'), "Form body parameters mapped accurately");
    @fclose($cForm);

    $engine->close();
} catch (\Throwable $e) {
    assertTest(false, "POST / Payloads / Status codes test threw exception", $e->getMessage());
}

// -----------------------------------------------------------------------------
// SECTION 5: Dynamic Router Parameter Matching, Wildcards, Regex & Onion Middleware
// -----------------------------------------------------------------------------
echo "\n--- 5. Dynamic Router Parameter Matching, Wildcards, Regex & Onion Middleware ---\n";
try {
    $container = new Container();
    $router = new Router($container);

    // 5.1 Deep dynamic params
    $router->get('/orgs/{org}/projects/{project}/envs/{env}/deploy/{dep}', function (Request $req, string $org, string $project, string $env, string $dep) {
        return Response::json(compact('org', 'project', 'env', 'dep'));
    })->whereAlpha('org')->whereNumber('dep');

    // 5.2 Custom regex where constraints
    $router->get('/inventory/{sku}/{country}', function (Request $req, string $sku, string $country) {
        return Response::json(compact('sku', 'country'));
    })->where(['sku' => 'SKU-[0-9]{4}-[A-Z]{2}', 'country' => '[A-Z]{2}']);

    // 5.3 Deep Wildcard parameter
    $router->get('/cdn/*filepath', function (Request $req, string $filepath) {
        return Response::json(['cdn_file' => $filepath]);
    });

    // 5.4 Triple-Nested Route Group with 3 Middleware Layers
    $executionLog = [];

    $mwGlobal = new class($executionLog) implements MiddlewareInterface {
        public function __construct(private array &$log) {}
        public function handle(Request $req, \Closure $next): Response {
            $this->log[] = 'L1_GLOBAL_IN';
            $res = $next($req);
            $this->log[] = 'L1_GLOBAL_OUT';
            return $res;
        }
    };

    $mwAuth = new class($executionLog) implements MiddlewareInterface {
        public function __construct(private array &$log) {}
        public function handle(Request $req, \Closure $next): Response {
            if ($req->header('x-auth-token') === 'BLOCKED') {
                return Response::json(['error' => 'Unauthorized'], 401);
            }
            $this->log[] = 'L2_AUTH_IN';
            $res = $next($req);
            $this->log[] = 'L2_AUTH_OUT';
            return $res;
        }
    };

    $mwAudit = new class($executionLog) implements MiddlewareInterface {
        public function __construct(private array &$log) {}
        public function handle(Request $req, \Closure $next): Response {
            $this->log[] = 'L3_AUDIT_IN';
            $res = $next($req);
            $this->log[] = 'L3_AUDIT_OUT';
            return $res;
        }
    };

    $router->group(['prefix' => '/api', 'middleware' => [$mwGlobal]], function (Router $r1) use ($mwAuth, $mwAudit, &$executionLog) {
        $r1->group(['prefix' => '/v2', 'middleware' => [$mwAuth]], function (Router $r2) use ($mwAudit, &$executionLog) {
            $r2->group(['prefix' => '/cluster', 'middleware' => [$mwAudit]], function (Router $r3) use (&$executionLog) {
                $r3->get('/nodes/{node_id}', function (Request $req, string $node_id) use (&$executionLog) {
                    $executionLog[] = "ACTION_NODE_{$node_id}";
                    return Response::json(['node' => $node_id, 'active' => true]);
                })->whereNumber('node_id');
            });
        });
    });

    // Test 5.1 valid
    $req1 = Request::create('GET', '/orgs/oshim/projects/cloud/envs/prod/deploy/999');
    $res1 = $router->dispatch($req1);
    assertTest($res1->getStatusCode() === 200, "Deep 4-segment parameter matching returned HTTP 200");
    assertTest(str_contains($res1->getContent(), '"dep":"999"'), "Route parameters extracted correctly");

    // Test 5.1 invalid constraint (dep is not number)
    $req1Bad = Request::create('GET', '/orgs/oshim/projects/cloud/envs/prod/deploy/not_a_num');
    $res1Bad = $router->dispatch($req1Bad);
    assertTest($res1Bad->getStatusCode() === 404, "Invalid constraint returns HTTP 404");

    // Test 5.2 custom regex
    $req2 = Request::create('GET', '/inventory/SKU-4821-US/US');
    $res2 = $router->dispatch($req2);
    assertTest($res2->getStatusCode() === 200, "Custom regex constraint SKU-[0-9]{4}-[A-Z]{2} matched");

    $req2Bad = Request::create('GET', '/inventory/INVALID_SKU/US');
    $res2Bad = $router->dispatch($req2Bad);
    assertTest($res2Bad->getStatusCode() === 404, "Invalid custom regex SKU returned 404");

    // Test 5.3 deep wildcard
    $req3 = Request::create('GET', '/cdn/assets/themes/dark/images/logo.svg');
    $res3 = $router->dispatch($req3);
    assertTest(str_contains($res3->getContent(), '"cdn_file":"assets/themes/dark/images/logo.svg"'), "Deep wildcard *filepath matched multiple slashes");

    // Test 5.4 Onion middleware order
    $executionLog = [];
    $req4 = Request::create('GET', '/api/v2/cluster/nodes/777');
    $res4 = $router->dispatch($req4);
    assertTest($res4->getStatusCode() === 200, "Nested route group dispatched successfully");
    $expectedOrder = [
        'L1_GLOBAL_IN',
        'L2_AUTH_IN',
        'L3_AUDIT_IN',
        'ACTION_NODE_777',
        'L3_AUDIT_OUT',
        'L2_AUTH_OUT',
        'L1_GLOBAL_OUT',
    ];
    assertTest($executionLog === $expectedOrder, "Middleware executed in strict onion order: " . implode(' -> ', $executionLog));

    // Test 5.5 Middleware early termination / abort
    $executionLog = [];
    $req5 = Request::create('GET', '/api/v2/cluster/nodes/777', server: ['HTTP_X_AUTH_TOKEN' => 'BLOCKED']);
    $res5 = $router->dispatch($req5);
    assertTest($res5->getStatusCode() === 401, "Middleware early termination returned 401");
    assertTest(!in_array('L3_AUDIT_IN', $executionLog), "Inner middleware L3 bypassed on early termination");
    assertTest(!in_array('ACTION_NODE_777', $executionLog), "Controller action bypassed on early termination");
    assertTest(in_array('L1_GLOBAL_OUT', $executionLog), "Outer middleware out-phase completed on early termination");

} catch (\Throwable $e) {
    assertTest(false, "Dynamic router / middleware test threw exception", $e->getMessage() . "\n" . $e->getTraceAsString());
}

// -----------------------------------------------------------------------------
// SECTION 6: High-Concurrency Stress Test on TurboRocketEngine
// -----------------------------------------------------------------------------
echo "\n--- 6. High-Concurrency Stress Test on TurboRocketEngine ---\n";
try {
    $engine = new TurboRocketEngine(4);
    $engine->boot();
    $engine->listen('127.0.0.1', 0);
    $port = $engine->getPort();

    $totalRequests = 100;
    $clients = [];
    $connectErrors = 0;

    // Send 100 requests in rapid bursts of 20
    $successfulResponses = 0;
    for ($batch = 0; $batch < 5; $batch++) {
        $batchClients = [];
        for ($i = 0; $i < 20; $i++) {
            $c = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 1.0, STREAM_CLIENT_CONNECT);
            if ($c) {
                stream_set_blocking($c, false);
                fwrite($c, "GET /health HTTP/1.1\r\nHost: 127.0.0.1\r\nConnection: close\r\n\r\n");
                $batchClients[] = $c;
            } else {
                $connectErrors++;
            }
        }

        $deadline = microtime(true) + 0.6;
        while (microtime(true) < $deadline) {
            $engine->tick(10);
            usleep(1000);
        }

        foreach ($batchClients as $c) {
            $resp = '';
            $rDeadline = microtime(true) + 0.3;
            while (!feof($c) && microtime(true) < $rDeadline) {
                $chunk = @fread($c, 4096);
                if ($chunk !== false && $chunk !== '') {
                    $resp .= $chunk;
                }
                usleep(1000);
            }
            if (str_contains($resp, 'HTTP/1.1 200 OK')) {
                $successfulResponses++;
            }
            @fclose($c);
        }
    }

    assertTest($connectErrors === 0, "Zero socket connection errors during 100-request burst");
    assertTest($successfulResponses === $totalRequests, "All {$totalRequests}/{$totalRequests} requests completed with HTTP 200 OK");
    assertTest($engine->getStats()->getTotalRequests() === $totalRequests, "ServerStats matches exactly {$totalRequests} processed requests");

    $engine->close();
} catch (\Throwable $e) {
    assertTest(false, "High-concurrency stress test threw exception", $e->getMessage());
}

// -----------------------------------------------------------------------------
// SECTION 7: HTTP 405 Method Not Allowed & Route URL Generation
// -----------------------------------------------------------------------------
echo "\n--- 7. HTTP 405 Method Not Allowed & Route URL Generation ---\n";
try {
    $router = new Router();
    $router->post('/api/resource', fn() => Response::json(['created' => true]));
    $router->put('/api/resource', fn() => Response::json(['updated' => true]));
    $router->delete('/api/resource', fn() => Response::json(['deleted' => true]));

    $router->nameRoute('product.view', $router->get('/catalog/{category}/{id}', fn() => 'product'));
    $router->nameRoute('docs.topic', $router->get('/docs/{topic}/{subtopic?}', fn() => 'docs'));

    // 7.1 Dispatch with GET on POST/PUT/DELETE route triggers 405
    $req405 = Request::create('GET', '/api/resource', server: ['HTTP_ACCEPT' => 'application/json']);
    $res405 = $router->dispatch($req405);
    assertTest($res405->getStatusCode() === 405, "Disallowed method triggers HTTP 405 Method Not Allowed");
    $allowHeader = (string)$res405->getHeaders()->get('allow');
    assertTest(str_contains($allowHeader, 'POST') && str_contains($allowHeader, 'PUT') && str_contains($allowHeader, 'DELETE'), "405 response includes Allow header with allowed methods: {$allowHeader}");

    // 7.2 Named Route URL generation
    $url1 = $router->route('product.view', ['category' => 'laptops', 'id' => 99, 'sort' => 'desc']);
    assertTest($url1 === '/catalog/laptops/99?sort=desc', "Named route generated with required parameters and query params: {$url1}");

    $url2 = $router->route('docs.topic', ['topic' => 'networking']);
    assertTest($url2 === '/docs/networking', "Named route generated with omitted optional parameter: {$url2}");

    $url3 = $router->route('docs.topic', ['topic' => 'networking', 'subtopic' => 'firewall']);
    assertTest($url3 === '/docs/networking/firewall', "Named route generated with provided optional parameter: {$url3}");
} catch (\Throwable $e) {
    assertTest(false, "Section 7 test threw exception", $e->getMessage());
}

// -----------------------------------------------------------------------------
// SECTION 8: Multi-Client Pipelined High-Throughput Burst
// -----------------------------------------------------------------------------
echo "\n--- 8. Multi-Client Pipelined High-Throughput Burst ---\n";
try {
    $engine = new TurboRocketEngine(4);
    $engine->boot();
    $engine->listen('127.0.0.1', 0);
    $port = $engine->getPort();

    $numClients = 10;
    $reqsPerClient = 10;
    $totalExpected = $numClients * $reqsPerClient;

    $clients = [];
    for ($i = 0; $i < $numClients; $i++) {
        $c = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2.0, STREAM_CLIENT_CONNECT);
        stream_set_blocking($c, false);

        // Build 10 pipelined requests buffer
        $pipeBuf = '';
        for ($r = 0; $r < $reqsPerClient; $r++) {
            $isLast = ($r === $reqsPerClient - 1);
            $connHdr = $isLast ? 'close' : 'keep-alive';
            $pipeBuf .= "GET /health HTTP/1.1\r\nHost: 127.0.0.1\r\nConnection: {$connHdr}\r\n\r\n";
        }
        fwrite($c, $pipeBuf);
        $clients[] = $c;
    }

    $processed = 0;
    $deadline = microtime(true) + 2.0;
    while ($processed < $totalExpected && microtime(true) < $deadline) {
        $processed += $engine->tick(10);
        usleep(1000);
    }
    assertTest($processed === $totalExpected, "Processed all {$totalExpected} pipelined requests across {$numClients} concurrent TCP streams ({$processed} processed)");

    $receivedTotal = 0;
    foreach ($clients as $c) {
        $buf = '';
        $rDeadline = microtime(true) + 0.5;
        while (!feof($c) && microtime(true) < $rDeadline) {
            $chunk = @fread($c, 8192);
            if ($chunk !== false && $chunk !== '') {
                $buf .= $chunk;
            }
            usleep(1000);
        }
        $receivedTotal += substr_count($buf, 'HTTP/1.1 200 OK');
        @fclose($c);
    }
    assertTest($receivedTotal === $totalExpected, "All {$totalExpected} pipelined responses received by clients without packet loss");

    $engine->close();
} catch (\Throwable $e) {
    assertTest(false, "Section 8 test threw exception", $e->getMessage());
}

echo "\n============================================================\n";
echo "  FINAL SUMMARY: {$passed} PASSED, {$failed} FAILED\n";
echo "============================================================\n";

exit($failed === 0 ? 0 : 1);
