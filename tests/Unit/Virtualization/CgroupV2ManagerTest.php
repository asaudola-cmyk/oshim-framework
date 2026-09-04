<?php
declare(strict_types=1);

namespace Tests\Unit\Virtualization;

use Oshim\Testing\TestCase;
use Oshim\Virtualization\Cgroup\CgroupConfig;
use Oshim\Virtualization\Cgroup\CgroupTelemetry;
use Oshim\Virtualization\Cgroup\CgroupV2Manager;
use Oshim\Virtualization\Exceptions\CgroupException;

class CgroupV2ManagerTest extends TestCase
{
    private string $tempCgroupRoot;
    private CgroupV2Manager $manager;

    public function setUp(): void
    {
        parent::setUp();
        $this->tempCgroupRoot = sys_get_temp_dir() . '/oshim_cg_test_' . bin2hex(random_bytes(4));
        @mkdir($this->tempCgroupRoot, 0777, true);
        $this->manager = new CgroupV2Manager($this->tempCgroupRoot, 'oshim');
    }

    public function tearDown(): void
    {
        $this->recursiveDelete($this->tempCgroupRoot);
        parent::tearDown();
    }

    public function testCgroupConfigCreationAndMapping(): void
    {
        $config = CgroupConfig::fromArray([
            'cpu_limit'          => 2.5,
            'cpu_weight'         => 200,
            'memory_limit_bytes' => 2147483648,
            'pids_limit'         => 1024,
            'io_limits'          => [
                '8:0' => ['rbps' => 52428800, 'wbps' => 52428800]
            ]
        ]);

        $this->assertEquals(2.5, $config->cpuCores);
        $this->assertEquals(200, $config->cpuWeight);
        $this->assertEquals(2147483648, $config->memoryMaxBytes);
        $this->assertEquals((int)(2147483648 * 0.875), $config->memoryHighBytes);
        $this->assertEquals(1024, $config->pidsMax);
        $this->assertArrayHasKey('8:0', $config->ioLimits);

        $arr = $config->toArray();
        $this->assertEquals(2.5, $arr['cpu_cores']);
        $this->assertEquals(2147483648, $arr['memory_max_bytes']);
    }

    public function testCgroupSliceCreationAndLimitEnforcement(): void
    {
        $config = new CgroupConfig(
            cpuCores: 2.0,
            cpuWeight: 100,
            memoryMaxBytes: 1073741824, // 1 GB
            memoryHighBytes: 939524096,
            memoryLowBytes: 268435456,
            memorySwapMaxBytes: 0,
            pidsMax: 512,
            ioLimits: ['8:0' => ['rbps' => 52428800, 'wbps' => 26214400, 'riops' => 1000, 'wiops' => 500]]
        );

        $containerId = 'test_inst_101';
        $this->manager->createContainerSlice($containerId, $config);

        $sliceDir = $this->manager->getContainerSlicePath($containerId);
        $this->assertTrue(is_dir($sliceDir));

        // Verify cpu.max (2.0 cores = 200000 100000)
        $cpuMax = trim((string)file_get_contents("{$sliceDir}/cpu.max"));
        $this->assertEquals('200000 100000', $cpuMax);

        // Verify cpu.weight
        $cpuWeight = trim((string)file_get_contents("{$sliceDir}/cpu.weight"));
        $this->assertEquals('100', $cpuWeight);

        // Verify memory.max & memory.high & memory.swap.max & memory.oom.group
        $memMax = trim((string)file_get_contents("{$sliceDir}/memory.max"));
        $this->assertEquals('1073741824', $memMax);

        $memHigh = trim((string)file_get_contents("{$sliceDir}/memory.high"));
        $this->assertEquals('939524096', $memHigh);

        $memSwap = trim((string)file_get_contents("{$sliceDir}/memory.swap.max"));
        $this->assertEquals('0', $memSwap);

        $oomGroup = trim((string)file_get_contents("{$sliceDir}/memory.oom.group"));
        $this->assertEquals('1', $oomGroup);

        // Verify pids.max
        $pidsMax = trim((string)file_get_contents("{$sliceDir}/pids.max"));
        $this->assertEquals('512', $pidsMax);

        // Verify io.max
        $ioMax = trim((string)file_get_contents("{$sliceDir}/io.max"));
        $this->assertStringContainsString('8:0', $ioMax);
        $this->assertStringContainsString('rbps=52428800', $ioMax);
        $this->assertStringContainsString('wbps=26214400', $ioMax);
    }

    public function testUnlimitedCgroupQuotas(): void
    {
        $unlimitedConfig = new CgroupConfig(cpuCores: null, memoryMaxBytes: null, pidsMax: null);
        $containerId = 'test_inst_unlimited';
        $this->manager->createContainerSlice($containerId, $unlimitedConfig);

        $sliceDir = $this->manager->getContainerSlicePath($containerId);
        $cpuMax = trim((string)file_get_contents("{$sliceDir}/cpu.max"));
        $this->assertEquals('max 100000', $cpuMax);

        $memMax = trim((string)file_get_contents("{$sliceDir}/memory.max"));
        $this->assertEquals('max', $memMax);

        $pidsMax = trim((string)file_get_contents("{$sliceDir}/pids.max"));
        $this->assertEquals('max', $pidsMax);
    }

