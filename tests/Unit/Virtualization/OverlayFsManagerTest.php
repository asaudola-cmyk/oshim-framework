<?php
declare(strict_types=1);

namespace Tests\Unit\Virtualization;

use Oshim\Testing\TestCase;
use Oshim\Virtualization\ContainerConfig;
use Oshim\Virtualization\Exceptions\SnapshotException;
use Oshim\Virtualization\Exceptions\StorageException;
use Oshim\Virtualization\Storage\OverlayFsManager;
use Oshim\Virtualization\Storage\SnapshotManager;
use Oshim\Virtualization\Storage\SnapshotMetadata;
use Oshim\Virtualization\Storage\StorageQuotaManager;
use Oshim\Virtualization\Syscall\MockSyscall;

class OverlayFsManagerTest extends TestCase
{
    private string $tempStorageRoot;
    private MockSyscall $mockSyscall;
    private OverlayFsManager $overlayManager;
    private SnapshotManager $snapshotManager;
    private StorageQuotaManager $quotaManager;

    public function setUp(): void
    {
        parent::setUp();
        $this->tempStorageRoot = sys_get_temp_dir() . '/oshim_storage_test_' . bin2hex(random_bytes(4));
        @mkdir($this->tempStorageRoot, 0777, true);
        $this->mockSyscall = new MockSyscall();
        $this->overlayManager = new OverlayFsManager($this->tempStorageRoot, $this->mockSyscall);
        $this->snapshotManager = new SnapshotManager($this->tempStorageRoot, $this->overlayManager);
        $this->quotaManager = new StorageQuotaManager($this->overlayManager);
    }

    public function tearDown(): void
    {
        $this->overlayManager->recursiveRmdir($this->tempStorageRoot);
        parent::tearDown();
    }

    public function testStorageInitializationAndInstanceDirectoryLayout(): void
    {
        $this->overlayManager->initializeStorage();

        $this->assertTrue(is_dir($this->overlayManager->getImagesPath()));
        $this->assertTrue(is_dir($this->overlayManager->getInstancesPath()));
        $this->assertTrue(is_dir($this->overlayManager->getSnapshotsPath()));

        $storage = $this->overlayManager->prepareInstanceStorage('inst_test_01', 'ubuntu-24.04-base');
        $this->assertTrue(is_dir($storage['upper']));
        $this->assertTrue(is_dir($storage['work']));
        $this->assertTrue(is_dir($storage['merged']));
        $this->assertCount(1, $storage['lowers']);

        $metaFile = $this->overlayManager->getInstancePath('inst_test_01') . '/metadata.json';
        $this->assertTrue(file_exists($metaFile));
        $meta = json_decode((string)file_get_contents($metaFile), true);
        $this->assertEquals('inst_test_01', $meta['instance_id']);
        $this->assertEquals('ubuntu-24.04-base', $meta['base_image']);
    }

    public function testMountOptionsComposition(): void
    {
        $upper = '/var/lib/oshim/instances/inst_1/upper';
        $work = '/var/lib/oshim/instances/inst_1/work';
        $lowers = [
            '/var/lib/oshim/snapshots/inst_1/snap_2/layer',
            '/var/lib/oshim/snapshots/inst_1/snap_1/layer',
            '/var/lib/oshim/images/ubuntu-24.04/rootfs',
        ];

        $opts = $this->overlayManager->buildMountOptions($upper, $work, $lowers);
        $expected = "lowerdir=/var/lib/oshim/snapshots/inst_1/snap_2/layer:/var/lib/oshim/snapshots/inst_1/snap_1/layer:/var/lib/oshim/images/ubuntu-24.04/rootfs,upperdir=/var/lib/oshim/instances/inst_1/upper,workdir=/var/lib/oshim/instances/inst_1/work";

        $this->assertEquals($expected, $opts);

        // Empty lowerdirs throws StorageException
        $this->assertThrows(function () use ($upper, $work) {
            $this->overlayManager->buildMountOptions($upper, $work, []);
        }, StorageException::class);
    }

    public function testHostConfigurationInjection(): void
    {
        $storage = $this->overlayManager->prepareInstanceStorage('inst_config_test', 'ubuntu-24.04-base');
        $config = new ContainerConfig(
            id: 'inst_config_test',
            name: 'vps-client-alpha',
            ipAddress: '10.42.0.55',
            dnsServers: ['1.1.1.1', '8.8.8.8'],
            sshAuthorizedKeys: ['ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIGeneratedKey123 root@master']
        );

        $this->overlayManager->injectConfigurations($storage['merged'], $config);

        $hostname = trim((string)file_get_contents("{$storage['merged']}/etc/hostname"));
        $this->assertEquals('vps-client-alpha', $hostname);

        $hosts = (string)file_get_contents("{$storage['merged']}/etc/hosts");
        $this->assertStringContainsString('127.0.0.1', $hosts);
        $this->assertStringContainsString("10.42.0.55\tvps-client-alpha", $hosts);

        $resolv = (string)file_get_contents("{$storage['merged']}/etc/resolv.conf");
        $this->assertStringContainsString('nameserver 1.1.1.1', $resolv);
        $this->assertStringContainsString('nameserver 8.8.8.8', $resolv);

        $sshKeys = (string)file_get_contents("{$storage['merged']}/root/.ssh/authorized_keys");
        $this->assertStringContainsString('AAAAC3NzaC1lZDI1NTE5AAAAIGeneratedKey123', $sshKeys);
    }

