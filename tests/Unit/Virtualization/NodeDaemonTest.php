<?php
declare(strict_types=1);

namespace Tests\Unit\Virtualization;

use Oshim\Cli\CliApplication;
use Oshim\Cli\Commands\NodeStartCommand;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Testing\TestCase;
use Oshim\Virtualization\Driver\MockVirtualizationDriver;
use Oshim\Virtualization\Exceptions\NodeRpcException;
use Oshim\Virtualization\Node\JsonRpcProtocol;
use Oshim\Virtualization\Node\NodeClient;
use Oshim\Virtualization\Node\NodeDaemon;
use Oshim\Virtualization\Node\NodeSecurityCodec;
use RuntimeException;

class NodeDaemonTest extends TestCase
{
    private string $secretKey = 'super-secret-cluster-key-32bytes';

    public function testJsonRpcProtocolValidationAndErrorCodes(): void
    {
        $req = JsonRpcProtocol::formatRequest('node.ping', ['node_id' => 'n1'], 1);
        $this->assertEquals('2.0', $req['jsonrpc']);
        $this->assertEquals('node.ping', $req['method']);
        $this->assertEquals(1, $req['id']);

        JsonRpcProtocol::validateRequestStructure($req);

        // Missing jsonrpc
        $this->assertThrows(function () {
            JsonRpcProtocol::validateRequestStructure(['method' => 'node.ping']);
        }, RuntimeException::class);

        // Missing method
        $this->assertThrows(function () {
            JsonRpcProtocol::validateRequestStructure(['jsonrpc' => '2.0']);
        }, RuntimeException::class);

        // Format success & error
        $success = JsonRpcProtocol::formatSuccess(1, ['status' => 'OK']);
        $this->assertEquals('2.0', $success['jsonrpc']);
        $this->assertEquals(['status' => 'OK'], $success['result']);

        $error = JsonRpcProtocol::formatError(1, JsonRpcProtocol::METHOD_NOT_FOUND, 'Method not found');
        $this->assertEquals(JsonRpcProtocol::METHOD_NOT_FOUND, $error['error']['code']);
    }

    public function testNodeSecurityCodecSealAndOpenRoundtrip(): void
    {
        $payload = [
            'jsonrpc' => '2.0',
            'method'  => 'container.create',
            'params'  => ['name' => 'secure-vps', 'vcpu' => 2],
            'id'      => 42,
        ];

        $sealed = NodeSecurityCodec::sealPayload($payload, $this->secretKey, 'node-us-1');
        $this->assertNotEmpty($sealed);

        $opened = NodeSecurityCodec::openPayload($sealed, $this->secretKey);
        $this->assertEquals('2.0', $opened['jsonrpc']);
        $this->assertEquals('container.create', $opened['method']);
        $this->assertEquals(42, $opened['id']);
        $this->assertEquals('secure-vps', $opened['params']['name']);
    }

    public function testNodeSecurityCodecTamperingAndReplayRejection(): void
    {
        $payload = ['jsonrpc' => '2.0', 'method' => 'node.ping', 'id' => 1];
        $sealed = NodeSecurityCodec::sealPayload($payload, $this->secretKey, 'node-1');

        $envelope = json_decode($sealed, true);

        // 1. Tampered payload
        $tamperedEnvelope = $envelope;
        $tamperedEnvelope['payload'] = substr($tamperedEnvelope['payload'], 0, -4) . 'XXXX';
        $this->assertThrows(function () use ($tamperedEnvelope) {
            NodeSecurityCodec::openPayload(json_encode($tamperedEnvelope), $this->secretKey);
        }, NodeRpcException::class);

        // 2. Tampered signature
        $tamperedSig = $envelope;
        $tamperedSig['signature'] = str_repeat('0', 64);
        $this->assertThrows(function () use ($tamperedSig) {
            NodeSecurityCodec::openPayload(json_encode($tamperedSig), $this->secretKey);
        }, NodeRpcException::class);

        // 3. Expired timestamp (replay protection)
        $expiredEnvelope = $envelope;
        $expiredEnvelope['timestamp'] = time() - 300; // 5 minutes ago
        $signedData = "{$expiredEnvelope['timestamp']}:{$expiredEnvelope['nonce']}:{$expiredEnvelope['node_id']}:{$expiredEnvelope['payload']}";
        $expiredEnvelope['signature'] = hash_hmac('sha256', $signedData, $this->secretKey);
        $this->assertThrows(function () use ($expiredEnvelope) {
            NodeSecurityCodec::openPayload(json_encode($expiredEnvelope), $this->secretKey, 60);
        }, NodeRpcException::class);
    }

