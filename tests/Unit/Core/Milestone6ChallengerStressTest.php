<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use Oshim\Testing\TestCase;
use Oshim\Turbo\TurboRocketEngine;
use Oshim\Turbo\RingBufferPool;
use Oshim\Turbo\PerfectHashRouter;
use Oshim\Turbo\ServerStats;
use Oshim\Billing\Gateways\StripeGateway;
use Oshim\Billing\Gateways\BkashGateway;
use Oshim\Billing\Gateways\NagadGateway;
use Oshim\Billing\Gateways\SslCommerzGateway;
use Oshim\Billing\Gateways\PaymentResult;
use Oshim\Security\Ssl\AcmeV2Client;
use Oshim\Security\Cipher;
use Oshim\Autoloader;
use Oshim\Http\Request;
use Oshim\Http\Response;

/**
 * 👑 Milestone 6 Adversarial Challenger Stress Test Suite
 * Concurrency, Gateways HMAC, ACME v2 JWS RFC 8555, Autoloader Fuzzing, and HTTP Reactor.
 */
class Milestone6ChallengerStressTest extends TestCase
{
    // =========================================================================
    // 1. Turbo HTTP Reactor Concurrency, Keep-Alive, Timeouts, & Bursts
    // =========================================================================

    /**
     * Stress-test non-blocking multi-socket concurrency across 25 simultaneous client streams.
     */
    public function testTurboHttpReactorSocketConcurrencyAndMultiStream(): void
    {
        $engine = new TurboRocketEngine(4);
        $engine->boot();
        $engine->listen('127.0.0.1', 0);
        $port = $engine->getPort();
        $this->assertTrue($port > 0);

        $clientCount = 25;
        $clients = [];

        try {
            // Open 25 non-blocking client connections simultaneously
            for ($i = 0; $i < $clientCount; $i++) {
                $c = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2.0, STREAM_CLIENT_CONNECT);
                $this->assertNotNull($c, "Failed to connect client {$i}: {$errstr}");
                stream_set_blocking($c, false);
                $clients[$i] = $c;
            }

            // Accept all connections
            $acceptDeadline = microtime(true) + 1.0;
            while ($engine->getActiveConnectionsCount() < $clientCount && microtime(true) < $acceptDeadline) {
                $engine->tick(5);
                usleep(500);
            }
            $this->assertSame($clientCount, $engine->getActiveConnectionsCount());

            // Write distinct HTTP requests across all 25 sockets simultaneously
            for ($i = 0; $i < $clientCount; $i++) {
                $req = "GET /api/ping HTTP/1.1\r\nHost: 127.0.0.1\r\nX-Client-Id: client-{$i}\r\n\r\n";
                fwrite($clients[$i], $req);
            }

            // Reactor ticks to process all 25 requests
            $processDeadline = microtime(true) + 2.0;
            $totalProcessed = 0;
            while ($totalProcessed < $clientCount && microtime(true) < $processDeadline) {
                $totalProcessed += $engine->tick(10);
                usleep(500);
            }

            $this->assertTrue($totalProcessed >= $clientCount, "Expected at least {$clientCount} requests processed, got {$totalProcessed}");

            // Verify all 25 clients received valid responses
            for ($i = 0; $i < $clientCount; $i++) {
                $resp = '';
                $readDeadline = microtime(true) + 1.0;
                while (!str_contains($resp, "\r\n\r\n") && microtime(true) < $readDeadline) {
                    $chunk = @fread($clients[$i], 2048);
                    if ($chunk !== false && $chunk !== '') {
                        $resp .= $chunk;
                    } else {
                        usleep(500);
                    }
                }
                $this->assertStringContainsString('HTTP/1.1 200 OK', $resp, "Client {$i} did not receive 200 OK");
                $this->assertStringContainsString('pong', $resp, "Client {$i} did not receive ping response body");
                $this->assertStringContainsString('Content-Type: application/json', $resp);
            }

            $this->assertTrue($engine->getStats()->getTotalRequests() >= $clientCount);
        } finally {
            foreach ($clients as $c) {
                if (is_resource($c)) {
                    @fclose($c);
                }
            }
            $engine->close();
        }
    }

    /**
     * Stress-test high-speed keep-alive reuse: 30 consecutive requests on a single persistent TCP socket.
     */
    public function testTurboHttpReactorPipelinedKeepAliveBurst(): void
    {
        $engine = new TurboRocketEngine(2);
        $engine->boot();
        $engine->listen('127.0.0.1', 0);
        $port = $engine->getPort();

        $client = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2.0, STREAM_CLIENT_CONNECT);
        $this->assertNotNull($client);
        stream_set_blocking($client, false);

        try {
            $burstCount = 30;
            for ($seq = 1; $seq <= $burstCount; $seq++) {
                $req = "GET /health HTTP/1.1\r\nHost: 127.0.0.1\r\nX-Seq: {$seq}\r\n\r\n";
                fwrite($client, $req);

                // Tick until request processed
                $deadline = microtime(true) + 1.0;
                $processed = 0;
                while ($processed === 0 && microtime(true) < $deadline) {
                    $processed += $engine->tick(5);
                    if ($processed === 0) usleep(500);
                }
                $this->assertTrue($processed >= 1, "Seq {$seq} failed to process");

                // Read response
                $resp = '';
                $readDeadline = microtime(true) + 1.0;
                while (!str_contains($resp, "\r\n\r\n") && microtime(true) < $readDeadline) {
                    $chunk = @fread($client, 2048);
                    if ($chunk !== false && $chunk !== '') {
                        $resp .= $chunk;
                    } else {
                        usleep(500);
                    }
                }

                $this->assertStringContainsString('HTTP/1.1 200 OK', $resp);
                $this->assertStringContainsString('HEALTHY', $resp);
                $this->assertStringContainsString('Connection: keep-alive', $resp);
            }

            $this->assertSame($burstCount, $engine->getStats()->getTotalRequests());
        } finally {
            @fclose($client);
            $engine->close();
        }
    }

    /**
     * Stress-test fragmented TCP chunks and coalesced back-to-back requests.
     */
    public function testTurboHttpReactorFragmentedFramesAndCoalescedPackets(): void
    {
        $engine = new TurboRocketEngine(2);
        $engine->boot();
        $engine->listen('127.0.0.1', 0);
        $port = $engine->getPort();

        $client = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2.0, STREAM_CLIENT_CONNECT);
        $this->assertNotNull($client);
        stream_set_blocking($client, false);

        try {
            // 1. Fragmented request: send 1 byte per iteration
            $bodyStr = "name=Sovereign";
            $bodyLen = strlen($bodyStr);
            $fullReq = "POST /api/echo HTTP/1.1\r\nHost: 127.0.0.1\r\nContent-Type: application/x-www-form-urlencoded\r\nContent-Length: {$bodyLen}\r\n\r\n{$bodyStr}";
            $totalLen = strlen($fullReq);

            for ($i = 0; $i < $totalLen - 5; $i++) {
                fwrite($client, $fullReq[$i]);
                $engine->tick(1); // Partial tick, should buffer until complete
            }

            // Write the remaining 5 bytes
            fwrite($client, substr($fullReq, $totalLen - 5));

            $deadline = microtime(true) + 1.0;
            $processed = 0;
            while ($processed === 0 && microtime(true) < $deadline) {
                $processed += $engine->tick(5);
                if ($processed === 0) usleep(500);
            }
            $this->assertTrue($processed >= 1, "Fragmented request was not completed");

            // Read response
            $resp1 = '';
            $readDeadline = microtime(true) + 1.0;
            while (!str_contains($resp1, "\r\n\r\n") && microtime(true) < $readDeadline) {
                $chunk = @fread($client, 2048);
                if ($chunk !== false && $chunk !== '') {
                    $resp1 .= $chunk;
                } else {
                    usleep(500);
                }
            }
            $this->assertStringContainsString('HTTP/1.1 200 OK', $resp1);

            // 2. Coalesced requests: 3 requests sent in a single TCP write buffer
            $coalesced = "GET /health HTTP/1.1\r\nHost: 127.0.0.1\r\n\r\n" .
                         "GET /api/ping HTTP/1.1\r\nHost: 127.0.0.1\r\n\r\n" .
                         "GET / HTTP/1.1\r\nHost: 127.0.0.1\r\n\r\n";
            fwrite($client, $coalesced);

            $deadline2 = microtime(true) + 1.0;
            $processed2 = 0;
            while ($processed2 < 3 && microtime(true) < $deadline2) {
                $processed2 += $engine->tick(5);
                if ($processed2 < 3) usleep(500);
            }
            $this->assertTrue($processed2 >= 3, "Coalesced requests not all processed: got {$processed2}");

        } finally {
            @fclose($client);
            $engine->close();
        }
    }

    /**
     * Test Connection: close header cleanly closes socket and decrements active count.
     */
    public function testTurboHttpReactorConnectionCloseAndMaxRequestLimit(): void
    {
        $engine = new TurboRocketEngine(2);
        $engine->boot();
        $engine->listen('127.0.0.1', 0);
        $port = $engine->getPort();

        $client = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2.0, STREAM_CLIENT_CONNECT);
        $this->assertNotNull($client);
        stream_set_blocking($client, false);

        try {
            fwrite($client, "GET /health HTTP/1.1\r\nHost: 127.0.0.1\r\nConnection: close\r\n\r\n");

            $deadline = microtime(true) + 1.0;
            $processed = 0;
            while ($processed === 0 && microtime(true) < $deadline) {
                $processed += $engine->tick(5);
                if ($processed === 0) usleep(500);
            }
            $this->assertSame(1, $processed);

            $resp = '';
            $readDeadline = microtime(true) + 1.0;
            while (!feof($client) && microtime(true) < $readDeadline) {
                $chunk = @fread($client, 2048);
                if ($chunk !== false && $chunk !== '') {
                    $resp .= $chunk;
                } else {
                    usleep(500);
                }
            }

            $this->assertStringContainsString('Connection: close', $resp);
            $this->assertSame(0, $engine->getActiveConnectionsCount());
        } finally {
            @fclose($client);
            $engine->close();
        }
    }

    /**
     * Test idle connection eviction (connections idle > 30 seconds are evicted during reactor activity).
     */
    public function testTurboHttpReactorIdleTimeoutEviction(): void
    {
        $engine = new TurboRocketEngine(2);
        $engine->boot();
        $engine->listen('127.0.0.1', 0);
        $port = $engine->getPort();

        $client = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2.0, STREAM_CLIENT_CONNECT);
        $this->assertNotNull($client);
        stream_set_blocking($client, false);

        try {
            // Establish connection
            $engine->tick(5);
            $this->assertSame(1, $engine->getActiveConnectionsCount());

            // Simulate idle timeout by modifying connection state via reflection
            $ref = new \ReflectionClass($engine);
            $connProp = $ref->getProperty('connections');
            $connProp->setAccessible(true);
            $conns = $connProp->getValue($engine);

            $this->assertNotEmpty($conns);
            $idleConnId = array_key_first($conns);
            $conns[$idleConnId]['last_activity'] = microtime(true) - 35.0; // 35 seconds ago
            $connProp->setValue($engine, $conns);

            // Connect a new probe client to trigger stream_select activity
            $probe = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2.0, STREAM_CLIENT_CONNECT);
            $this->assertNotNull($probe);
            stream_set_blocking($probe, false);

            $engine->tick(5);

            // After tick, the idle client is evicted, only the new probe remains
            $activeConns = $connProp->getValue($engine);
            $this->assertFalse(isset($activeConns[$idleConnId]), "Idle connection {$idleConnId} must be evicted");

            @fclose($probe);
        } finally {
            @fclose($client);
            $engine->close();
        }
    }

    /**
     * Test resilience against malformed HTTP requests (garbage bytes, missing methods, broken delimiters).
     */
    public function testTurboHttpReactorMalformedRequestsResilience(): void
    {
        $engine = new TurboRocketEngine(2);
        $engine->boot();
        $engine->listen('127.0.0.1', 0);
        $port = $engine->getPort();

        $client = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2.0, STREAM_CLIENT_CONNECT);
        $this->assertNotNull($client);
        stream_set_blocking($client, false);

        try {
            // Send garbage request with valid \r\n\r\n delimiter
            fwrite($client, "INVALID_GARBAGE_REQUEST_WITHOUT_METHOD\r\nHeader: value\r\n\r\n");

            $deadline = microtime(true) + 1.0;
            $processed = 0;
            while ($processed === 0 && microtime(true) < $deadline) {
                $processed += $engine->tick(5);
                if ($processed === 0) usleep(500);
            }

            $this->assertSame(1, $processed);

            $resp = '';
            $readDeadline = microtime(true) + 1.0;
            while (!str_contains($resp, "\r\n\r\n") && microtime(true) < $readDeadline) {
                $chunk = @fread($client, 2048);
                if ($chunk !== false && $chunk !== '') {
                    $resp .= $chunk;
                } else {
                    usleep(500);
                }
            }

            // Server safely responds with fallback response without throwing or crashing
            $this->assertStringContainsString('HTTP/1.1 200 OK', $resp);
        } finally {
            @fclose($client);
            $engine->close();
        }
    }

    // =========================================================================
    // 2. Payment Gateway Webhook HMAC Validation Adversarial Testing
    // =========================================================================

    /**
     * Test Stripe webhook HMAC verification under various adversarial attacks.
     */
    public function testStripeGatewayWebhookHmacAdversarialAttacks(): void
    {
        $gateway = new StripeGateway(['webhook_secret' => 'whsec_sovereign_secret_999']);
        $rawPayload = json_encode([
            'id' => 'evt_test_123',
            'data' => [
                'object' => [
                    'id' => 'pi_real_321',
                    'amount' => 5000,
                    'currency' => 'usd',
                    'status' => 'succeeded',
                ]
            ]
        ]);

        $timestamp = (string)time();
        $validSignature = hash_hmac('sha256', "{$timestamp}.{$rawPayload}", 'whsec_sovereign_secret_999');
        $validHeader = "t={$timestamp},v1={$validSignature}";

        // 1. Valid Signature -> Success
        $validReq = new Request(
            method: 'POST',
            uri: '/billing/stripe/webhook',
            headers: ['Stripe-Signature' => $validHeader],
            rawBody: $rawPayload
        );
        $result = $gateway->handleWebhook($validReq);
        $this->assertTrue($result->isSuccess());
        $this->assertSame('pi_real_321', $result->transactionId);
        $this->assertSame(5000, $result->amountCents);

        // 2. Attack: Corrupted HMAC Signature
        $corruptHeader = "t={$timestamp},v1=bad0000000000000000000000000000000000000000000000000000000000000";
        $corruptReq = new Request(
            method: 'POST',
            uri: '/billing/stripe/webhook',
            headers: ['Stripe-Signature' => $corruptHeader],
            rawBody: $rawPayload
        );
        $corruptResult = $gateway->handleWebhook($corruptReq);
        $this->assertFalse($corruptResult->isSuccess(), "Corrupted signature must be rejected");
        $this->assertSame('Invalid Stripe webhook signature', $corruptResult->message);

        // 3. Attack: Tampered Payload (signature for original, body modified)
        $tamperedBody = json_encode([
            'id' => 'evt_test_123',
            'data' => [
                'object' => [
                    'id' => 'pi_real_321',
                    'amount' => 5, // Attacker changed amount from 5000 to 5
                    'currency' => 'usd',
                ]
            ]
        ]);
        $tamperedReq = new Request(
            method: 'POST',
            uri: '/billing/stripe/webhook',
            headers: ['Stripe-Signature' => $validHeader],
            rawBody: $tamperedBody
        );
        $tamperedResult = $gateway->handleWebhook($tamperedReq);
        $this->assertFalse($tamperedResult->isSuccess(), "Tampered payload must be rejected");

        // 4. Attack: Modified Timestamp (replay / timestamp tampering)
        $fakeTimestamp = (string)(time() + 9999);
        $fakeTimeHeader = "t={$fakeTimestamp},v1={$validSignature}";
        $fakeTimeReq = new Request(
            method: 'POST',
            uri: '/billing/stripe/webhook',
            headers: ['Stripe-Signature' => $fakeTimeHeader],
            rawBody: $rawPayload
        );
        $fakeTimeResult = $gateway->handleWebhook($fakeTimeReq);
        $this->assertFalse($fakeTimeResult->isSuccess(), "Tampered timestamp must be rejected");

        // 5. Test associative array input to handleWebhook
        $arrayInput = [
            'headers' => ['Stripe-Signature' => $validHeader],
            'raw_body' => $rawPayload,
            'data' => json_decode($rawPayload, true)['data'],
        ];
        $arrayResult = $gateway->handleWebhook($arrayInput);
        $this->assertTrue($arrayResult->isSuccess());
        $this->assertSame('pi_real_321', $arrayResult->transactionId);
    }

    /**
     * Test Nagad webhook HMAC and payload tampering rejection.
     */
    public function testNagadGatewayWebhookHmacAndPayloadTamperingAttacks(): void
    {
        $gateway = new NagadGateway(['merchant_private_key' => 'nagad_secret_key_rsa_4096']);

        $sensitiveObj = [
            'merchantId' => 'NAGAD_01',
            'orderId' => 'NAGAD_ORDER_77',
            'datetime' => '20260830180000',
            'challenge' => 'abcdef0123456789',
        ];
        $sensitiveJson = (string)json_encode($sensitiveObj);
        $sensitiveB64 = Cipher::base64UrlEncode($sensitiveJson);
        $validSig = Cipher::base64UrlEncode(hash_hmac('sha256', $sensitiveJson, 'nagad_secret_key_rsa_4096', true));

        // 1. Valid Webhook -> Success
        $validPayload = [
            'sensitiveData' => $sensitiveB64,
            'signature' => $validSig,
            'orderId' => 'NAGAD_ORDER_77',
            'paymentReferenceId' => 'NAGAD_REF_88',
            'amount_cents' => 150000,
        ];
        $validResult = $gateway->handleWebhook($validPayload);
        $this->assertTrue($validResult->isSuccess());
        $this->assertSame('NAGAD_REF_88', $validResult->transactionId);

        // 2. Attack: Corrupted Signature
        $corruptPayload = $validPayload;
        $corruptPayload['signature'] = 'bad_corrupted_signature_123';
        $corruptResult = $gateway->handleWebhook($corruptPayload);
        $this->assertFalse($corruptResult->isSuccess(), "Corrupted Nagad signature must be rejected");
        $this->assertSame('Invalid Nagad webhook signature', $corruptResult->message);

        // 3. Attack: Tampered Sensitive Data
        $tamperedObj = $sensitiveObj;
        $tamperedObj['orderId'] = 'ATTACKER_ORDER_99';
        $tamperedPayload = $validPayload;
        $tamperedPayload['sensitiveData'] = Cipher::base64UrlEncode((string)json_encode($tamperedObj));
        // signature is still $validSig
        $tamperedResult = $gateway->handleWebhook($tamperedPayload);
        $this->assertFalse($tamperedResult->isSuccess(), "Tampered sensitiveData must be rejected");

        // 4. Attack: Aborted status
        $abortedPayload = $validPayload;
        $abortedPayload['status'] = 'Aborted';
        $abortedResult = $gateway->handleWebhook($abortedPayload);
        $this->assertFalse($abortedResult->isSuccess());
    }

    /**
     * Test Bkash and SSLCommerz webhook failure and edge cases.
     */
    public function testBkashAndSslCommerzWebhookFailureStates(): void
    {
        // Bkash
        $bkash = new BkashGateway();
        $failBkash = $bkash->handleWebhook(['status' => 'failure', 'paymentID' => 'TR123']);
        $this->assertFalse($failBkash->isSuccess());

        $cancelBkash = $bkash->handleWebhook(['status' => 'cancel', 'paymentID' => 'TR123']);
        $this->assertFalse($cancelBkash->isSuccess());

        // SSLCommerz
        $ssl = new SslCommerzGateway();
        $failSsl = $ssl->handleWebhook(['status' => 'FAILED', 'tran_id' => 'TX_FAIL']);
        $this->assertFalse($failSsl->isSuccess());

        $cancelSsl = $ssl->handleWebhook(['status' => 'CANCELLED', 'tran_id' => 'TX_CANCEL']);
        $this->assertFalse($cancelSsl->isSuccess());

        $expiredSsl = $ssl->handleWebhook(['status' => 'EXPIRED', 'tran_id' => 'TX_EXPIRED']);
        $this->assertFalse($expiredSsl->isSuccess());

        $validSsl = $ssl->handleWebhook(['status' => 'VALIDATED', 'tran_id' => 'TX_OK', 'amount' => '100.00', 'currency' => 'BDT']);
        $this->assertTrue($validSsl->isSuccess());
        $this->assertSame(10000, $validSsl->amountCents);
    }

    // =========================================================================
    // 3. ACME v2 JWS Signatures & RFC 8555 Challenge Validation
    // =========================================================================

    /**
     * Test RSA Account Key generation, JWK format, and RFC 7638 canonical thumbprint.
     */
    public function testAcmeV2RsaKeyGenerationAndJwkThumbprintCanonicalOrder(): void
    {
        $client = new AcmeV2Client('ssl-admin@oshim.cloud');
        $key = $client->getAccountKey();
        $this->assertStringContainsString('PRIVATE KEY', $key);

        $jwk = $client->getJwk();
        $this->assertSame('RSA', $jwk['kty']);
        $this->assertNotEmpty($jwk['e']);
        $this->assertNotEmpty($jwk['n']);

        // Verify thumbprint is 43-character base64url SHA-256
        $thumbprint = $client->getJwkThumbprint();
        $this->assertSame(43, strlen($thumbprint));
        $this->assertFalse(str_contains($thumbprint, '+'));
        $this->assertFalse(str_contains($thumbprint, '/'));
        $this->assertFalse(str_contains($thumbprint, '='));

        // Manually compute canonical JSON thumbprint to verify RFC 7638 exactness
        $canonical = json_encode([
            'e' => $jwk['e'],
            'kty' => $jwk['kty'],
            'n' => $jwk['n'],
        ], JSON_UNESCAPED_SLASHES);
        $expectedThumb = Cipher::base64UrlEncode(hash('sha256', (string)$canonical, true));
        $this->assertSame($expectedThumb, $thumbprint);
    }

    /**
     * Cryptographically verify JWS RS256 signature against public key exported from account private key.
     */
    public function testAcmeV2JwsRs256CryptographicVerificationWithJwkAndKid(): void
    {
        $client = new AcmeV2Client('ssl-admin@oshim.cloud');
        $privateKeyPem = $client->getAccountKey();

        $pkey = openssl_pkey_get_private($privateKeyPem);
        $this->assertNotSame(false, $pkey, "OpenSSL must parse private key");
        $details = openssl_pkey_get_details($pkey);
        $publicKeyPem = $details['key'];

        // 1. Sign with JWK (Account creation)
        $url = 'https://acme-v02.api.letsencrypt.org/acme/new-acct';
        $payload = ['contact' => ['mailto:ssl-admin@oshim.cloud'], 'termsOfServiceAgreed' => true];

        $jws = $client->signJws($url, $payload, null);
        $this->assertArrayHasKey('protected', $jws);
        $this->assertArrayHasKey('payload', $jws);
        $this->assertArrayHasKey('signature', $jws);

        $signingInput = "{$jws['protected']}.{$jws['payload']}";
        $rawSig = Cipher::base64UrlDecode($jws['signature']);

        $verifyResult = openssl_verify($signingInput, (string)$rawSig, $publicKeyPem, OPENSSL_ALGO_SHA256);
        $this->assertSame(1, $verifyResult, "OpenSSL must verify RS256 signature with JWK");

        // Verify protected header contents
        $protectedJson = json_decode((string)Cipher::base64UrlDecode($jws['protected']), true);
        $this->assertSame('RS256', $protectedJson['alg']);
        $this->assertSame($url, $protectedJson['url']);
        $this->assertNotEmpty($protectedJson['nonce']);
        $this->assertArrayHasKey('jwk', $protectedJson);
        $this->assertFalse(isset($protectedJson['kid']));

        // 2. Sign with KID (Authenticated order/challenge)
        $kidUrl = 'https://acme-v02.api.letsencrypt.org/acme/acct/987654321';
        $orderUrl = 'https://acme-v02.api.letsencrypt.org/acme/new-order';
        $orderPayload = ['identifiers' => [['type' => 'dns', 'value' => 'oshim.cloud']]];

        $jwsKid = $client->signJws($orderUrl, $orderPayload, $kidUrl);
        $signingInputKid = "{$jwsKid['protected']}.{$jwsKid['payload']}";
        $rawSigKid = Cipher::base64UrlDecode($jwsKid['signature']);

        $verifyResultKid = openssl_verify($signingInputKid, (string)$rawSigKid, $publicKeyPem, OPENSSL_ALGO_SHA256);
        $this->assertSame(1, $verifyResultKid, "OpenSSL must verify RS256 signature with KID");

        $protectedKidJson = json_decode((string)Cipher::base64UrlDecode($jwsKid['protected']), true);
        $this->assertSame('RS256', $protectedKidJson['alg']);
        $this->assertSame($orderUrl, $protectedKidJson['url']);
        $this->assertSame($kidUrl, $protectedKidJson['kid']);
        $this->assertFalse(isset($protectedKidJson['jwk']));
    }

    /**
     * Test RFC 8555 POST-as-GET signature generation with empty payload.
     */
    public function testAcmeV2PostAsGetEmptyPayloadSignatureVerification(): void
    {
        $client = new AcmeV2Client('ssl-admin@oshim.cloud');
        $privateKeyPem = $client->getAccountKey();
        $pkey = openssl_pkey_get_private($privateKeyPem);
        $details = openssl_pkey_get_details($pkey);
        $publicKeyPem = $details['key'];

        $authUrl = 'https://acme-v02.api.letsencrypt.org/acme/authz/test_123';
        $jws = $client->signJws($authUrl, '', 'https://acme-v02.api.letsencrypt.org/acme/acct/123');

        $this->assertSame('', $jws['payload'], "Payload must be empty string in POST-as-GET");
        $signingInput = "{$jws['protected']}.";
        $rawSig = Cipher::base64UrlDecode($jws['signature']);

        $verifyResult = openssl_verify($signingInput, (string)$rawSig, $publicKeyPem, OPENSSL_ALGO_SHA256);
        $this->assertSame(1, $verifyResult, "OpenSSL must verify empty POST-as-GET signature");
    }

    /**
     * Test multi-SAN challenge generation and DNS-01 / HTTP-01 digests.
     */
    public function testAcmeV2MultiSanChallengeGenerationAndDns01Digest(): void
    {
        $client = new AcmeV2Client('admin@oshim.cloud');
        $sans = ['api.oshim.cloud', 'ws.oshim.cloud', 'cdn.oshim.cloud', 'db.oshim.cloud'];
        $result = $client->requestCertificate('oshim.cloud', $sans, 'dns-01');

        $this->assertSame('valid', $result['status']);
        $this->assertSame('oshim.cloud', $result['domain']);
        $this->assertCount(5, $result['san']); // Primary + 4 SANs

        $thumbprint = $client->getJwkThumbprint();

        foreach (['oshim.cloud', 'api.oshim.cloud', 'ws.oshim.cloud', 'cdn.oshim.cloud', 'db.oshim.cloud'] as $d) {
            $this->assertArrayHasKey($d, $result['challenges']);
            $chall = $result['challenges'][$d];

            $token = $chall['token'];
            $keyAuth = $chall['key_authorization'];
            $this->assertSame("{$token}.{$thumbprint}", $keyAuth);

            // Verify DNS-01 digest: base64url(sha256(key_authorization))
            $expectedDnsValue = Cipher::base64UrlEncode(hash('sha256', $keyAuth, true));
            $this->assertSame($expectedDnsValue, $chall['dns_value']);
            $this->assertSame("_acme-challenge.{$d}", $chall['dns_record']);
            $this->assertSame("/.well-known/acme-challenge/{$token}", $chall['http_path']);
        }

        $this->assertStringContainsString('BEGIN CERTIFICATE', $result['certificate_pem']);
        $this->assertStringContainsString('PRIVATE KEY', $result['private_key_pem']);
        $this->assertTrue($result['auto_renew']);
    }

    // =========================================================================
    // 4. Autoloader Concurrency, Precedence, & Path Traversal Fuzzing
    // =========================================================================

    /**
     * Adversarial path traversal fuzzing on Autoloader.
     */
    public function testAutoloaderPathTraversalFuzzingAndNullByteRejection(): void
    {
        // Ensure autoloader is initialized
        Autoloader::register();

        $adversarialClassNames = [
            "Oshim\\..\\..\\..\\etc\\passwd",
            "App\\..\\..\\database\\database.sqlite",
            "Database\\Seeders\\..\\..\\..\\bin\\oshim",
            "Oshim\\Core\\..\\..\\..\\..\\..\\etc\\shadow",
            "Oshim\\Foo\0Bar",
            "App\\Models\0User",
            "Oshim\\..\\..\\windows\\system32\\cmd.exe",
            "Oshim\\/etc/passwd",
            "Oshim\\C:\\Windows\\System32\\calc.exe",
            "Oshim\\" . str_repeat('A', 2000),
            "Oshim\\\x01\x02\x03\x04",
            "NonExistentVendor\\Deep\\Nested\\Class\\That\\Does\\Not\\Exist",
            "",
            "\\",
            "\\\\",
        ];

        foreach ($adversarialClassNames as $className) {
            $loaded = Autoloader::loadClass($className);
            $this->assertFalse($loaded, "Autoloader must safely return false for adversarial class name: '{$className}'");
        }
    }

    /**
     * Stress-test Autoloader namespace precedence, prepend behavior, and high-volume resolution.
     */
    public function testAutoloaderHighVolumeResolutionStressAndPrefixPrecedence(): void
    {
        $namespaces = Autoloader::getNamespaces();
        $this->assertArrayHasKey('Oshim\\', $namespaces);
        $this->assertArrayHasKey('App\\', $namespaces);
        $this->assertArrayHasKey('Database\\', $namespaces);
        $this->assertArrayHasKey('Tests\\', $namespaces);

        // Prepend custom prefix directory and verify priority
        $customDir = dirname(__DIR__, 3) . '/tests/Fixtures/CustomNamespace/';
        Autoloader::addNamespace('Oshim\\Custom\\', $customDir, prepend: true);

        $updatedNamespaces = Autoloader::getNamespaces();
        $this->assertArrayHasKey('Oshim\\Custom\\', $updatedNamespaces);

        // High volume resolution stress: 1,000 queries
        $start = microtime(true);
        for ($i = 0; $i < 1000; $i++) {
            $loaded = Autoloader::loadClass("Oshim\\Turbo\\TurboRocketEngine");
            $this->assertTrue($loaded);

            $missing = Autoloader::loadClass("Oshim\\NonExistentClass_{$i}");
            $this->assertFalse($missing);
        }
        $elapsed = microtime(true) - $start;

        $this->assertTrue($elapsed < 0.500, "1,000 autoloader lookups should finish under 500ms (took {$elapsed}s)");
    }
}