    public function testSnapshotLifecycleAndRollback(): void
    {
        $instanceId = 'inst_snap_test';
        $storage = $this->overlayManager->prepareInstanceStorage($instanceId, 'ubuntu-24.04-base');

        // 1. Create file in upper layer
        file_put_contents("{$storage['upper']}/version.txt", "1.0.0\n");

        // 2. Create Snapshot 1
        $snap1 = $this->snapshotManager->createSnapshot($instanceId, 'v1.0-release', 'Initial release checkpoint');
        $this->assertNotEmpty($snap1->id);
        $this->assertEquals('v1.0-release', $snap1->name);
        $this->assertTrue(file_exists("{$snap1->layerPath}/version.txt"));
        $this->assertEquals("1.0.0\n", file_get_contents("{$snap1->layerPath}/version.txt"));

        // Upper should now be cleared for new writes
        $this->assertFalse(file_exists("{$storage['upper']}/version.txt"));

        // 3. Write new changes and Create Snapshot 2
        file_put_contents("{$storage['upper']}/version.txt", "2.0.0\n");
        file_put_contents("{$storage['upper']}/feature.txt", "enabled\n");

        $snap2 = $this->snapshotManager->createSnapshot($instanceId, 'v2.0-release', 'Added feature');
        $this->assertNotEmpty($snap2->id);
        $this->assertEquals("2.0.0\n", file_get_contents("{$snap2->layerPath}/version.txt"));

        // 4. List Snapshots
        $snapshots = $this->snapshotManager->listSnapshots($instanceId);
        $this->assertCount(2, $snapshots);
        $snapIds = array_map(fn($s) => $s->id, $snapshots);
        $this->assertContains($snap1->id, $snapIds);
        $this->assertContains($snap2->id, $snapIds);

        // 5. Rollback to Snapshot 1
        $rolledBack = $this->snapshotManager->rollbackSnapshot($instanceId, $snap1->id);
        $this->assertTrue($rolledBack);

        $metaFile = $this->overlayManager->getInstancePath($instanceId) . '/metadata.json';
        $meta = json_decode((string)file_get_contents($metaFile), true);
        $this->assertEquals($snap1->id, $meta['active_snapshot_id']);

        // Rollback to nonexistent snapshot throws SnapshotException
        $this->assertThrows(function () use ($instanceId) {
            $this->snapshotManager->rollbackSnapshot($instanceId, 'nonexistent_snap_999');
        }, SnapshotException::class);

        // 6. Delete Snapshot
        $deleted = $this->snapshotManager->deleteSnapshot($instanceId, $snap2->id);
        $this->assertTrue($deleted);

        $snapshotsAfterDelete = $this->snapshotManager->listSnapshots($instanceId);
        $this->assertCount(1, $snapshotsAfterDelete);
        $this->assertEquals($snap1->id, $snapshotsAfterDelete[0]->id);
    }

    public function testSnapshotMetadataValueObject(): void
    {
        $data = [
            'snapshot_id'   => 'snap_123',
            'instance_id'   => 'inst_1',
            'snapshot_name' => 'initial-backup',
            'description'   => 'test description',
            'layer_path'    => '/var/lib/oshim/snapshots/inst_1/snap_123/layer',
            'size_bytes'    => 4096,
            'created_at'    => 1756479000,
            'layer_stack'   => ['/path/1', '/path/2'],
        ];

        $meta = SnapshotMetadata::fromArray($data);
        $this->assertEquals('snap_123', $meta->id);
        $this->assertEquals('inst_1', $meta->instanceId);
        $this->assertEquals('initial-backup', $meta->name);
        $this->assertEquals(4096, $meta->sizeBytes);
        $this->assertEquals(1756479000, $meta->createdAt);
        $this->assertEquals(['/path/1', '/path/2'], $meta->layerStack);

        $arr = $meta->toArray();
        $this->assertEquals('snap_123', $arr['id']);
        $this->assertEquals('initial-backup', $arr['name']);
    }

    public function testStorageQuotaManagerCalculationsAndBreach(): void
    {
        $instanceId = 'inst_quota_test';
        $storage = $this->overlayManager->prepareInstanceStorage($instanceId, 'ubuntu-24.04-base');

        // Write 10 KB file
        file_put_contents("{$storage['upper']}/data.bin", str_repeat('A', 10240));

        $usedBytes = $this->quotaManager->getContainerDiskUsage($instanceId);
        $this->assertEquals(10240, $usedBytes);

        $limitBytes = 1048576; // 1 MB
        $this->assertTrue($this->quotaManager->checkQuota($instanceId, $limitBytes));

        $stats = $this->quotaManager->getQuotaStats($instanceId, $limitBytes);
        $this->assertEquals(10240, $stats['used_bytes']);
        $this->assertEquals($limitBytes, $stats['limit_bytes']);
        $this->assertEquals(0.98, $stats['usage_pct']);
        $this->assertFalse($stats['is_exceeded']);

        // Quota breach
        $this->assertThrows(function () use ($instanceId) {
            $this->quotaManager->checkQuota($instanceId, 5000); // Limit 5000 bytes, used 10240
        }, StorageException::class);
    }
}
