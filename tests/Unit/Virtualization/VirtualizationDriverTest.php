<?php
declare(strict_types=1);

namespace Tests\Unit\Virtualization;

use Oshim\Container\Container as ServiceContainer;
use Oshim\Testing\TestCase;
use Oshim\Virtualization\Container;
use Oshim\Virtualization\ContainerConfig;
use Oshim\Virtualization\ContainerState;
use Oshim\Virtualization\ContainerStats;
use Oshim\Virtualization\Driver\MockVirtualizationDriver;
use Oshim\Virtualization\Driver\VirtualizationDriverInterface;
use Oshim\Virtualization\ExecResult;
use Oshim\Virtualization\Exceptions\VirtualizationException;
use Oshim\Virtualization\VirtualizationEnvironment;
use Oshim\Virtualization\VirtualizationServiceProvider;
use RuntimeException;

class VirtualizationDriverTest extends TestCase
{
    private MockVirtualizationDriver $driver;

    public function setUp(): void
    {
        parent::setUp();
        $this->driver = new MockVirtualizationDriver();
    }

    public function testFullContainerLifecycleTransitions(): void
    {
        $config = new ContainerConfig(
            id: 'vps_lifecycle_1',
            name: 'app-server-01',
            image: 'debian-12',
            vcpu: 2.0,
            cpuWeight: 150,
            memoryLimitBytes: 2147483648,
            memoryHighBytes: 1879048192,
            swapLimitBytes: 0,
            diskLimitBytes: 42949672960,
            pidsLimit: 1024,
            ipAddress: '10.42.0.10',
            netmask: '255.255.255.0',
            gateway: '10.42.0.1',
            dnsServers: ['1.1.1.1', '8.8.8.8'],
            sshAuthorizedKeys: ['ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIExampleKey root@master'],
            entrypoint: ['/bin/sh', '-c', 'sleep 3600'],
            env: ['APP_ENV' => 'production'],
            workingDir: '/root'
        );

        $this->assertEquals(150, $config->getCpuWeight());
        $this->assertEquals('/root', $config->getWorkingDir());
        $this->assertEquals(['APP_ENV' => 'production'], $config->getEnv());

        // 1. Create
        $container = $this->driver->create($config);
        $this->assertEquals('vps_lifecycle_1', $container->getId());
        $this->assertEquals('app-server-01', $container->getName());
        $this->assertEquals(ContainerState::CREATED, $container->getState());
        $this->assertFalse($container->isRunning());

        // 2. Start
        $this->assertTrue($this->driver->start('vps_lifecycle_1'));
        $this->assertEquals(ContainerState::RUNNING, $container->getState());
        $this->assertTrue($container->isRunning());
        $this->assertNotNull($container->getPid());

        // 3. Pause
        $this->assertTrue($this->driver->pause('vps_lifecycle_1'));
        $this->assertEquals(ContainerState::PAUSED, $container->getState());
        $this->assertTrue($container->isPaused());

        // 4. Resume
        $this->assertTrue($this->driver->resume('vps_lifecycle_1'));
        $this->assertEquals(ContainerState::RUNNING, $container->getState());

        // 5. Restart
        $this->assertTrue($this->driver->restart('vps_lifecycle_1'));
        $this->assertEquals(ContainerState::RUNNING, $container->getState());

        // 6. Stop
        $this->assertTrue($this->driver->stop('vps_lifecycle_1'));
        $this->assertEquals(ContainerState::STOPPED, $container->getState());
        $this->assertTrue($container->isStopped());

        // 7. Destroy
        $this->assertTrue($this->driver->destroy('vps_lifecycle_1'));
        $this->assertNull($this->driver->getContainer('vps_lifecycle_1'));
    }