    public function testNodeDaemonDirectRequestDispatching(): void
    {
        $driver = new MockVirtualizationDriver();
        $daemon = new NodeDaemon($driver, 'node-test-01');

        // 1. node.ping
        $pingReq = JsonRpcProtocol::formatRequest('node.ping', [], 1);
        $pingResp = $daemon->dispatchSingleRequest($pingReq);
        $this->assertEquals(1, $pingResp['id']);
        $this->assertEquals('ONLINE', $pingResp['result']['status']);
        $this->assertEquals('node-test-01', $pingResp['result']['node_id']);

        // 2. node.status
        $statusReq = JsonRpcProtocol::formatRequest('node.status', [], 2);
        $statusResp = $daemon->dispatchSingleRequest($statusReq);
        $this->assertArrayHasKey('cpu', $statusResp['result']);
        $this->assertArrayHasKey('memory', $statusResp['result']);
        $this->assertArrayHasKey('containers', $statusResp['result']);

        // 3. container.create
        $createReq = JsonRpcProtocol::formatRequest('container.create', [
            'instance_id' => 'vps_daemon_101',
            'hostname'    => 'web-node',
            'cpu_limit'   => 2.0,
        ], 3);
        $createResp = $daemon->dispatchSingleRequest($createReq);
        $this->assertEquals('vps_daemon_101', $createResp['result']['instance_id']);
        $this->assertEquals('CREATED', $createResp['result']['status']);

        // 4. container.start
        $startReq = JsonRpcProtocol::formatRequest('container.start', ['instance_id' => 'vps_daemon_101'], 4);
        $startResp = $daemon->dispatchSingleRequest($startReq);
        $this->assertEquals('RUNNING', $startResp['result']['status']);

        // 5. container.stats
        $statsReq = JsonRpcProtocol::formatRequest('container.stats', ['instance_id' => 'vps_daemon_101'], 5);
        $statsResp = $daemon->dispatchSingleRequest($statsReq);
        $this->assertTrue($statsResp['result']['cpu_usage_pct'] > 0);

        // 6. container.exec
        $execReq = JsonRpcProtocol::formatRequest('container.exec', [
            'instance_id' => 'vps_daemon_101',
            'command'     => ['echo', 'Daemon RPC Active'],
        ], 6);
        $execResp = $daemon->dispatchSingleRequest($execReq);
        $this->assertEquals(0, $execResp['result']['exit_code']);
        $this->assertEquals("Daemon RPC Active\n", $execResp['result']['stdout']);

        // 7. container.snapshot & rollback
        $snapReq = JsonRpcProtocol::formatRequest('container.snapshot', [
            'instance_id'   => 'vps_daemon_101',
            'snapshot_name' => 'pre-deploy',
        ], 7);
        $snapResp = $daemon->dispatchSingleRequest($snapReq);
        $this->assertNotEmpty($snapResp['result']['snapshot_id']);

        $rollReq = JsonRpcProtocol::formatRequest('container.rollback', [
            'instance_id' => 'vps_daemon_101',
            'snapshot_id' => $snapResp['result']['snapshot_id'],
        ], 8);
        $rollResp = $daemon->dispatchSingleRequest($rollReq);
        $this->assertEquals('ROLLED_BACK', $rollResp['result']['status']);

        // 8. container.list & get
        $listReq = JsonRpcProtocol::formatRequest('container.list', [], 9);
        $listResp = $daemon->dispatchSingleRequest($listReq);
        $this->assertCount(1, $listResp['result']);

        $getReq = JsonRpcProtocol::formatRequest('container.get', ['instance_id' => 'vps_daemon_101'], 10);
        $getResp = $daemon->dispatchSingleRequest($getReq);
        $this->assertEquals('vps_daemon_101', $getResp['result']['id']);

        // 9. container.stop
        $stopReq = JsonRpcProtocol::formatRequest('container.stop', ['instance_id' => 'vps_daemon_101'], 11);
        $stopResp = $daemon->dispatchSingleRequest($stopReq);
        $this->assertEquals('STOPPED', $stopResp['result']['status']);

        // 10. container.destroy
        $destroyReq = JsonRpcProtocol::formatRequest('container.destroy', ['instance_id' => 'vps_daemon_101'], 12);
        $destroyResp = $daemon->dispatchSingleRequest($destroyReq);
        $this->assertEquals('DESTROYED', $destroyResp['result']['status']);

        // 11. Unknown method error code -32601
        $unknownReq = JsonRpcProtocol::formatRequest('nonexistent.action', [], 13);
        $unknownResp = $daemon->dispatchSingleRequest($unknownReq);
        $this->assertEquals(JsonRpcProtocol::METHOD_NOT_FOUND, $unknownResp['error']['code']);
    }

    public function testNodeDaemonBatchRequestExecution(): void
    {
        $driver = new MockVirtualizationDriver();
        $daemon = new NodeDaemon($driver, 'node-batch');

        $batch = [
            JsonRpcProtocol::formatRequest('node.ping', [], 101),
            JsonRpcProtocol::formatRequest('node.status', [], 102),
        ];

        $rawBatchJson = json_encode($batch);
        $responseJson = $daemon->handleRequestPayload($rawBatchJson);
        $responses = json_decode($responseJson, true);

        $this->assertTrue(is_array($responses));
        $this->assertCount(2, $responses);
        $this->assertEquals(101, $responses[0]['id']);
        $this->assertEquals(102, $responses[1]['id']);
        $this->assertEquals('ONLINE', $responses[0]['result']['status']);
    }

    public function testNodeStartCommandRegistrationAndConfiguration(): void
    {
        $cmd = new NodeStartCommand();
        $this->assertEquals('node:start', $cmd->getName());
        $this->assertStringContainsString('JSON-RPC Daemon', $cmd->getDescription());

        $cli = new CliApplication();
        $cli->register($cmd);
        $this->assertNotNull($cli->get('node:start'));
    }
}
