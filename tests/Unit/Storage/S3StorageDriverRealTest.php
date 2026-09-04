<?php
declare(strict_types=1);

namespace Tests\Unit\Storage;

use Oshim\Testing\TestCase;
use Oshim\Storage\Drivers\S3StorageDriver;

class S3StorageDriverRealTest extends TestCase
{
    public function testS3StorageDriverPutGetDelete(): void
    {
        $driver = new S3StorageDriver('test-bucket', 'us-east-1');
        
        $this->assertTrue($driver->put('uploads/test.txt', 'Hello S3 Cloud'));
        $this->assertTrue($driver->exists('uploads/test.txt'));
        $this->assertSame('Hello S3 Cloud', $driver->get('uploads/test.txt'));
        $this->assertSame(14, $driver->size('uploads/test.txt'));
        
        $presigned = $driver->presignedUrl('uploads/test.txt', 3600);
        $this->assertStringContainsString('X-Amz-Signature=', $presigned);
        $this->assertStringContainsString('X-Amz-Algorithm=AWS4-HMAC-SHA256', $presigned);

        $this->assertTrue($driver->delete('uploads/test.txt'));
        $this->assertFalse($driver->exists('uploads/test.txt'));
    }
}
