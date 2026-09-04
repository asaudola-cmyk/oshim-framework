<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Swarm\SwarmNode;
use Oshim\Swarm\SwarmProtocol;
use Oshim\Swarm\SwarmLoadBalancer;
use Oshim\Swarm\DistributedStateSync;
use Oshim\Swarm\SwarmCluster;
use Oshim\Cli\CliApplication;
use Oshim\Cli\Commands\SwarmInitCommand;
use Oshim\Cli\Commands\SwarmJoinCommand;
use Oshim\Cli\Commands\SwarmStatusCommand;
use Oshim\Cli\Commands\SwarmLeaveCommand;
use InvalidArgumentException;
use RuntimeException;

class SwarmClusterTest extends TestCase
{
    public function testSwarmNodePropertiesLivenessAndSerialization(): void
    {
        $node = new SwarmNode(
            nodeId: 'node-test-1',
            host: '192.168.1.10',
            port: 9500,
            role: 'worker',
            cpuCores: 8,
            memoryTotalMb: 16384,
            memoryUsedMb: 4096,
            status: 'HEALTHY',
            lastHeartbeat: microtime(true),
            activeConnections: 5,
            tags: ['zone' => 'us-east-1'],
            weight: 150
        );

        $this->assertSame('node-test-1', $node->nodeId);
        $this->assertSame('192.168.1.10', $node->host);
        $this->assertSame(9500, $node->port);
        $this->assertSame('worker', $node->role);
        $this->assertSame(8, $node->cpuCores);
        $this->assertSame(16384, $node->memoryTotalMb);
        $this->assertSame(4096, $node->memoryUsedMb);
        $this->assertSame('HEALTHY', $node->status);
        $this->assertSame(5, $node->activeConnections);
        $this->assertSame(['zone' => 'us-east-1'], $node->tags);
        $this->assertSame(150, $node->weight);
        $this->assertSame('192.168.1.10:9500', $node->getEndpoint());
        $this->assertTrue($node->isAlive(5.0));

        // Heartbeat recording
        $node->recordHeartbeat(5120, 12);
        $this->assertSame(5120, $node->memoryUsedMb);
        $this->assertSame(12, $node->activeConnections);
        $this->assertSame('HEALTHY', $node->status);
        $this->assertTrue($node->isAlive());

        // Mark unreachable
        $node->markUnreachable();
        $this->assertSame('UNREACHABLE', $node->status);
        $this->assertFalse($node->isAlive());

        // Array serialization round-trip
        $node->recordHeartbeat(2048, 3);
        $arr = $node->toArray();
        $this->assertSame('node-test-1', $arr['node_id']);
        $this->assertSame('192.168.1.10', $arr['host']);
        $this->assertSame(9500, $arr['port']);
        $this->assertSame(8, $arr['cpu_cores']);
        $this->assertSame(150, $arr['weight']);
        $this->assertSame(['zone' => 'us-east-1'], $arr['tags']);

        $restored = SwarmNode::fromArray($arr);
        $this->assertSame('node-test-1', $restored->nodeId);
        $this->assertSame(16384, $restored->memoryTotalMb);
        $this->assertSame(150, $restored->weight);
        $this->assertSame(['zone' => 'us-east-1'], $restored->tags);
    }