    public function testProcessAttachmentAndActivePids(): void
    {
        $containerId = 'test_inst_proc';
        $config = new CgroupConfig(cpuCores: 1.0, memoryMaxBytes: 536870912);
        $this->manager->createContainerSlice($containerId, $config);

        $this->manager->attachProcess($containerId, 40101);
        $pids = $this->manager->getActivePids($containerId);

        $this->assertCount(1, $pids);
        $this->assertEquals(40101, $pids[0]);

        // Attach second PID
        $sliceDir = $this->manager->getContainerSlicePath($containerId);
        file_put_contents("{$sliceDir}/cgroup.procs", "40101\n40102\n40103\n");
        $allPids = $this->manager->getActivePids($containerId);

        $this->assertCount(3, $allPids);
        $this->assertEquals([40101, 40102, 40103], $allPids);
    }

    public function testAttachProcessToNonExistentCgroupThrowsCgroupException(): void
    {
        $this->assertThrows(function () {
            $this->manager->attachProcess('nonexistent_container', 1234);
        }, CgroupException::class);
    }

    public function testApplyLimitsToNonExistentCgroupThrowsCgroupException(): void
    {
        $this->assertThrows(function () {
            $this->manager->applyLimits('nonexistent_container', new CgroupConfig());
        }, CgroupException::class);
    }

    public function testFreezeAndUnfreeze(): void
    {
        $containerId = 'test_inst_freeze';
        $config = new CgroupConfig();
        $this->manager->createContainerSlice($containerId, $config);

        $sliceDir = $this->manager->getContainerSlicePath($containerId);

        $this->manager->freeze($containerId);
        $this->assertEquals('1', trim((string)file_get_contents("{$sliceDir}/cgroup.freeze")));

        $this->manager->unfreeze($containerId);
        $this->assertEquals('0', trim((string)file_get_contents("{$sliceDir}/cgroup.freeze")));
    }

    public function testTelemetryComputationFromFiles(): void
    {
        $containerId = 'test_inst_telemetry';
        $config = new CgroupConfig(cpuCores: 2.0, memoryMaxBytes: 2147483648);
        $this->manager->createContainerSlice($containerId, $config);

        $sliceDir = $this->manager->getContainerSlicePath($containerId);

        // Write simulated cgroup status files
        file_put_contents("{$sliceDir}/cpu.stat", "usage_usec 500000\nuser_usec 350000\nsystem_usec 150000\nnr_throttled 2\nthrottled_usec 12000\n");
        file_put_contents("{$sliceDir}/memory.current", "1073741824\n"); // 1 GB (50%)
        file_put_contents("{$sliceDir}/memory.stat", "anon 858993459\nfile 214748365\n");
        file_put_contents("{$sliceDir}/memory.events", "oom 0\noom_kill 0\n");
        file_put_contents("{$sliceDir}/pids.current", "24\n");
        file_put_contents("{$sliceDir}/io.stat", "8:0 rbytes=10485760 wbytes=5242880 rios=120 wios=60\n");
        file_put_contents("{$sliceDir}/cgroup.events", "populated 1\nfrozen 0\n");

        $prev = new CgroupTelemetry(cpuUsageUsec: 300000, timestamp: microtime(true) - 1.0);
        $telemetry = $this->manager->getTelemetry($containerId, $prev, 1.0);

        $this->assertEquals(500000, $telemetry->cpuUsageUsec);
        $this->assertEquals(350000, $telemetry->cpuUserUsec);
        $this->assertEquals(150000, $telemetry->cpuSystemUsec);
        $this->assertEquals(2, $telemetry->cpuNrThrottled);
        $this->assertEquals(20.0, $telemetry->cpuUsagePercent); // (500000 - 300000) / 1000000 * 100 = 20%
        $this->assertEquals(1073741824, $telemetry->memoryCurrentBytes);
        $this->assertEquals(50.0, $telemetry->memoryUsagePercent);
        $this->assertEquals(24, $telemetry->pidsCurrent);
        $this->assertEquals(10485760, $telemetry->ioReadBytes);
        $this->assertEquals(5242880, $telemetry->ioWriteBytes);
        $this->assertEquals(120, $telemetry->ioReadOps);
        $this->assertEquals(60, $telemetry->ioWriteOps);
        $this->assertTrue($telemetry->isPopulated);
        $this->assertFalse($telemetry->isFrozen);

        $arr = $telemetry->toArray();
        $this->assertEquals(20.0, $arr['cpu_usage_pct']);
        $this->assertEquals(50.0, $arr['memory_usage_pct']);
        $this->assertEquals(24, $arr['pids_count']);
    }

    public function testDestroyContainerSliceCleanup(): void
    {
        $containerId = 'test_inst_destroy';
        $config = new CgroupConfig();
        $this->manager->createContainerSlice($containerId, $config);

        $sliceDir = $this->manager->getContainerSlicePath($containerId);
        $this->assertTrue(is_dir($sliceDir));

        $this->manager->destroyContainerSlice($containerId, 1);
        $this->assertFalse(is_dir($sliceDir));
    }

    public function testEmptyFileParsersGracefulHandling(): void
    {
        $kv = $this->manager->parseKeyValueFile('/nonexistent/path/file.txt');
        $this->assertEquals([], $kv);

        $io = $this->manager->parseIoStatFile('/nonexistent/path/file.txt');
        $this->assertEquals(['rbytes' => 0, 'wbytes' => 0, 'rios' => 0, 'wios' => 0], $io);
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
