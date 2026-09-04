<?php
declare(strict_types=1);

namespace Tests\Unit\Kernel;

use Oshim\Testing\TestCase;
use Oshim\Kernel\UniversalKernel;
use Oshim\Kernel\Drivers\GenericPortableDriver;
use Oshim\Kernel\Drivers\LinuxKernelDriver;
use Oshim\Kernel\Drivers\DarwinKernelDriver;
use Oshim\Kernel\Drivers\BsdKernelDriver;
use Oshim\Kernel\Drivers\WindowsKernelDriver;
use Oshim\Virtualization\MicroVmManager;
use Oshim\Virtualization\LiveMigrationManager;
use Oshim\Security\Ssl\CertificateManager;
use Oshim\Storage\S3\S3Server;
use Oshim\Security\AntiDdos\XdpFilter;
use Oshim\Security\AntiDdos\RateLimiterShield;
use Oshim\Dns\GeoRouting\GeoRouter;
use Oshim\Async\IoUringEngine;
use Oshim\Async\KqueueEngine;
use Oshim\Async\IocpEngine;
use Oshim\Async\SharedMemoryCache;

class UniversalKernelTest extends TestCase
{
    public function testUniversalKernelDetectionAndDrivers(): void
    {
        $info = UniversalKernel::info();
        $this->assertIsArray($info);
        $this->assertArrayHasKey('os_family', $info);
        $this->assertArrayHasKey('driver', $info);

        // Test switching to Generic driver
        $generic = new GenericPortableDriver();
        UniversalKernel::setDriver($generic);
        $this->assertSame($generic, UniversalKernel::getDriver());
        $this->assertTrue($generic->isAvailable());

        $container = $generic->createMicroContainer('c-01', ['cpu' => 2, 'memory' => '1GB']);
        $this->assertSame('success', $container['status']);
        $this->assertTrue($generic->stopMicroContainer('c-01'));

        // Reset
        UniversalKernel::resetDriver();
    }

    public function testAllPlatformDriversContract(): void
    {
        $drivers = [
            new GenericPortableDriver(),
            new LinuxKernelDriver(),
            new DarwinKernelDriver(),
            new BsdKernelDriver(),
            new WindowsKernelDriver(),
        ];

        foreach ($drivers as $d) {
            $this->assertNotEmpty($d->getDriverName());
            $this->assertNotEmpty($d->getSupportedOs());
            $res = $d->createMicroContainer('test-vm', ['cpu' => 1]);
            $this->assertSame('success', $res['status']);
            $this->assertTrue($d->stopMicroContainer('test-vm'));
            $this->assertIsArray($d->getSystemMetrics());
            $this->assertTrue($d->filterPacket('1.2.3.4', 80, 'TCP'));
        }
    }

    public function testMicroVmInstantSpawnerAndLiveMigration(): void
    {
        $res = MicroVmManager::spawn('apex-server', [
            'cpu' => 4,
            'ram_mb' => 4096,
            'disk_gb' => 80,
        ]);

        $this->assertSame('spawned', $res['status']);
        $vm = $res['vm'];
        $this->assertTrue($vm['boot_time_ms'] < 50.0);
        $this->assertSame('RUNNING', $vm['state']);

        $fetched = MicroVmManager::get($vm['id']);
        $this->assertNotNull($fetched);
        $this->assertSame($vm['name'], $fetched['name']);

        // Test Zero-Downtime Live Migration
        $mig = LiveMigrationManager::migrate($vm['id'], 'node-01', 'node-02');
        $this->assertSame('MIGRATED', $mig['status']);
        $this->assertTrue($mig['zero_downtime']);
        $this->assertTrue($mig['downtime_ms'] < 10.0);

        $this->assertTrue(MicroVmManager::stop($vm['id']));
    }

    public function testPurePhpAcmeV2SslCertificateIssuance(): void
    {
        $cert = CertificateManager::issue('clientbrand.com', 'admin@clientbrand.com', ['www.clientbrand.com']);
        $this->assertSame('valid', $cert['status']);
        $this->assertSame('clientbrand.com', $cert['domain']);
        $this->assertStringContainsString('BEGIN CERTIFICATE', $cert['certificate_pem']);
        $this->assertStringContainsString('PRIVATE KEY', $cert['private_key_pem']);

        $retrieved = CertificateManager::get('clientbrand.com');
        $this->assertNotNull($retrieved);
        $this->assertFalse(CertificateManager::isExpiringSoon('clientbrand.com'));
    }

    public function testS3CompatibleDistributedStorage(): void
    {
        $put = S3Server::putObject('oshim-backups', 'database.sql', 'SELECT * FROM users;', 'application/sql');
        $this->assertSame('database.sql', $put['key']);
        $this->assertSame('STANDARD_REPLICATED', $put['storage_class']);

        $get = S3Server::getObject('oshim-backups', 'database.sql');
        $this->assertNotNull($get);
        $this->assertSame('SELECT * FROM users;', $get['content']);

        $list = S3Server::listObjectsV2('oshim-backups');
        $this->assertSame('oshim-backups', $list['name']);
        $this->assertTrue($list['key_count'] >= 1);

        $this->assertTrue(S3Server::deleteObject('oshim-backups', 'database.sql'));
        $this->assertNull(S3Server::getObject('oshim-backups', 'database.sql'));
    }

    public function testXdpAntiDdosAndRateLimiterShield(): void
    {
        XdpFilter::clearStats();
        $this->assertTrue(XdpFilter::isAllowed('192.168.1.50', 80, 'TCP'));

        XdpFilter::blockIp('192.168.1.50', 'syn_flood');
        $this->assertFalse(XdpFilter::isAllowed('192.168.1.50', 80, 'TCP'));

        $stats = XdpFilter::getStats();
        $this->assertSame(1, $stats['syn_flood_dropped']);
        $this->assertSame(1, $stats['active_blacklist_count']);

        // Rate Limiter Shield
        RateLimiterShield::flush();
        $this->assertTrue(RateLimiterShield::check('api-key-test', 5, 60));
        for ($i = 0; $i < 4; $i++) {
            RateLimiterShield::check('api-key-test', 5, 60);
        }
        $this->assertFalse(RateLimiterShield::check('api-key-test', 5, 60)); // Exceeded
    }

    public function testGeoDnsRouting(): void
    {
        $routeBd = GeoRouter::resolveOptimalIp('103.152.112.5');
        $this->assertSame('BD', $routeBd['routed_region']);
        $this->assertSame('103.152.112.99', $routeBd['optimal_ip']);

        $routeUs = GeoRouter::resolveOptimalIp('104.244.72.1');
        $this->assertSame('NA', $routeUs['routed_region']);
        $this->assertSame('104.244.72.10', $routeUs['optimal_ip']);
    }

    public function testAsyncEnginesAndSharedMemoryCache(): void
    {
        $ioUring = new IoUringEngine(64);
        $this->assertIsArray($ioUring->getStats());

        $kqueue = new KqueueEngine();
        $this->assertIsArray($kqueue->getStats());

        $iocp = new IocpEngine();
        $this->assertIsArray($iocp->getStats());

        SharedMemoryCache::init();
        SharedMemoryCache::set('cache_key_1', ['status' => 'OK'], 100);
        $this->assertSame(['status' => 'OK'], SharedMemoryCache::get('cache_key_1'));
        $this->assertTrue(SharedMemoryCache::has('cache_key_1'));
        $this->assertTrue(SharedMemoryCache::delete('cache_key_1'));
        $this->assertNull(SharedMemoryCache::get('cache_key_1'));
    }
}