    public function testSwarmProtocolConstantsEncodingDecodingAndValidation(): void
    {
        $this->assertSame("OSHM_SWRM_V1\n", SwarmProtocol::MAGIC);
        $this->assertSame('HANDSHAKE', SwarmProtocol::TYPE_HANDSHAKE);
        $this->assertSame('JOIN', SwarmProtocol::TYPE_JOIN);
        $this->assertSame('JOIN_ACK', SwarmProtocol::TYPE_JOIN_ACK);
        $this->assertSame('HEARTBEAT', SwarmProtocol::TYPE_HEARTBEAT);
        $this->assertSame('HEARTBEAT_ACK', SwarmProtocol::TYPE_HEARTBEAT_ACK);
        $this->assertSame('STATE_SYNC', SwarmProtocol::TYPE_STATE_SYNC);
        $this->assertSame('TASK_DISPATCH', SwarmProtocol::TYPE_TASK_DISPATCH);
        $this->assertSame('TASK_RESULT', SwarmProtocol::TYPE_TASK_RESULT);
        $this->assertSame('LEAVE', SwarmProtocol::TYPE_LEAVE);
        $this->assertSame('ELECT_LEADER', SwarmProtocol::TYPE_ELECT_LEADER);

        $secret = 'sovereign_swarm_secret_key_999';
        $payloadData = ['node_id' => 'node_alpha', 'load' => 0.75, 'active_conns' => 25];

        $encoded = SwarmProtocol::encode(SwarmProtocol::TYPE_HEARTBEAT, $payloadData, $secret);
        $this->assertStringContainsString('sig', $encoded);
        $this->assertStringContainsString('body', $encoded);

        $decoded = SwarmProtocol::decode($encoded, $secret);
        $this->assertSame(SwarmProtocol::TYPE_HEARTBEAT, $decoded['type']);
        $this->assertSame('node_alpha', $decoded['payload']['node_id']);
        $this->assertSame(0.75, $decoded['payload']['load']);
        $this->assertSame(25, $decoded['payload']['active_conns']);
        $this->assertTrue($decoded['timestamp'] > 0.0);

        // Tampered signature or secret mismatch throws InvalidArgumentException
        $this->assertThrows(function () use ($encoded) {
            SwarmProtocol::decode($encoded, 'invalid_secret_token');
        }, InvalidArgumentException::class);

        // Empty frame throws InvalidArgumentException
        $this->assertThrows(function () use ($secret) {
            SwarmProtocol::decode('', $secret);
        }, InvalidArgumentException::class);

        // Malformed JSON frame structure throws InvalidArgumentException
        $this->assertThrows(function () use ($secret) {
            SwarmProtocol::decode('{"invalid":"frame"}', $secret);
        }, InvalidArgumentException::class);
    }

    public function testDistributedStateSyncOperationsAndDeltaMerge(): void
    {
        $sync = new DistributedStateSync();
        $this->assertEmpty($sync->getAll());
        $this->assertEmpty($sync->getVersions());
        $this->assertFalse($sync->has('app.name'));
        $this->assertNull($sync->get('app.name'));
        $this->assertSame('default', $sync->get('app.name', 'default'));

        // Set keys with automatic versioning
        $v1 = $sync->set('app.name', 'OSHIM Swarm');
        $this->assertSame(1, $v1);
        $this->assertTrue($sync->has('app.name'));
        $this->assertSame('OSHIM Swarm', $sync->get('app.name'));

        $v2 = $sync->set('app.name', 'OSHIM Sovereign Swarm');
        $this->assertSame(2, $v2);
        $this->assertSame('OSHIM Sovereign Swarm', $sync->get('app.name'));

        $v3 = $sync->set('cluster.nodes', 5);
        $this->assertSame(1, $v3);

        $all = $sync->getAll();
        $this->assertCount(2, $all);
        $this->assertSame('OSHIM Sovereign Swarm', $all['app.name']);
        $this->assertSame(5, $all['cluster.nodes']);

        $versions = $sync->getVersions();
        $this->assertSame(2, $versions['app.name']);
        $this->assertSame(1, $versions['cluster.nodes']);

        // Merge incoming delta
        $updatedKeys = $sync->mergeDelta(
            [
                'app.name' => 'Stale Name', // Version 1 (lower than current 2 -> ignore)
                'cluster.nodes' => 10,      // Version 3 (higher than current 1 -> apply)
                'security.mode' => 'tls',   // Version 1 (new key -> apply)
            ],
            [
                'app.name' => 1,
                'cluster.nodes' => 3,
                'security.mode' => 1,
            ]
        );

        $this->assertNotContains('app.name', $updatedKeys);
        $this->assertContains('cluster.nodes', $updatedKeys);
        $this->assertContains('security.mode', $updatedKeys);
        $this->assertSame('OSHIM Sovereign Swarm', $sync->get('app.name'));
        $this->assertSame(10, $sync->get('cluster.nodes'));
        $this->assertSame('tls', $sync->get('security.mode'));

        // Test getDeltaSince
        $delta = $sync->getDeltaSince(['cluster.nodes' => 3]);
        $this->assertArrayHasKey('app.name', $delta['store']);
        $this->assertArrayHasKey('security.mode', $delta['store']);
        $this->assertFalse(array_key_exists('cluster.nodes', $delta['store']));

        // Delete key
        $sync->delete('security.mode');
        $this->assertFalse($sync->has('security.mode'));
        $this->assertNull($sync->get('security.mode'));
    }

