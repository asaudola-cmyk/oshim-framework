<?php
declare(strict_types=1);

namespace Tests\Unit\Virtualization;

use InvalidArgumentException;
use Oshim\Testing\TestCase;
use Oshim\Virtualization\Cgroup\CgroupConfig;
use Oshim\Virtualization\Cgroup\CgroupTelemetry;
use Oshim\Virtualization\Cgroup\CgroupV2Manager;
use Oshim\Virtualization\ContainerConfig;
use Oshim\Virtualization\ContainerState;
use Oshim\Virtualization\Driver\MockVirtualizationDriver;
use Oshim\Virtualization\Exceptions\CgroupException;
use Oshim\Virtualization\Exceptions\NetworkException;
use Oshim\Virtualization\Exceptions\NodeRpcException;
use Oshim\Virtualization\Exceptions\SnapshotException;
use Oshim\Virtualization\Exceptions\StorageException;
use Oshim\Virtualization\Exceptions\SyscallException;
use Oshim\Virtualization\Exceptions\VirtualizationException;
use Oshim\Virtualization\Network\IpamService;
use Oshim\Virtualization\Network\SimulatedNatRouter;
use Oshim\Virtualization\Node\JsonRpcProtocol;
use Oshim\Virtualization\Node\NodeDaemon;
use Oshim\Virtualization\Node\NodeSecurityCodec;
use Oshim\Virtualization\Storage\OverlayFsManager;
use Oshim\Virtualization\Storage\SnapshotManager;
use Oshim\Virtualization\Storage\SnapshotMetadata;
use Oshim\Virtualization\Storage\StorageQuotaManager;
use Oshim\Virtualization\Syscall\LinuxConstants;
use Oshim\Virtualization\Syscall\LinuxSyscall;
use Oshim\Virtualization\Syscall\MockSyscall;
use RuntimeException;

/**
 * Adversarial empirical stress-test suite for Milestone 3 (Virtualization & Node Engine).
 */
class VirtualizationStressTest extends TestCase
{
    private string $tempRoot;
    private MockSyscall $mockSyscall;
    private CgroupV2Manager $cgroupManager;
    private OverlayFsManager $overlayManager;
    private SnapshotManager $snapshotManager;
    private StorageQuotaManager $quotaManager;
    private MockVirtualizationDriver $mockDriver;

    public function setUp(): void
    {
        parent::setUp();
        $this->tempRoot = sys_get_temp_dir() . '/oshim_stress_' . bin2hex(random_bytes(6));
        @mkdir("{$this->tempRoot}/cgroup", 0777, true);
        @mkdir("{$this->tempRoot}/storage", 0777, true);
        @mkdir("{$this->tempRoot}/driver", 0777, true);

        $this->mockSyscall = new MockSyscall();
        $this->cgroupManager = new CgroupV2Manager("{$this->tempRoot}/cgroup", 'oshim');
        $this->overlayManager = new OverlayFsManager("{$this->tempRoot}/storage", $this->mockSyscall);
        $this->snapshotManager = new SnapshotManager("{$this->tempRoot}/storage", $this->overlayManager);
        $this->quotaManager = new StorageQuotaManager($this->overlayManager);
        $this->mockDriver = new MockVirtualizationDriver("{$this->tempRoot}/driver");
    }

    public function tearDown(): void
    {
        $this->recursiveDelete($this->tempRoot);
        parent::tearDown();
    }

    // =========================================================================
    // SECTION 1: Syscall & FFI Adversarial Stress Tests
    // =========================================================================

    public function testSyscallErrnoAndDiagnosticResolutionAllKnownAndUnknown(): void
    {
        $testMatrix = [
            1  => 'Operation not permitted (EPERM)',
            2  => 'No such file or directory (ENOENT)',
            3  => 'No such process (ESRCH)',
            12 => 'Cannot allocate memory (ENOMEM)',
            13 => 'Permission denied (EACCES)',
            16 => 'Device or resource busy (EBUSY)',
            22 => 'Invalid argument (EINVAL)',
            24 => 'Too many open files (EMFILE)',
            28 => 'No space left on device (ENOSPC)',
            38 => 'Function not implemented (ENOSYS)',
        ];

        foreach ($testMatrix as $errno => $expectedSubstring) {
            $msg = LinuxSyscall::resolveDiagnosticMessage($errno, 'test_call');
            $this->assertStringContainsString($expectedSubstring, $msg);
        }

        // Test unmapped errnos
        $unmapped1 = LinuxSyscall::resolveDiagnosticMessage(0, 'custom_syscall');
        $this->assertStringContainsString('custom_syscall', $unmapped1);

        $unmapped2 = LinuxSyscall::resolveDiagnosticMessage(99, 'unshare');
        $this->assertStringContainsString('unshare(2) and errno 99', $unmapped2);

        $unmapped3 = LinuxSyscall::resolveDiagnosticMessage(131, 'pivot_root');
        $this->assertStringContainsString('errno 131', $unmapped3);
    }

