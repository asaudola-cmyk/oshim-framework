<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Storage\Drivers\LocalStorageDriver;
use Oshim\Storage\Drivers\S3StorageDriver;
use Oshim\Storage\StorageManager;
use Oshim\Storage\Storage;

final class StorageDriverTest extends TestCase
{
    public function testLocalStorageDriver(): void
    {
        $driver = new LocalStorageDriver();
        $testPath = 'unit_tests/hello.txt';

        $driver->put($testPath, 'Hello Sovereign Storage');
        $this->assertTrue($driver->exists($testPath));
        $this->assertSame('Hello Sovereign Storage', $driver->get($testPath));
        $this->assertTrue($driver->size($testPath) > 0);
        $this->assertStringContainsString('hello.txt', $driver->url($testPath));

        $driver->delete($testPath);
        $this->assertFalse($driver->exists($testPath));
    }

    public function testS3StoragePresignedUrl(): void
    {
        $s3 = new S3StorageDriver('my-cloud-bucket', 'ap-southeast-1', 'AKIA_TEST_KEY', 'SECRET_KEY_123');
        $presigned = $s3->presignedUrl('backups/db.tar.gz', 1800);

        $this->assertStringContainsString('X-Amz-Algorithm=AWS4-HMAC-SHA256', $presigned);
        $this->assertStringContainsString('X-Amz-Credential=', $presigned);
        $this->assertStringContainsString('X-Amz-Signature=', $presigned);
        $this->assertStringContainsString('backups/db.tar.gz', $presigned);
    }
}