    public function testSwarmLoadBalancerRoutingStrategies(): void
    {
        $node1 = new SwarmNode('node_1', '10.0.0.1', 9500, 'worker', 4, 8192, 1000, 'HEALTHY', microtime(true), 10, [], 100);
        $node2 = new SwarmNode('node_2', '10.0.0.2', 9500, 'worker', 8, 16384, 2000, 'HEALTHY', microtime(true), 2, [], 100);
        $node3 = new SwarmNode('node_3', '10.0.0.3', 9500, 'worker', 16, 32768, 8000, 'HEALTHY', microtime(true), 25, [], 100);
        $unhealthyNode = new SwarmNode('node_bad', '10.0.0.4', 9500, 'worker', 32, 65536, 100, 'UNREACHABLE', microtime(true), 0);

        $nodes = [$node1, $node2, $node3, $unhealthyNode];
        $lb = new SwarmLoadBalancer();

        // Least Connections strategy picks node_2 (activeConnections = 2)
        $least = $lb->selectNode($nodes, 'least_conn');
        $this->assertSame('node_2', $least->nodeId);

        // Weighted CPU strategy picks high-capacity node
        $weighted = $lb->selectNode($nodes, 'weighted_cpu');
        $this->assertContains($weighted->nodeId, ['node_1', 'node_2']);

        // Round Robin cycles sequentially across healthy nodes
        $rr1 = $lb->selectNode($nodes, 'round_robin');
        $rr2 = $lb->selectNode($nodes, 'round_robin');
        $rr3 = $lb->selectNode($nodes, 'round_robin');
        $rr4 = $lb->selectNode($nodes, 'round_robin');

        $this->assertSame('node_1', $rr1->nodeId);
        $this->assertSame('node_2', $rr2->nodeId);
        $this->assertSame('node_3', $rr3->nodeId);
        $this->assertSame('node_1', $rr4->nodeId); // wraps around

        // Empty healthy pool throws RuntimeException
        $this->assertThrows(function () use ($lb, $unhealthyNode) {
            $lb->selectNode([$unhealthyNode], 'round_robin');
        }, RuntimeException::class);
    }

    public function testSwarmClusterFullMeshTopologyAndLeaderElection(): void
    {
        $nodeAlpha = new SwarmNode('node_01_alpha', '10.0.0.1', 9500, 'leader', 4, 4096);
        $cluster = new SwarmCluster($nodeAlpha, 'my_cluster_secret');

        $this->assertSame($nodeAlpha, $cluster->getLocalNode());
        $this->assertTrue($cluster->isLeader());

        $nodeBeta = new SwarmNode('node_02_beta', '10.0.0.2', 9501, 'worker', 8, 8192);
        $nodeGamma = new SwarmNode('node_03_gamma', '10.0.0.3', 9502, 'worker', 16, 16384);

        $cluster->registerPeer($nodeBeta);
        $cluster->registerPeer($nodeGamma);

        $this->assertSame($nodeBeta, $cluster->getPeer('node_02_beta'));
        $this->assertSame($nodeGamma, $cluster->getPeer('node_03_gamma'));
        $this->assertNull($cluster->getPeer('node_99_unknown'));
        $this->assertCount(3, $cluster->getAllNodes());

        // Cluster summary
        $summary = $cluster->getClusterSummary();
        $this->assertSame('HEALTHY', $summary['cluster_status']);
        $this->assertSame('node_01_alpha', $summary['local_node_id']);
        $this->assertTrue($summary['is_leader']);
        $this->assertSame(3, $summary['total_nodes']);
        $this->assertSame(3, $summary['healthy_nodes']);
        $this->assertSame(28, $summary['cluster_cpu_cores']);
        $this->assertSame(28672, $summary['cluster_memory_total_mb']);

        // Leader election: picks lowest alphabetical ID (node_01_alpha)
        $elected = $cluster->electLeader();
        $this->assertSame('node_01_alpha', $elected->nodeId);
        $this->assertSame('leader', $elected->role);
        $this->assertSame('worker', $nodeBeta->role);
        $this->assertSame('worker', $nodeGamma->role);
        $this->assertTrue($cluster->isLeader());

        // Set local node
        $newNode = new SwarmNode('node_00_zero', '10.0.0.99', 9500, 'worker');
        $cluster->setLocalNode($newNode);
        $this->assertSame('node_00_zero', $cluster->getLocalNode()->nodeId);

        // Leader election with new node
        $electedNew = $cluster->electLeader();
        $this->assertSame('node_00_zero', $electedNew->nodeId);
        $this->assertTrue($cluster->isLeader());

        // Remove peer
        $cluster->removePeer('node_03_gamma');
        $this->assertNull($cluster->getPeer('node_03_gamma'));

        // Route request
        $routed = $cluster->routeRequest('least_conn');
        $this->assertInstanceOf(SwarmNode::class, $routed);
    }