    public function testContainerModelAndStateHelpers(): void
    {
        $this->assertTrue(ContainerState::isValid('RUNNING'));
        $this->assertTrue(ContainerState::isValid('stopped'));
        $this->assertFalse(ContainerState::isValid('INVALID_STATE'));
        $this->assertTrue(ContainerState::isActive('RUNNING'));
        $this->assertTrue(ContainerState::isTerminal('DESTROYED'));
        $this->assertTrue(ContainerState::isTerminal('ERROR'));

        $config = new ContainerConfig(id: 'c_model_1', name: 'model-tester');
        $c = Container::create($config);
        $this->assertEquals('c_model_1', $c->getId());
        $this->assertEquals(ContainerState::CREATED, $c->getState());

        $c->setState(ContainerState::RUNNING);
        $this->assertTrue($c->isRunning());
        $this->assertNotNull($c->getStartedAt());

        $c->setNetwork('10.42.0.99', '52:54:00:11:22:33', 'tap_99');
        $this->assertEquals('10.42.0.99', $c->getIpAddress());
        $this->assertEquals('52:54:00:11:22:33', $c->getMacAddress());
        $this->assertEquals('tap_99', $c->getTapDevice());

        $arr = $c->toArray();
        $this->assertEquals('c_model_1', $arr['id']);
        $this->assertEquals('10.42.0.99', $arr['ip_address']);
    }

    public function testExecResultDto(): void
    {
        $res = new ExecResult(0, "output string\n", "", 12.5);
        $this->assertTrue($res->isSuccessful());
        $this->assertEquals(0, $res->getExitCode());
        $this->assertEquals("output string\n", $res->getStdout());
        $this->assertEquals("", $res->getStderr());
        $this->assertEquals(12.5, $res->getDurationMs());

        $arr = $res->toArray();
        $this->assertTrue($arr['success']);
        $this->assertEquals(0, $arr['exit_code']);
    }

    public function testInvalidStateTransitionsThrowExceptions(): void
    {
        $config = new ContainerConfig(id: 'vps_invalid_state', name: 'db-server');
        $this->driver->create($config);

        // Cannot pause stopped container
        $this->assertThrows(function () {
            $this->driver->pause('vps_invalid_state');
        }, VirtualizationException::class);

        // Start container
        $this->driver->start('vps_invalid_state');

        // Cannot start already running container
        $this->assertThrows(function () {
            $this->driver->start('vps_invalid_state');
        }, VirtualizationException::class);
    }

    public function testContainerStatsTelemetryMetrics(): void
    {
        $config = new ContainerConfig(id: 'vps_stats_01', name: 'metric-tester', memoryLimitBytes: 1073741824);
        $this->driver->create($config);

        // Stopped stats
        $stoppedStats = $this->driver->stats('vps_stats_01');
        $this->assertEquals(0.0, $stoppedStats->getCpuUsagePct());
        $this->assertEquals(0, $stoppedStats->getMemoryUsageBytes());

        // Start and check running stats
        $this->driver->start('vps_stats_01');
        $stats = $this->driver->stats('vps_stats_01');

        $this->assertTrue($stats->getCpuUsagePct() > 0.0);
        $this->assertTrue($stats->getMemoryUsageBytes() > 0);
        $this->assertEquals(1073741824, $stats->getMemoryLimitBytes());
        $this->assertTrue($stats->getNetRxBytes() > 0);
        $this->assertTrue($stats->getNetTxBytes() > 0);

        $arr = $stats->toArray();
        $this->assertArrayHasKey('cpu_usage_pct', $arr);
        $this->assertArrayHasKey('memory_usage_pct', $arr);
        $this->assertArrayHasKey('pids_count', $arr);
    }

    public function testMockCommandExecution(): void
    {
        $config = new ContainerConfig(id: 'vps_exec_01', name: 'exec-box');
        $this->driver->create($config);
        $this->driver->start('vps_exec_01');

        // Echo command
        $resEcho = $this->driver->exec('vps_exec_01', ['echo', 'Hello', 'OSHIM']);
        $this->assertTrue($resEcho->isSuccessful());
        $this->assertEquals("Hello OSHIM\n", $resEcho->getStdout());

        // Hostname command
        $resHost = $this->driver->exec('vps_exec_01', ['hostname']);
        $this->assertTrue($resHost->isSuccessful());
        $this->assertEquals("exec-box\n", $resHost->getStdout());

        // Whoami command
        $resWho = $this->driver->exec('vps_exec_01', ['whoami']);
        $this->assertTrue($resWho->isSuccessful());
        $this->assertEquals("root\n", $resWho->getStdout());

        // Uptime command
        $resUptime = $this->driver->exec('vps_exec_01', ['uptime']);
        $this->assertTrue($resUptime->isSuccessful());
        $this->assertStringContainsString('load average', $resUptime->getStdout());

        // Uname command
        $resUname = $this->driver->exec('vps_exec_01', ['uname', '-a']);
        $this->assertTrue($resUname->isSuccessful());
        $this->assertStringContainsString('Linux', $resUname->getStdout());
    }