    public function testSyscallCheckResultBoundaries(): void
    {
        $syscall = new LinuxSyscall();

        // 0 and positive numbers should NEVER throw
        $syscall->checkResult(0, 'mount', ['stage' => 'bind']);
        $syscall->checkResult(1, 'open', ['fd' => 1]);
        $syscall->checkResult(100, 'ioctl', ['code' => 0x123]);

        // Negative values must throw SyscallException with diagnostic details
        $negativeResults = [-1, -2, -22, -100];
        foreach ($negativeResults as $neg) {
            try {
                $syscall->checkResult($neg, 'unshare', ['flags' => 0x20000]);
                $this->fail("Expected SyscallException for result {$neg}");
            } catch (SyscallException $e) {
                $this->assertEquals('unshare', $e->getSyscall());
                $this->assertEquals(0x20000, $e->getContext()['flags']);
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    public function testSyscallArchitecturePivotRootSyscallNumbers(): void
    {
        $pivotRootSyscall = LinuxConstants::getSyscallPivotRoot();
        $this->assertTrue(
            $pivotRootSyscall === LinuxConstants::SYS_X86_64_PIVOT_ROOT ||
            $pivotRootSyscall === LinuxConstants::SYS_AARCH64_PIVOT_ROOT
        );
        $this->assertEquals(155, LinuxConstants::SYS_X86_64_PIVOT_ROOT);
        $this->assertEquals(217, LinuxConstants::SYS_AARCH64_PIVOT_ROOT);
    }

    public function testSyscallNamespaceFlagCompositionEdgeCases(): void
    {
        // 1. Empty array -> 0
        $this->assertEquals(0, LinuxConstants::buildNamespaceFlags([]));

        // 2. Mixed case, leading/trailing whitespace, and unknown tokens
        $tokens = [
            '  mOuNt  ',
            'PID',
            '  nEt  ',
            'uTs',
            '  IPC  ',
            'cgroup',
            'USER',
            'INVALID_TOKEN',
            '',
            '    ',
            'bogus_namespace',
        ];

        $flags = LinuxConstants::buildNamespaceFlags($tokens);
        $expected = LinuxConstants::CLONE_NEWNS
            | LinuxConstants::CLONE_NEWPID
            | LinuxConstants::CLONE_NEWNET
            | LinuxConstants::CLONE_NEWUTS
            | LinuxConstants::CLONE_NEWIPC
            | LinuxConstants::CLONE_NEWCGROUP
            | LinuxConstants::CLONE_NEWUSER;

        $this->assertEquals($expected, $flags);
        $this->assertEquals(0x7E020000, $flags);
    }

    public function testMockSyscallForcedFailureAndRelativePivotRoot(): void
    {
        $this->mockSyscall->reset();

        // Test normal relative path rejection in pivotRoot
        $resRelative = $this->mockSyscall->pivotRoot('relative/path', '/put_old');
        $this->assertEquals(-1, $resRelative);
        $this->assertEquals(22, $this->mockSyscall->getLastError());

        $resRelative2 = $this->mockSyscall->pivotRoot('/new_root', 'relative_old');
        $this->assertEquals(-1, $resRelative2);
        $this->assertEquals(22, $this->mockSyscall->getLastError());

        // Test forced error on specific syscall
        $this->mockSyscall->forceResult('mount', -1, 16 /* EBUSY */);
        $resMount = $this->mockSyscall->mount('none', '/mnt', 'tmpfs', 0, null);
        $this->assertEquals(-1, $resMount);
        $this->assertEquals(16, $this->mockSyscall->getLastError());
        $this->assertEquals('Device or resource busy', $this->mockSyscall->getErrorString(16));
    }

    // =========================================================================
    // SECTION 2: Cgroups v2 Manager Edge Cases & Extreme Boundaries
    // =========================================================================

    public function testCgroupSubMillisecondCpuQuotaClamping(): void
    {
        // Sub-millisecond CPU (0.0001 cores = 10 usec quota) must clamp to minimum 1000 usec (1ms)
        $configSubMs = new CgroupConfig(cpuCores: 0.0001);
        $containerId = 'cgroup_subms_01';
        $this->cgroupManager->createContainerSlice($containerId, $configSubMs);

        $sliceDir = $this->cgroupManager->getContainerSlicePath($containerId);
        $cpuMax = trim((string)file_get_contents("{$sliceDir}/cpu.max"));
        $this->assertEquals('1000 100000', $cpuMax);

        // 0.001 cores = 100 usec quota -> clamps to 1000 usec
        $configSubMs2 = new CgroupConfig(cpuCores: 0.001);
        $containerId2 = 'cgroup_subms_02';
        $this->cgroupManager->createContainerSlice($containerId2, $configSubMs2);

        $sliceDir2 = $this->cgroupManager->getContainerSlicePath($containerId2);
        $cpuMax2 = trim((string)file_get_contents("{$sliceDir2}/cpu.max"));
        $this->assertEquals('1000 100000', $cpuMax2);

        // 0.05 cores = 5000 usec quota -> exactly 5000 100000
        $config5pct = new CgroupConfig(cpuCores: 0.05);
        $containerId3 = 'cgroup_subms_03';
        $this->cgroupManager->createContainerSlice($containerId3, $config5pct);

        $sliceDir3 = $this->cgroupManager->getContainerSlicePath($containerId3);
        $cpuMax3 = trim((string)file_get_contents("{$sliceDir3}/cpu.max"));
        $this->assertEquals('5000 100000', $cpuMax3);
    }

    public function testCgroupMultiCoreExtremeQuota(): void
    {
        // Multi-core: 64 cores = 6,400,000 usec
        $config64 = new CgroupConfig(cpuCores: 64.0);
        $containerId = 'cgroup_core64';
        $this->cgroupManager->createContainerSlice($containerId, $config64);

        $sliceDir = $this->cgroupManager->getContainerSlicePath($containerId);
        $cpuMax = trim((string)file_get_contents("{$sliceDir}/cpu.max"));
        $this->assertEquals('6400000 100000', $cpuMax);

        // Multi-core: 128 cores = 12,800,000 usec
        $config128 = new CgroupConfig(cpuCores: 128.0);
        $containerId2 = 'cgroup_core128';
        $this->cgroupManager->createContainerSlice($containerId2, $config128);

        $sliceDir2 = $this->cgroupManager->getContainerSlicePath($containerId2);
        $cpuMax2 = trim((string)file_get_contents("{$sliceDir2}/cpu.max"));
        $this->assertEquals('12800000 100000', $cpuMax2);
    }

    public function testCgroupZeroAndUnlimitedCpuQuota(): void
    {
        $configNull = new CgroupConfig(cpuCores: null);
        $containerId1 = 'cgroup_unlim_01';
        $this->cgroupManager->createContainerSlice($containerId1, $configNull);
        $sliceDir1 = $this->cgroupManager->getContainerSlicePath($containerId1);
        $this->assertEquals('max 100000', trim((string)file_get_contents("{$sliceDir1}/cpu.max")));

        $configZero = new CgroupConfig(cpuCores: 0.0);
        $containerId2 = 'cgroup_unlim_02';
        $this->cgroupManager->createContainerSlice($containerId2, $configZero);
        $sliceDir2 = $this->cgroupManager->getContainerSlicePath($containerId2);
        $this->assertEquals('max 100000', trim((string)file_get_contents("{$sliceDir2}/cpu.max")));

        $configNeg = new CgroupConfig(cpuCores: -4.0);
        $containerId3 = 'cgroup_unlim_03';
        $this->cgroupManager->createContainerSlice($containerId3, $configNeg);
        $sliceDir3 = $this->cgroupManager->getContainerSlicePath($containerId3);
        $this->assertEquals('max 100000', trim((string)file_get_contents("{$sliceDir3}/cpu.max")));
    }

    public function testCgroupZeroSwapLimitEnforcement(): void
    {
        // memorySwapMaxBytes = 0 -> writes "0" to memory.swap.max
        $configZeroSwap = new CgroupConfig(memoryMaxBytes: 1073741824, memorySwapMaxBytes: 0);
        $containerId = 'cgroup_swap_01';
        $this->cgroupManager->createContainerSlice($containerId, $configZeroSwap);

        $sliceDir = $this->cgroupManager->getContainerSlicePath($containerId);
        $this->assertTrue(file_exists("{$sliceDir}/memory.swap.max"));
        $this->assertEquals('0', trim((string)file_get_contents("{$sliceDir}/memory.swap.max")));

        // memorySwapMaxBytes = 536870912 -> writes "536870912"
        $configSwap512 = new CgroupConfig(memoryMaxBytes: 1073741824, memorySwapMaxBytes: 536870912);
        $containerId2 = 'cgroup_swap_02';
        $this->cgroupManager->createContainerSlice($containerId2, $configSwap512);

        $sliceDir2 = $this->cgroupManager->getContainerSlicePath($containerId2);
        $this->assertEquals('536870912', trim((string)file_get_contents("{$sliceDir2}/memory.swap.max")));
    }

    public function testCgroupMemoryHighSoftThresholdCalculation(): void
    {
        // 1. When memoryHighBytes is null, default calculation is 87.5% of memoryMaxBytes
        $configDefaultHigh = new CgroupConfig(memoryMaxBytes: 2147483648); // 2GB
        $containerId = 'cgroup_memhigh_01';
        $this->cgroupManager->createContainerSlice($containerId, $configDefaultHigh);

        $sliceDir = $this->cgroupManager->getContainerSlicePath($containerId);
        $expectedHigh = (int)(2147483648 * 0.875); // 1879048192
        $this->assertEquals((string)$expectedHigh, trim((string)file_get_contents("{$sliceDir}/memory.high")));

        // 2. When memoryHighBytes is explicitly given, exact value is written
        $configExplicitHigh = new CgroupConfig(memoryMaxBytes: 2147483648, memoryHighBytes: 1073741824); // 1GB
        $containerId2 = 'cgroup_memhigh_02';
        $this->cgroupManager->createContainerSlice($containerId2, $configExplicitHigh);

        $sliceDir2 = $this->cgroupManager->getContainerSlicePath($containerId2);
        $this->assertEquals('1073741824', trim((string)file_get_contents("{$sliceDir2}/memory.high")));
    }

    public function testCgroupTelemetryWithEmptyAndMalformedFiles(): void
    {
        $containerId = 'cgroup_telemetry_corrupt';
        $this->cgroupManager->createContainerSlice($containerId, new CgroupConfig());
        $sliceDir = $this->cgroupManager->getContainerSlicePath($containerId);

        // Write empty or corrupted files
        file_put_contents("{$sliceDir}/cpu.stat", "");
        file_put_contents("{$sliceDir}/memory.current", "  invalid_not_a_number  \n");
        file_put_contents("{$sliceDir}/memory.max", "max\n");
        file_put_contents("{$sliceDir}/memory.stat", "corrupt_line_without_space\nanon_invalid\n");
        file_put_contents("{$sliceDir}/memory.events", "");
        file_put_contents("{$sliceDir}/pids.current", "");
        file_put_contents("{$sliceDir}/pids.max", "max\n");
        file_put_contents("{$sliceDir}/io.stat", "8:0 rbytes=invalid wbytes=abc rios=xyz\n");
        file_put_contents("{$sliceDir}/cgroup.events", "frozen \npopulated \n");

        $telemetry = $this->cgroupManager->getTelemetry($containerId);

        // Verify telemetry handles corrupted files safely without throwing fatal exceptions
        $this->assertInstanceOf(CgroupTelemetry::class, $telemetry);
        $this->assertEquals(0.0, $telemetry->cpuUsagePercent);
        $this->assertEquals(0, $telemetry->cpuUsageUsec);
        $this->assertEquals(0, $telemetry->memoryCurrentBytes);
        $this->assertEquals(0, $telemetry->memoryMaxBytes);
        $this->assertEquals(0.0, $telemetry->memoryUsagePercent);
        $this->assertEquals(0, $telemetry->pidsMax);
        $this->assertEquals(0, $telemetry->ioReadBytes);
        $this->assertEquals(0, $telemetry->ioWriteBytes);
        $this->assertFalse($telemetry->isFrozen);
    }

    public function testCgroupTelemetryCpuPercentMathUnderZeroElapsedAndZeroDelta(): void
    {
        $containerId = 'cgroup_telemetry_math';
        $this->cgroupManager->createContainerSlice($containerId, new CgroupConfig());
        $sliceDir = $this->cgroupManager->getContainerSlicePath($containerId);

        file_put_contents("{$sliceDir}/cpu.stat", "usage_usec 500000\n");

        // 1. Elapsed seconds = 0.0 -> CPU% should be 0.0 (no div by zero)
        $prev = new CgroupTelemetry(cpuUsageUsec: 400000, timestamp: microtime(true));
        $t1 = $this->cgroupManager->getTelemetry($containerId, $prev, 0.0);
        $this->assertEquals(0.0, $t1->cpuUsagePercent);

        // 2. Elapsed seconds < 0 -> CPU% should be 0.0
        $t2 = $this->cgroupManager->getTelemetry($containerId, $prev, -1.5);
        $this->assertEquals(0.0, $t2->cpuUsagePercent);

        // 3. Delta usage <= 0 (counter reset or lower) -> CPU% should be 0.0
        $prevHigher = new CgroupTelemetry(cpuUsageUsec: 600000, timestamp: microtime(true) - 1.0);
        $t3 = $this->cgroupManager->getTelemetry($containerId, $prevHigher, 1.0);
        $this->assertEquals(0.0, $t3->cpuUsagePercent);

        // 4. Normal calculation: delta = 100,000 usec over 1.0 sec -> 10.0%
        $prevNormal = new CgroupTelemetry(cpuUsageUsec: 400000, timestamp: microtime(true) - 1.0);
        $t4 = $this->cgroupManager->getTelemetry($containerId, $prevNormal, 1.0);
        $this->assertEquals(10.0, $t4->cpuUsagePercent);
    }

    public function testCgroupFreezeUnfreezeLifecycle(): void
    {
        $containerId = 'cgroup_freeze_test';
        $this->cgroupManager->createContainerSlice($containerId, new CgroupConfig());
        $sliceDir = $this->cgroupManager->getContainerSlicePath($containerId);

        $this->cgroupManager->freeze($containerId);
        $this->assertEquals('1', trim((string)file_get_contents("{$sliceDir}/cgroup.freeze")));

        $this->cgroupManager->unfreeze($containerId);
        $this->assertEquals('0', trim((string)file_get_contents("{$sliceDir}/cgroup.freeze")));
    }

    public function testCgroupAttachProcessFailureModes(): void
    {
        // 1. Missing container directory throws CgroupException
        $this->assertThrows(function () {
            $this->cgroupManager->attachProcess('non_existent_cgroup_dir', 12345);
        }, CgroupException::class, 'Container cgroup slice not found');
    }

    // =========================================================================
    // SECTION 3: OverlayFS Storage & Snapshot Manager Edge Cases
    // =========================================================================

    public function testOverlayFsLowerdirChainPrecedence(): void
    {
        $instanceId = 'inst_layer_precedence';
        $storage = $this->overlayManager->prepareInstanceStorage($instanceId, 'ubuntu-24.04-base');

        // Create Snap 1 (v1.0)
        file_put_contents("{$storage['upper']}/state.txt", "v1.0\n");
        $snap1 = $this->snapshotManager->createSnapshot($instanceId, 'snap-1');

        // Create Snap 2 (v2.0)
        file_put_contents("{$storage['upper']}/state.txt", "v2.0\n");
        $snap2 = $this->snapshotManager->createSnapshot($instanceId, 'snap-2');

        // Create Snap 3 (v3.0)
        file_put_contents("{$storage['upper']}/state.txt", "v3.0\n");
        $snap3 = $this->snapshotManager->createSnapshot($instanceId, 'snap-3');

        // Check metadata.json lower_dirs ordering
        $metaFile = $this->overlayManager->getInstancePath($instanceId) . '/metadata.json';
        $meta = json_decode((string)file_get_contents($metaFile), true);
        $lowers = $meta['lower_dirs'];

        // In OverlayFS, leftmost is highest precedence (newest snapshot first)
        $this->assertCount(4, $lowers); // snap3, snap2, snap1, base
        $this->assertEquals($snap3->layerPath, $lowers[0]);
        $this->assertEquals($snap2->layerPath, $lowers[1]);
        $this->assertEquals($snap1->layerPath, $lowers[2]);
        $this->assertStringContainsString('ubuntu-24.04-base', $lowers[3]);

        // Verify mount options composition preserves exact chain order
        $opts = $this->overlayManager->buildMountOptions($storage['upper'], $storage['work'], $lowers);
        $expectedMountStr = "lowerdir={$snap3->layerPath}:{$snap2->layerPath}:{$snap1->layerPath}:{$lowers[3]},upperdir={$storage['upper']},workdir={$storage['work']}";
        $this->assertEquals($expectedMountStr, $opts);
    }

    public function testOverlayFsSnapshotRollbackHierarchyAndStateRestoration(): void
    {
        $instanceId = 'inst_snap_rollback_tree';
        $storage = $this->overlayManager->prepareInstanceStorage($instanceId, 'ubuntu-24.04-base');

        // 1. Base change -> Snap 1
        file_put_contents("{$storage['upper']}/app.conf", "version=1.0\n");
        $snap1 = $this->snapshotManager->createSnapshot($instanceId, 'release-1.0');

        // 2. Add breaking change -> Snap 2
        file_put_contents("{$storage['upper']}/app.conf", "version=2.0-broken\n");
        file_put_contents("{$storage['upper']}/corrupt.db", "corrupt_data\n");
        $snap2 = $this->snapshotManager->createSnapshot($instanceId, 'broken-2.0');

        // 3. Add uncommitted dirty files in upper
        file_put_contents("{$storage['upper']}/dirty_uncommitted.log", "dirty\n");

        // 4. Execute Rollback to Snap 1
        $rollbackSuccess = $this->snapshotManager->rollbackSnapshot($instanceId, $snap1->id);
        $this->assertTrue($rollbackSuccess);

        // Verify upper layer was purged of dirty changes and corrupt.db
        $this->assertFalse(file_exists("{$storage['upper']}/dirty_uncommitted.log"));
        $this->assertFalse(file_exists("{$storage['upper']}/corrupt.db"));

        // Verify instance metadata active snapshot ID and restored lower layers
        $metaFile = $this->overlayManager->getInstancePath($instanceId) . '/metadata.json';
        $meta = json_decode((string)file_get_contents($metaFile), true);
        $this->assertEquals($snap1->id, $meta['active_snapshot_id']);
        $this->assertEquals($snap1->layerStack, $meta['lower_dirs']);

        // 5. Create new branch from v1.0 -> Snap 1.1
        file_put_contents("{$storage['upper']}/app.conf", "version=1.1-fixed\n");
        $snap11 = $this->snapshotManager->createSnapshot($instanceId, 'fixed-1.1');

        $metaAfterBranch = json_decode((string)file_get_contents($metaFile), true);
        $this->assertEquals($snap11->layerPath, $metaAfterBranch['lower_dirs'][0]);
        $this->assertEquals($snap1->layerPath, $metaAfterBranch['lower_dirs'][1]);
    }

    public function testOverlayFsSnapshotRollbackNonExistentIdThrows(): void
    {
        $instanceId = 'inst_snap_nonexist';
        $this->overlayManager->prepareInstanceStorage($instanceId, 'ubuntu-24.04-base');

        $this->assertThrows(function () use ($instanceId) {
            $this->snapshotManager->rollbackSnapshot($instanceId, 'snap_does_not_exist_9999');
        }, SnapshotException::class, 'not found for instance');
    }

    public function testStorageQuotaManagerExactBoundaryChecks(): void
    {
        $instanceId = 'inst_quota_boundary';
        $storage = $this->overlayManager->prepareInstanceStorage($instanceId, 'ubuntu-24.04-base');

        // Write exactly 10,000 bytes
        file_put_contents("{$storage['upper']}/payload.bin", str_repeat('X', 10000));

        // 1. Boundary: exactly equal to quota limit -> MUST PASS
        $this->assertTrue($this->quotaManager->checkQuota($instanceId, 10000));

        $stats = $this->quotaManager->getQuotaStats($instanceId, 10000);
        $this->assertEquals(10000, $stats['used_bytes']);
        $this->assertEquals(10000, $stats['limit_bytes']);
        $this->assertEquals(100.0, $stats['usage_pct']);
        $this->assertFalse($stats['is_exceeded']);

        // 2. Boundary: 1 byte over quota (limit 9999) -> MUST THROW
        $this->assertThrows(function () use ($instanceId) {
            $this->quotaManager->checkQuota($instanceId, 9999);
        }, StorageException::class, 'exceeded disk quota');

        $statsExceeded = $this->quotaManager->getQuotaStats($instanceId, 9999);
        $this->assertTrue($statsExceeded['is_exceeded']);

        // 3. Boundary: Unlimited storage (limit 0 or negative) -> MUST PASS
        $this->assertTrue($this->quotaManager->checkQuota($instanceId, 0));
        $this->assertTrue($this->quotaManager->checkQuota($instanceId, -100));

        $statsZero = $this->quotaManager->getQuotaStats($instanceId, 0);
        $this->assertEquals(0.0, $statsZero['usage_pct']);
        $this->assertFalse($statsZero['is_exceeded']);
    }

    public function testOverlayFsConfigurationInjection(): void
    {
        $instanceId = 'inst_cfg_inject';
        $storage = $this->overlayManager->prepareInstanceStorage($instanceId, 'ubuntu-24.04-base');

        $config = new ContainerConfig(
            id: $instanceId,
            name: 'node-alpha-42',
            ipAddress: '10.42.0.42',
            dnsServers: ['1.1.1.1', '9.9.9.9'],
            sshAuthorizedKeys: [
                'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIStressTestKey1 test@node',
                'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIStressTestKey2 backup@node',
            ]
        );

        $this->overlayManager->injectConfigurations($storage['merged'], $config);

        // Check /etc/hostname
        $this->assertEquals("node-alpha-42\n", file_get_contents("{$storage['merged']}/etc/hostname"));

        // Check /etc/hosts contains localhost and custom container mapping
        $hosts = file_get_contents("{$storage['merged']}/etc/hosts");
        $this->assertStringContainsString("127.0.0.1\tlocalhost", $hosts);
        $this->assertStringContainsString("10.42.0.42\tnode-alpha-42", $hosts);

        // Check /etc/resolv.conf
        $resolv = file_get_contents("{$storage['merged']}/etc/resolv.conf");
        $this->assertStringContainsString("nameserver 1.1.1.1", $resolv);
        $this->assertStringContainsString("nameserver 9.9.9.9", $resolv);

        // Check /root/.ssh/authorized_keys
        $sshKeysFile = "{$storage['merged']}/root/.ssh/authorized_keys";
        $this->assertTrue(file_exists($sshKeysFile));
        $sshContent = file_get_contents($sshKeysFile);
        $this->assertStringContainsString('IStressTestKey1', $sshContent);
        $this->assertStringContainsString('IStressTestKey2', $sshContent);
    }

    // =========================================================================
    // SECTION 4: IPAM, Network & Router Stress Tests
    // =========================================================================

    public function testIpamBoundarySubnetsAllMasks(): void
    {
        // 1. Single-host /32 subnet
        $sub32 = IpamService::parseCidr('192.168.1.50/32');
        $this->assertEquals('192.168.1.50', $sub32['network_ip']);
        $this->assertEquals('255.255.255.255', $sub32['netmask_ip']);
        $this->assertEquals(1, $sub32['total_hosts']);

        // 2. Point-to-point /31 subnet (RFC 3021)
        $sub31 = IpamService::parseCidr('192.168.1.10/31');
        $this->assertEquals('192.168.1.10', $sub31['network_ip']);
        $this->assertEquals('255.255.255.254', $sub31['netmask_ip']);
        $this->assertEquals(2, $sub31['total_hosts']);

        // 3. Small /30 subnet (4 IPs: net, gw, 1 usable host, bcast)
        $sub30 = IpamService::parseCidr('10.0.0.0/30');
        $this->assertEquals('10.0.0.0', $sub30['network_ip']);
        $this->assertEquals('10.0.0.1', $sub30['gateway_ip']);
        $this->assertEquals('10.0.0.2', $sub30['first_usable']);
        $this->assertEquals('10.0.0.2', $sub30['last_usable']);
        $this->assertEquals('10.0.0.3', $sub30['broadcast_ip']);
        $this->assertEquals(2, $sub30['total_hosts']); // formula: 4 - 2 = 2

        // 4. Large /16 subnet
        $sub16 = IpamService::parseCidr('172.16.0.0/16');
        $this->assertEquals(65534, $sub16['total_hosts']);
    }

    public function testIpamPreferredIpValidationAndConflicts(): void
    {
        $ipam = new IpamService('10.42.0.0/24');

        // 1. Valid preferred IP within subnet
        $ip1 = $ipam->allocateIp('inst_pref_1', '10.42.0.77');
        $this->assertEquals('10.42.0.77', $ip1);
        $this->assertTrue($ipam->isIpAllocated('10.42.0.77'));

        // 2. Preferred IP outside subnet throws InvalidArgumentException
        $this->assertThrows(function () use ($ipam) {
            $ipam->allocateIp('inst_pref_bad', '192.168.1.1');
        }, InvalidArgumentException::class, 'does not belong to subnet');

        // 3. Preferred IP already allocated throws NetworkException
        $this->assertThrows(function () use ($ipam) {
            $ipam->allocateIp('inst_pref_conflict', '10.42.0.77');
        }, NetworkException::class, 'already allocated');
    }

    public function testIpamSubnetExhaustionOnSlash30(): void
    {
        // /30 subnet has 1 available container IP (10.99.0.2) because .0 is net, .1 is gw, .3 is bcast
        $ipam30 = new IpamService('10.99.0.0/30');

        // First container allocates 10.99.0.2
        $ip1 = $ipam30->allocateIp('container_01');
        $this->assertEquals('10.99.0.2', $ip1);

        // Second container must trigger subnet exhaustion
        $this->assertThrows(function () use ($ipam30) {
            $ipam30->allocateIp('container_02');
        }, NetworkException::class, 'IPAM Subnet Exhausted');
    }

    public function testIpamReleaseAndReallocation(): void
    {
        $ipam = new IpamService('10.42.0.0/24');

        $ip1 = $ipam->allocateIp('inst_to_release', '10.42.0.88');
        $this->assertTrue($ipam->isIpAllocated('10.42.0.88'));

        $released = $ipam->releaseIp('inst_to_release');
        $this->assertTrue($released);
        $this->assertFalse($ipam->isIpAllocated('10.42.0.88'));

        // Can immediately reallocate to another instance
        $ip2 = $ipam->allocateIp('inst_reallocated', '10.42.0.88');
        $this->assertEquals('10.42.0.88', $ip2);
    }

    public function testIpamSubnetOverlapDetection(): void
    {
        // Overlapping subnets: parent /16 and child /24
        $this->assertTrue(IpamService::checkSubnetOverlap('10.42.0.0/16', '10.42.5.0/24'));
        $this->assertTrue(IpamService::checkSubnetOverlap('10.42.5.0/24', '10.42.0.0/16'));

        // Identical subnets
        $this->assertTrue(IpamService::checkSubnetOverlap('10.42.0.0/24', '10.42.0.0/24'));

        // Non-overlapping subnets
        $this->assertFalse(IpamService::checkSubnetOverlap('10.42.0.0/24', '10.43.0.0/24'));
        $this->assertFalse(IpamService::checkSubnetOverlap('192.168.1.0/24', '172.16.0.0/12'));
    }

    public function testSimulatedNatRouterPortCollisions(): void
    {
        $router = new SimulatedNatRouter();

        $rule1 = $router->addPortForward('0.0.0.0', 8080, '10.42.0.10', 80, 'tcp');
        $this->assertNotEmpty($rule1);

        // Adding duplicate public port forward on same proto must throw RuntimeException
        $this->assertThrows(function () use ($router) {
            $router->addPortForward('0.0.0.0', 8080, '10.42.0.20', 8080, 'tcp');
        }, RuntimeException::class, 'Port collision');

        // Different protocol on same port is allowed
        $ruleUdp = $router->addPortForward('0.0.0.0', 8080, '10.42.0.10', 80, 'udp');
        $this->assertNotEmpty($ruleUdp);
    }

    // =========================================================================
    // SECTION 5: JSON-RPC 2.0 Node Protocol & Cryptographic Stress Tests
    // =========================================================================

    public function testJsonRpcProtocolErrorCodes(): void
    {
        // 1. Empty payload -> PARSE_ERROR (-32700)
        $this->assertThrows(function () {
            JsonRpcProtocol::parsePayload('');
        }, RuntimeException::class, 'Empty JSON payload');

        // 2. Malformed JSON -> PARSE_ERROR (-32700)
        $this->assertThrows(function () {
            JsonRpcProtocol::parsePayload('{"jsonrpc": "2.0", "method": invalid_json');
        }, RuntimeException::class, 'Parse error: Malformed JSON string');

        // 3. Top-level scalar -> INVALID_REQUEST (-32600)
        $this->assertThrows(function () {
            JsonRpcProtocol::parsePayload('"scalar_string"');
        }, RuntimeException::class, 'Invalid Request');

        // 4. Missing jsonrpc field -> INVALID_REQUEST
        $this->assertThrows(function () {
            JsonRpcProtocol::validateRequestStructure(['method' => 'node.ping']);
        }, RuntimeException::class, "Missing or invalid 'jsonrpc'");

        // 5. Missing method field -> INVALID_REQUEST
        $this->assertThrows(function () {
            JsonRpcProtocol::validateRequestStructure(['jsonrpc' => '2.0']);
        }, RuntimeException::class, "Missing or invalid 'method'");

        // 6. Invalid params type (not array/object) -> INVALID_REQUEST
        $this->assertThrows(function () {
            JsonRpcProtocol::validateRequestStructure(['jsonrpc' => '2.0', 'method' => 'node.ping', 'params' => 'invalid_str']);
        }, RuntimeException::class, "'params' must be an object or array");
    }

    public function testNodeSecurityCodecTamperingAndDrift(): void
    {
        $secretKey = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';
        $payload = ['jsonrpc' => '2.0', 'method' => 'node.ping', 'id' => 42];

        // 1. Normal seal & open round-trip
        $frame = NodeSecurityCodec::sealPayload($payload, $secretKey, 'node-stress-1');
        $opened = NodeSecurityCodec::openPayload($frame, $secretKey);
        $this->assertEquals($payload, $opened);

        // 2. Tampered signature -> UNAUTHORIZED (-32001)
        $env = json_decode($frame, true);
        $env['signature'] = 'tampered_signature_hex_0000';
        $tamperedFrame = json_encode($env);

        $this->assertThrows(function () use ($tamperedFrame, $secretKey) {
            NodeSecurityCodec::openPayload($tamperedFrame, $secretKey);
        }, NodeRpcException::class, 'HMAC signature verification failed');

        // 3. Replay attack: Timestamp drift > 60 seconds (in past)
        $pastEnv = json_decode($frame, true);
        $pastEnv['timestamp'] = time() - 120; // 2 minutes ago
        $pastData = "{$pastEnv['timestamp']}:{$pastEnv['nonce']}:{$pastEnv['node_id']}:{$pastEnv['payload']}";
        $pastEnv['signature'] = hash_hmac('sha256', $pastData, $secretKey);
        $pastFrame = json_encode($pastEnv);

        $this->assertThrows(function () use ($pastFrame, $secretKey) {
            NodeSecurityCodec::openPayload($pastFrame, $secretKey, 60);
        }, NodeRpcException::class, 'Replay attack protection');

        // 4. Replay attack: Timestamp drift in future
        $futureEnv = json_decode($frame, true);
        $futureEnv['timestamp'] = time() + 120;
        $futureData = "{$futureEnv['timestamp']}:{$futureEnv['nonce']}:{$futureEnv['node_id']}:{$futureEnv['payload']}";
        $futureEnv['signature'] = hash_hmac('sha256', $futureData, $secretKey);
        $futureFrame = json_encode($futureEnv);

        $this->assertThrows(function () use ($futureFrame, $secretKey) {
            NodeSecurityCodec::openPayload($futureFrame, $secretKey, 60);
        }, NodeRpcException::class, 'Replay attack protection');
    }

    public function testNodeDaemonDirectMethodDispatch(): void
    {
        $daemon = new NodeDaemon($this->mockDriver, 'node-stress-local');

        // 1. node.ping
        $resPing = $daemon->dispatchSingleRequest(['jsonrpc' => '2.0', 'method' => 'node.ping', 'id' => 1]);
        $this->assertEquals(1, $resPing['id']);
        $this->assertEquals('ONLINE', $resPing['result']['status']);
        $this->assertEquals('node-stress-local', $resPing['result']['node_id']);

        // 2. container.create
        $resCreate = $daemon->dispatchSingleRequest([
            'jsonrpc' => '2.0',
            'method'  => 'container.create',
            'params'  => [
                'id'        => 'inst_daemon_test',
                'name'      => 'daemon-vps',
                'vcpu'      => 2,
                'ram_mb'    => 2048,
                'disk_gb'   => 40,
            ],
            'id'      => 2
        ]);
        $this->assertEquals(2, $resCreate['id']);
        $this->assertEquals('inst_daemon_test', $resCreate['result']['id']);
        $this->assertEquals(ContainerState::CREATED, $resCreate['result']['status']);

        // 3. container.start
        $resStart = $daemon->dispatchSingleRequest([
            'jsonrpc' => '2.0',
            'method'  => 'container.start',
            'params'  => ['instance_id' => 'inst_daemon_test'],
            'id'      => 3
        ]);
        $this->assertEquals('RUNNING', $resStart['result']['status']);

        // 4. container.stats
        $resStats = $daemon->dispatchSingleRequest([
            'jsonrpc' => '2.0',
            'method'  => 'container.stats',
            'params'  => ['instance_id' => 'inst_daemon_test'],
            'id'      => 4
        ]);
        $this->assertEquals('inst_daemon_test', $resStats['result']['instance_id']);
        $this->assertTrue($resStats['result']['cpu_usage_pct'] >= 0);

        // 5. container.exec
        $resExec = $daemon->dispatchSingleRequest([
            'jsonrpc' => '2.0',
            'method'  => 'container.exec',
            'params'  => ['instance_id' => 'inst_daemon_test', 'command' => ['whoami']],
            'id'      => 5
        ]);
        $this->assertEquals(0, $resExec['result']['exit_code']);
        $this->assertStringContainsString('root', $resExec['result']['stdout']);

        // 6. container.snapshot & rollback
        $resSnap = $daemon->dispatchSingleRequest([
            'jsonrpc' => '2.0',
            'method'  => 'container.snapshot',
            'params'  => ['instance_id' => 'inst_daemon_test', 'name' => 'snap-daemon-01'],
            'id'      => 6
        ]);
        $snapId = $resSnap['result']['snapshot_id'];
        $this->assertNotEmpty($snapId);

        $resRollback = $daemon->dispatchSingleRequest([
            'jsonrpc' => '2.0',
            'method'  => 'container.rollback',
            'params'  => ['instance_id' => 'inst_daemon_test', 'snapshot_id' => $snapId],
            'id'      => 7
        ]);
        $this->assertEquals('ROLLED_BACK', $resRollback['result']['status']);

        // 7. container.destroy
        $resDestroy = $daemon->dispatchSingleRequest([
            'jsonrpc' => '2.0',
            'method'  => 'container.destroy',
            'params'  => ['instance_id' => 'inst_daemon_test'],
            'id'      => 8
        ]);
        $this->assertEquals('DESTROYED', $resDestroy['result']['status']);
    }

    public function testNodeDaemonMissingRequiredParams(): void
    {
        $daemon = new NodeDaemon($this->mockDriver, 'node-stress-local');

        // Missing instance_id in container.start
        $res = $daemon->dispatchSingleRequest([
            'jsonrpc' => '2.0',
            'method'  => 'container.start',
            'params'  => [],
            'id'      => 10
        ]);

        $this->assertArrayHasKey('error', $res);
        $this->assertEquals(JsonRpcProtocol::INVALID_PARAMS, $res['error']['code']);
        $this->assertStringContainsString('Missing required parameter', $res['error']['message']);
    }

    public function testHighVolumeSequentialSnapshotsAndArbitraryRollbacks(): void
    {
        $instanceId = 'inst_snap_highvol';
        $storage = $this->overlayManager->prepareInstanceStorage($instanceId, 'ubuntu-24.04-base');

        /** @var list<string> $createdSnapIds */
        $createdSnapIds = [];

        // Create 10 sequential snapshots
        for ($i = 1; $i <= 10; $i++) {
            file_put_contents("{$storage['upper']}/step_{$i}.log", "Step {$i} data\n");
            $snap = $this->snapshotManager->createSnapshot($instanceId, "snap-step-{$i}");
            $createdSnapIds[] = $snap->id;
        }

        $this->assertCount(10, $createdSnapIds);
        $allSnaps = $this->snapshotManager->listSnapshots($instanceId);
        $this->assertCount(10, $allSnaps);

        // Rollback to snapshot 4
        $this->snapshotManager->rollbackSnapshot($instanceId, $createdSnapIds[3]);
        $metaFile = $this->overlayManager->getInstancePath($instanceId) . '/metadata.json';
        $meta = json_decode((string)file_get_contents($metaFile), true);
        $this->assertEquals($createdSnapIds[3], $meta['active_snapshot_id']);

        // Create branch 4.1
        file_put_contents("{$storage['upper']}/step_4_branch.log", "Branch 4.1\n");
        $snap41 = $this->snapshotManager->createSnapshot($instanceId, 'snap-step-4-branch');
        $this->assertNotEmpty($snap41->id);

        // Rollback to snapshot 8
        $this->snapshotManager->rollbackSnapshot($instanceId, $createdSnapIds[7]);
        $meta8 = json_decode((string)file_get_contents($metaFile), true);
        $this->assertEquals($createdSnapIds[7], $meta8['active_snapshot_id']);

        // Rollback to snapshot 1
        $this->snapshotManager->rollbackSnapshot($instanceId, $createdSnapIds[0]);
        $meta1 = json_decode((string)file_get_contents($metaFile), true);
        $this->assertEquals($createdSnapIds[0], $meta1['active_snapshot_id']);
        $this->assertCount(2, $meta1['lower_dirs']); // snap 1 + base image
    }

    public function testCgroupTelemetryFuzzingExtremeValues(): void
    {
        $containerId = 'cgroup_fuzz_test';
        $this->cgroupManager->createContainerSlice($containerId, new CgroupConfig());
        $sliceDir = $this->cgroupManager->getContainerSlicePath($containerId);

        // Write extreme 64-bit numbers and high IOPS
        file_put_contents("{$sliceDir}/cpu.stat", "usage_usec 9223372036854775000\nuser_usec 5000000000000000\nsystem_usec 4223372036854775\nnr_throttled 999999\nthrottled_usec 88888888\n");
        file_put_contents("{$sliceDir}/memory.current", "17179869184\n"); // 16GB
        file_put_contents("{$sliceDir}/memory.max", "34359738368\n"); // 32GB
        file_put_contents("{$sliceDir}/memory.stat", "anon 12884901888\nfile 4294967296\n");
        file_put_contents("{$sliceDir}/memory.events", "oom 15\noom_kill 12\n");
        file_put_contents("{$sliceDir}/pids.current", "65535\n");
        file_put_contents("{$sliceDir}/pids.max", "131072\n");
        file_put_contents("{$sliceDir}/io.stat", "259:0 rbytes=999999999999999 wbytes=888888888888888 rios=5000000 wios=4000000\n");
        file_put_contents("{$sliceDir}/cgroup.events", "populated 1\nfrozen 1\n");

        $telemetry = $this->cgroupManager->getTelemetry($containerId);

        $this->assertEquals(9223372036854775000, $telemetry->cpuUsageUsec);
        $this->assertEquals(17179869184, $telemetry->memoryCurrentBytes);
        $this->assertEquals(34359738368, $telemetry->memoryMaxBytes);
        $this->assertEquals(50.0, $telemetry->memoryUsagePercent);
        $this->assertEquals(12, $telemetry->memoryOomCount);
        $this->assertEquals(65535, $telemetry->pidsCurrent);
        $this->assertEquals(131072, $telemetry->pidsMax);
        $this->assertEquals(999999999999999, $telemetry->ioReadBytes);
        $this->assertEquals(888888888888888, $telemetry->ioWriteBytes);
        $this->assertEquals(5000000, $telemetry->ioReadOps);
        $this->assertEquals(4000000, $telemetry->ioWriteOps);
        $this->assertTrue($telemetry->isFrozen);
        $this->assertTrue($telemetry->isPopulated);
    }

    public function testIpamMonteCarloAllocationAndReleaseLoop(): void
    {
        $ipam = new IpamService('10.42.0.0/24');
        $activeAllocations = []; // ['inst_X' => '10.42.0.Y']

        for ($cycle = 1; $cycle <= 300; $cycle++) {
            $action = mt_rand(0, 100);
            if ($action < 60 || empty($activeAllocations)) {
                // Allocate
                $instId = 'sim_inst_' . $cycle;
                if (count($activeAllocations) < 250) {
                    $ip = $ipam->allocateIp($instId);
                    $this->assertTrue($ipam->isIpAllocated($ip));
                    $activeAllocations[$instId] = $ip;
                }
            } else {
                // Release random active
                $randKey = array_rand($activeAllocations);
                $ipToRelease = $activeAllocations[$randKey];
                $this->assertTrue($ipam->releaseIp($randKey));
                $this->assertFalse($ipam->isIpAllocated($ipToRelease));
                unset($activeAllocations[$randKey]);
            }
        }

        // Release all remaining active allocations
        foreach ($activeAllocations as $instId => $ip) {
            $ipam->releaseIp($instId);
            $this->assertFalse($ipam->isIpAllocated($ip));
        }

        // Only network (.0), gateway (.1), broadcast (.255) remain
        $allocated = $ipam->getAllocatedIps();
        $this->assertCount(3, $allocated);
        $this->assertArrayHasKey('10.42.0.0', $allocated);
        $this->assertArrayHasKey('10.42.0.1', $allocated);
        $this->assertArrayHasKey('10.42.0.255', $allocated);
    }

    public function testNodeSecurityCodecCryptographicFuzzing(): void
    {
        $secretKey = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';
        $validPayload = ['jsonrpc' => '2.0', 'method' => 'node.ping', 'id' => 999];
        $sealedFrame = NodeSecurityCodec::sealPayload($validPayload, $secretKey, 'node-fuzz');

        // Test 20 fuzz variants
        $fuzzVariants = [
            '{"corrupt": true}',
            '{"signature": "abc"}',
            '{"payload": "abc", "signature": "xyz"}',
            '{"payload": "abc", "signature": "xyz", "timestamp": 0}',
            '{"payload": "abc", "signature": "xyz", "timestamp": ' . time() . '}',
            'not a json string at all',
            '{"signature": "123", "payload": "123", "timestamp": ' . (time() + 999) . ', "nonce": "abc"}',
            '{"signature": "123", "payload": "123", "timestamp": ' . (time() - 999) . ', "nonce": "abc"}',
        ];

        // Add bit-flipped signature and ciphertext variants
        $decoded = json_decode($sealedFrame, true);
        for ($i = 0; $i < 10; $i++) {
            $mutated = $decoded;
            $char = $mutated['signature'][$i % 32];
            $mutated['signature'] = substr_replace($mutated['signature'], ($char === 'a' ? 'b' : 'a'), $i % 32, 1);
            $fuzzVariants[] = json_encode($mutated);
        }

        foreach ($fuzzVariants as $badFrame) {
            $this->assertThrows(function () use ($badFrame, $secretKey) {
                NodeSecurityCodec::openPayload($badFrame, $secretKey, 60);
            }, NodeRpcException::class);
        }
    }

    public function testContainerStateTransitionMatrix(): void
    {
        $this->mockDriver->reset();
        $config = new ContainerConfig(id: 'inst_matrix_01', name: 'matrix-vps');
        $c = $this->mockDriver->create($config);
        $this->assertEquals(ContainerState::CREATED, $c->getState());

        // 1. Double CREATE -> throws
        $this->assertThrows(function () use ($config) {
            $this->mockDriver->create($config);
        }, VirtualizationException::class, 'already exists');

        // 2. PAUSE from CREATED -> throws
        $this->assertThrows(function () {
            $this->mockDriver->pause('inst_matrix_01');
        }, VirtualizationException::class, 'Cannot pause');

        // 3. RESUME from CREATED -> throws
        $this->assertThrows(function () {
            $this->mockDriver->resume('inst_matrix_01');
        }, VirtualizationException::class, 'Cannot resume');

        // 4. START from CREATED -> RUNNING
        $this->assertTrue($this->mockDriver->start('inst_matrix_01'));
        $this->assertEquals(ContainerState::RUNNING, $c->getState());

        // 5. START from RUNNING -> throws
        $this->assertThrows(function () {
            $this->mockDriver->start('inst_matrix_01');
        }, VirtualizationException::class, 'already running');

        // 6. PAUSE from RUNNING -> PAUSED
        $this->assertTrue($this->mockDriver->pause('inst_matrix_01'));
        $this->assertEquals(ContainerState::PAUSED, $c->getState());

        // 7. PAUSE from PAUSED -> throws
        $this->assertThrows(function () {
            $this->mockDriver->pause('inst_matrix_01');
        }, VirtualizationException::class, 'Cannot pause');

        // 8. RESUME from PAUSED -> RUNNING
        $this->assertTrue($this->mockDriver->resume('inst_matrix_01'));
        $this->assertEquals(ContainerState::RUNNING, $c->getState());

        // 9. STOP from RUNNING -> STOPPED
        $this->assertTrue($this->mockDriver->stop('inst_matrix_01'));
        $this->assertEquals(ContainerState::STOPPED, $c->getState());

        // 10. STOP from STOPPED without force -> throws
        $this->assertThrows(function () {
            $this->mockDriver->stop('inst_matrix_01', 10, false);
        }, VirtualizationException::class, 'already stopped');

        // 11. STOP from STOPPED with force -> succeeds
        $this->assertTrue($this->mockDriver->stop('inst_matrix_01', 10, true));

        // 12. DESTROY from STOPPED -> succeeds
        $this->assertTrue($this->mockDriver->destroy('inst_matrix_01'));

        // 13. Any action on destroyed container throws not found
        $this->assertThrows(function () {
            $this->mockDriver->start('inst_matrix_01');
        }, VirtualizationException::class, 'not found');
    }

    private function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = @scandir($dir);
        if ($files) {
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                $path = "{$dir}/{$file}";
                is_dir($path) ? $this->recursiveDelete($path) : @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