    public function testHeartbeatSweepAndNodeDegradation(): void
    {
        $localNode = new SwarmNode('node_leader', '127.0.0.1', 9500, 'leader', 4, 4096);
        $cluster = new SwarmCluster($localNode);

        // Healthy peer
        $healthyPeer = new SwarmNode('node_healthy', '127.0.0.1', 9501, 'worker', 4, 4096, 0, 'HEALTHY', microtime(true));
        // Stale peer with old heartbeat (10 seconds ago)
        $stalePeer = new SwarmNode('node_stale', '127.0.0.1', 9502, 'worker', 4, 4096, 0, 'HEALTHY', microtime(true) - 10.0);

        $cluster->registerPeer($healthyPeer);
        $cluster->registerPeer($stalePeer);

        $degraded = $cluster->checkHeartbeats(5.0);
        $this->assertCount(1, $degraded);
        $this->assertSame('node_stale', $degraded[0]->nodeId);
        $this->assertSame('UNREACHABLE', $stalePeer->status);
        $this->assertSame('HEALTHY', $healthyPeer->status);
    }

    public function testClusterProtocolMessageHandling(): void
    {
        $secret = 'test_cluster_secret_abc';
        $localNode = new SwarmNode('node_master', '127.0.0.1', 9500, 'leader', 4, 4096);
        $cluster = new SwarmCluster($localNode, $secret);

        // 1. Handshake Message
        $handshakeFrame = SwarmProtocol::encode(SwarmProtocol::TYPE_HANDSHAKE, ['version' => '1.0'], $secret);
        $handshakeResp = $cluster->handleMessage($handshakeFrame);
        $decodedResp = SwarmProtocol::decode($handshakeResp, $secret);
        $this->assertSame(SwarmProtocol::TYPE_HANDSHAKE, $decodedResp['type']);
        $this->assertSame('OK', $decodedResp['payload']['status']);
        $this->assertSame('node_master', $decodedResp['payload']['node']['node_id']);

        // 2. Join Message
        $newPeer = new SwarmNode('node_worker_1', '127.0.0.1', 9501, 'worker', 2, 2048);
        $joinFrame = SwarmProtocol::encode(SwarmProtocol::TYPE_JOIN, ['node' => $newPeer->toArray()], $secret);
        $joinResp = $cluster->handleMessage($joinFrame);
        $decodedJoin = SwarmProtocol::decode($joinResp, $secret);
        $this->assertSame(SwarmProtocol::TYPE_JOIN_ACK, $decodedJoin['type']);
        $this->assertSame('ACCEPTED', $decodedJoin['payload']['status']);
        $this->assertNotNull($cluster->getPeer('node_worker_1'));

        // 3. Heartbeat Message
        $hbFrame = SwarmProtocol::encode(
            SwarmProtocol::TYPE_HEARTBEAT,
            ['node_id' => 'node_worker_1', 'memory_used_mb' => 1024, 'active_connections' => 8],
            $secret
        );
        $hbResp = $cluster->handleMessage($hbFrame);
        $decodedHb = SwarmProtocol::decode($hbResp, $secret);
        $this->assertSame(SwarmProtocol::TYPE_HEARTBEAT_ACK, $decodedHb['type']);
        $this->assertSame('OK', $decodedHb['payload']['status']);
        $this->assertSame(1024, $cluster->getPeer('node_worker_1')?->memoryUsedMb);
        $this->assertSame(8, $cluster->getPeer('node_worker_1')?->activeConnections);

        // 4. State Sync Message
        $syncFrame = SwarmProtocol::encode(
            SwarmProtocol::TYPE_STATE_SYNC,
            ['store' => ['k1' => 'v1', 'k2' => 42], 'versions' => ['k1' => 1, 'k2' => 1]],
            $secret
        );
        $syncResp = $cluster->handleMessage($syncFrame);
        $decodedSync = SwarmProtocol::decode($syncResp, $secret);
        $this->assertSame(SwarmProtocol::TYPE_STATE_SYNC, $decodedSync['type']);
        $this->assertSame('OK', $decodedSync['payload']['status']);
        $this->assertSame('v1', $cluster->getStateSync()->get('k1'));
        $this->assertSame(42, $cluster->getStateSync()->get('k2'));

        // 5. Elect Leader Message
        $electFrame = SwarmProtocol::encode(SwarmProtocol::TYPE_ELECT_LEADER, [], $secret);
        $electResp = $cluster->handleMessage($electFrame);
        $decodedElect = SwarmProtocol::decode($electResp, $secret);
        $this->assertSame(SwarmProtocol::TYPE_ELECT_LEADER, $decodedElect['type']);
        $this->assertSame('OK', $decodedElect['payload']['status']);
        $this->assertSame('node_master', $decodedElect['payload']['leader']['node_id']);

        // 6. Task Dispatch Message
        $taskFrame = SwarmProtocol::encode(
            SwarmProtocol::TYPE_TASK_DISPATCH,
            ['task_id' => 'job_101', 'action' => 'compute_pi'],
            $secret
        );
        $taskResp = $cluster->handleMessage($taskFrame);
        $decodedTask = SwarmProtocol::decode($taskResp, $secret);
        $this->assertSame(SwarmProtocol::TYPE_TASK_RESULT, $decodedTask['type']);
        $this->assertSame('job_101', $decodedTask['payload']['task_id']);
        $this->assertSame('COMPLETED', $decodedTask['payload']['status']);

        // 7. Leave Message
        $leaveFrame = SwarmProtocol::encode(
            SwarmProtocol::TYPE_LEAVE,
            ['node_id' => 'node_worker_1'],
            $secret
        );
        $leaveResp = $cluster->handleMessage($leaveFrame);
        $decodedLeave = SwarmProtocol::decode($leaveResp, $secret);
        $this->assertSame(SwarmProtocol::TYPE_LEAVE, $decodedLeave['type']);
        $this->assertSame('OK', $decodedLeave['payload']['status']);
        $this->assertNull($cluster->getPeer('node_worker_1'));
    }