    public function testSnapshotCreationAndRollbackWorkflow(): void
    {
        $config = new ContainerConfig(id: 'vps_snap_01', name: 'snap-box');
        $this->driver->create($config);
        $this->driver->start('vps_snap_01');

        $snapId = $this->driver->createSnapshot('vps_snap_01', 'checkpoint-1');
        $this->assertNotEmpty($snapId);

        $snapshots = $this->driver->listSnapshots('vps_snap_01');
        $this->assertCount(1, $snapshots);
        $this->assertEquals($snapId, $snapshots[0]['id']);

        $this->assertTrue($this->driver->rollbackSnapshot('vps_snap_01', $snapId));
        $this->assertTrue($this->driver->deleteSnapshot('vps_snap_01', $snapId));
        $this->assertCount(0, $this->driver->listSnapshots('vps_snap_01'));
    }

    public function testFaultInjectionAndExecutionLogs(): void
    {
        $config = new ContainerConfig(id: 'vps_fault_01', name: 'fault-box');
        $this->driver->create($config);

        $this->driver->injectFault('start', 'Simulated out-of-memory kernel fault');

        $this->assertThrows(function () {
            $this->driver->start('vps_fault_01');
        }, RuntimeException::class);

        // Fault should be consumed and next attempt succeed
        $this->assertTrue($this->driver->start('vps_fault_01'));

        $logs = $this->driver->getExecutionLogs();
        $this->assertTrue(count($logs) >= 2);
    }

    public function testLegacyFacadeBridgeMethods(): void
    {
        $spec = [
            'name'               => 'legacy-vm',
            'cpu_limit'          => 2.0,
            'memory_limit_bytes' => 2147483648,
            'disk_limit_bytes'   => 21474836480,
            'ipv4'               => '10.42.0.22',
        ];

        $id = $this->driver->createInstance($spec);
        $this->assertNotEmpty($id);

        $this->assertTrue($this->driver->startInstance($id));
        $inst = $this->driver->getInstance($id);
        $this->assertEquals('RUNNING', $inst['state']);

        $stats = $this->driver->getInstanceStats($id);
        $this->assertEquals('RUNNING', $stats['state']);
        $this->assertTrue($stats['cpu_usage_pct'] > 0);

        $this->assertTrue($this->driver->suspendInstance($id));
        $this->assertEquals('SUSPENDED', $this->driver->getInstance($id)['state']);

        $this->assertTrue($this->driver->resumeInstance($id));
        $this->assertEquals('RUNNING', $this->driver->getInstance($id)['state']);

        $this->assertTrue($this->driver->restartInstance($id));
        $this->assertTrue($this->driver->stopInstance($id));
        $this->assertTrue($this->driver->destroyInstance($id));

        $this->assertEquals([], $this->driver->listInstances());
    }

    public function testVirtualizationEnvironmentResolver(): void
    {
        $driverMock = VirtualizationEnvironment::resolveDriver('mock');
        $this->assertInstanceOf(MockVirtualizationDriver::class, $driverMock);

        $driverAuto = VirtualizationEnvironment::resolveDriver('auto');
        $this->assertInstanceOf(VirtualizationDriverInterface::class, $driverAuto);
    }

    public function testVirtualizationServiceProviderDiRegistration(): void
    {
        $container = new ServiceContainer();
        $provider = new VirtualizationServiceProvider();
        $provider->register($container);
        $provider->boot($container);

        $this->assertTrue($container->has(VirtualizationDriverInterface::class));
        $driver = $container->make(VirtualizationDriverInterface::class);
        $this->assertInstanceOf(VirtualizationDriverInterface::class, $driver);
    }
}
