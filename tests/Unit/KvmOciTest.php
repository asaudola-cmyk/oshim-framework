<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Virtualization\Kvm\KvmHardwareDriver;
use Oshim\Virtualization\Oci\OciImageManager;
use Oshim\Virtualization\CloudInit\CloudInitService;

final class KvmOciTest extends TestCase
{
    public function testKvmHardwareDriverMicroVmCreation(): void
    {
        $driver = new KvmHardwareDriver();
        $result = $driver->createMicroVm('microvm-alpha-1', 4, 4096);

        $this->assertSame('SUCCESS', $result['status']);
        $this->assertSame('microvm-alpha-1', $result['vm']['vm_id']);
        $this->assertSame(4, $result['vm']['vcpus']);
        $this->assertSame(4096, $result['vm']['memory_mb']);

        $this->assertTrue($driver->stopMicroVm('microvm-alpha-1'));
    }

    public function testOciManifestParserAndOverlayLowerDir(): void
    {
        $manifestJson = json_encode([
            'schemaVersion' => 2,
            'mediaType' => 'application/vnd.oci.image.manifest.v1+json',
            'config' => ['digest' => 'sha256:config123', 'size' => 1400],
            'layers' => [
                ['digest' => 'sha256:layer1base'],
                ['digest' => 'sha256:layer2app'],
            ]
        ]);

        $parsed = OciImageManager::parseManifest($manifestJson);
        $this->assertSame(2, $parsed['layer_count']);

        $lowerDir = OciImageManager::computeOverlayLowerDir($parsed['layers'], '/var/lib/oshim/layers');
        $this->assertSame(
            '/var/lib/oshim/layers/layer2app/diff:/var/lib/oshim/layers/layer1base/diff',
            $lowerDir
        );
    }

    public function testCloudInitUserDataAndMetadata(): void
    {
        $userData = CloudInitService::generateUserData(
            'node-dhaka-01',
            'admin',
            'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAI...',
            ['nginx', 'curl'],
            ['systemctl start nginx']
        );

        $this->assertStringStartsWith('#cloud-config', $userData);
        $this->assertStringContainsString('hostname: node-dhaka-01', $userData);
        $this->assertStringContainsString('name: admin', $userData);
        $this->assertStringContainsString('ssh-ed25519', $userData);

        $meta = CloudInitService::generateMetaData('vm-uuid-1', 'node-dhaka-01');
        $this->assertSame('vm-uuid-1', $meta['instance-id']);
        $this->assertSame('node-dhaka-01', $meta['local-hostname']);
    }
}