    public function testSwarmCliCommandsExecution(): void
    {
        $cli = new CliApplication();
        $cli->register(new SwarmInitCommand())
            ->register(new SwarmJoinCommand())
            ->register(new SwarmStatusCommand())
            ->register(new SwarmLeaveCommand());

        // Test swarm:init
        ob_start();
        $codeInit = $cli->run(['oshim', 'swarm:init', '--port=9600', '--secret=secret123']);
        $outputInit = ob_get_clean();
        $this->assertSame(0, $codeInit);
        $this->assertStringContainsString('Swarm Cluster Initialized', $outputInit);
        $this->assertStringContainsString('9600', $outputInit);

        // Test swarm:join
        ob_start();
        $codeJoin = $cli->run(['oshim', 'swarm:join', '127.0.0.1:9600', '--port=9601', '--secret=secret123']);
        $outputJoin = ob_get_clean();
        $this->assertSame(0, $codeJoin);
        $this->assertStringContainsString('Joining OSHIM Swarm Cluster', $outputJoin);
        $this->assertStringContainsString('127.0.0.1:9600', $outputJoin);

        // Test swarm:status (table)
        ob_start();
        $codeStatusTable = $cli->run(['oshim', 'swarm:status']);
        $outputStatusTable = ob_get_clean();
        $this->assertSame(0, $codeStatusTable);
        $this->assertStringContainsString('Swarm Cluster Status', $outputStatusTable);
        $this->assertStringContainsString('Active Nodes in Mesh', $outputStatusTable);

        // Test swarm:status (JSON format)
        ob_start();
        $codeStatusJson = $cli->run(['oshim', 'swarm:status', '--format=json']);
        $outputStatusJson = ob_get_clean();
        $this->assertSame(0, $codeStatusJson);
        $this->assertStringContainsString('"cluster_status": "HEALTHY"', $outputStatusJson);
        $this->assertStringContainsString('"total_nodes": 3', $outputStatusJson);

        // Test swarm:leave
        ob_start();
        $codeLeave = $cli->run(['oshim', 'swarm:leave', '--node-id=node_worker_02']);
        $outputLeave = ob_get_clean();
        $this->assertSame(0, $codeLeave);
        $this->assertStringContainsString('Draining connections', $outputLeave);
        $this->assertStringContainsString('successfully left', $outputLeave);
    }

