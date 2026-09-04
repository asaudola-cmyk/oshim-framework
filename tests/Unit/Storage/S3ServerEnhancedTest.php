<?php
declare(strict_types=1);

namespace Tests\Unit\Storage;

use Oshim\Testing\TestCase;
use Oshim\Storage\S3\S3Server;

class S3ServerEnhancedTest extends TestCase
{
    private string $tempDir;

    public function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/oshim_s3_test_' . bin2hex(random_bytes(4));
        S3Server::setStorageDirectory($this->tempDir);
    }

    public function tearDown(): void
    {
        parent::tearDown();
        // Clean up temporary test files
        if (is_dir($this->tempDir)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->tempDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $fileinfo) {
                $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                @$todo($fileinfo->getRealPath());
            }
            @rmdir($this->tempDir);
        }
    }

    public function testS3PutAndGetPersistsToDisk(): void
    {
        $meta = S3Server::putObject('test-bucket', 'docs/readme.txt', 'Sovereign S3 Content');
        $this->assertSame(20, $meta['size']);
        $this->assertStringContainsString('STANDARD_REPLICATED', $meta['storage_class']);

        $filePath = $this->tempDir . '/test-bucket/docs/readme.txt';
        $this->assertTrue(file_exists($filePath));
        $this->assertSame('Sovereign S3 Content', file_get_contents($filePath));

        $obj = S3Server::getObject('test-bucket', 'docs/readme.txt');
        $this->assertNotNull($obj);
        $this->assertSame('Sovereign S3 Content', $obj['content']);
    }

    public function testS3ListObjectsV2XmlGeneration(): void
    {
        S3Server::putObject('media-bucket', 'images/logo.png', 'fake_png_data');
        $xml = S3Server::generateListObjectsV2Xml('media-bucket', 'images/');

        $this->assertStringContainsString('<ListBucketResult', $xml);
        $this->assertStringContainsString('<Name>media-bucket</Name>', $xml);
        $this->assertStringContainsString('<Key>images/logo.png</Key>', $xml);
    }

    public function testS3SigV4Verification(): void
    {
        $dateStr = '20260830T120000Z';
        $secretKey = 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY';
        $canonical = "GET\n/\n\nhost:s3.amazonaws.com\n\nhost\ne3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855";
        
        $kDate = hash_hmac('sha256', '20260830', 'AWS4' . $secretKey, true);
        $kRegion = hash_hmac('sha256', 'us-east-1', $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $stringToSign = "AWS4-HMAC-SHA256\n{$dateStr}\nAKIAIOSFODNN7EXAMPLE/20260830/us-east-1/s3/aws4_request\n" . hash('sha256', $canonical);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authHeader = "AWS4-HMAC-SHA256 Credential=AKIAIOSFODNN7EXAMPLE/20260830/us-east-1/s3/aws4_request, SignedHeaders=host, Signature={$signature}";
        
        $isValid = S3Server::verifySigV4($authHeader, $secretKey, $canonical, $dateStr);
        $this->assertTrue($isValid);
    }
}