    public function testDrainingNodeStateAndLoadBalancerExclusion(): void
    {
        $healthyNode = new SwarmNode('node_active', '127.0.0.1', 9500, 'worker', 4, 4096, 500, 'HEALTHY', microtime(true), 2);
        $drainingNode = new SwarmNode('node_drain', '127.0.0.1', 9501, 'worker', 4, 4096, 500, 'HEALTHY', microtime(true), 0);

        $drainingNode->markDraining();
        $this->assertSame('DRAINING', $drainingNode->status);

        $lb = new SwarmLoadBalancer();
        $selected = $lb->selectNode([$healthyNode, $drainingNode], 'round_robin');
        $this->assertSame('node_active', $selected->nodeId);

        $selectedLeast = $lb->selectNode([$healthyNode, $drainingNode], 'least_conn');
        $this->assertSame('node_active', $selectedLeast->nodeId);
    }

    public function testMagicHeaderPrefixedProtocolDecoding(): void
    {
        $secret = 'test_magic_secret';
        $encoded = SwarmProtocol::encode(SwarmProtocol::TYPE_HANDSHAKE, ['version' => '1.0'], $secret);
        $withMagic = SwarmProtocol::MAGIC . $encoded;

        $decoded = SwarmProtocol::decode($withMagic, $secret);
        $this->assertSame(SwarmProtocol::TYPE_HANDSHAKE, $decoded['type']);
        $this->assertSame('1.0', $decoded['payload']['version']);
    }

    public function testLeaderReElectionWhenLeaderDegradesOrLeaves(): void
    {
        $node1 = new SwarmNode('node_01_alpha', '127.0.0.1', 9500, 'leader', 4, 4096, 0, 'HEALTHY', microtime(true) - 10.0);
        $node2 = new SwarmNode('node_02_beta', '127.0.0.1', 9501, 'worker', 4, 4096, 0, 'HEALTHY', microtime(true));

        $localNode = new SwarmNode('node_03_gamma', '127.0.0.1', 9502, 'worker', 4, 4096, 0, 'HEALTHY', microtime(true));
        $cluster = new SwarmCluster($localNode);
        $cluster->registerPeer($node1);
        $cluster->registerPeer($node2);

        // node_01_alpha was leader, but is stale
        $node1->role = 'leader';
        $degraded = $cluster->checkHeartbeats(5.0);
        $this->assertCount(1, $degraded);
        $this->assertSame('node_01_alpha', $degraded[0]->nodeId);

        // Leader re-elected to next lowest healthy node (node_02_beta)
        $this->assertSame('leader', $cluster->getPeer('node_02_beta')->role);
        $this->assertSame('leader', $node2->role);

        // When node_02_beta leaves, leader re-elected to localNode (node_03_gamma)
        $leaveFrame = SwarmProtocol::encode(SwarmProtocol::TYPE_LEAVE, ['node_id' => 'node_02_beta'], 'oshim_sovereign_swarm_secret');
        $cluster->handleMessage($leaveFrame);
        $this->assertTrue($cluster->isLeader());
        $this->assertSame('leader', $localNode->role);
    }
}

