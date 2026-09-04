<?php
declare(strict_types=1);

namespace Oshim\Storage\S3;

class S3Server
{
    private static array $buckets = ['oshim-backups' => [], 'oshim-media' => []];
    private static array $metadata = [];
    private static ?string $storageDir = null;

    public static function setStorageDirectory(string $path): void
    {
        self::$storageDir = rtrim($path, '/');
        if (!is_dir(self::$storageDir)) {
            @mkdir(self::$storageDir, 0755, true);
        }
    }

    public static function getStorageDirectory(): string
    {
        if (self::$storageDir === null) {
            self::$storageDir = dirname(__DIR__, 3) . '/storage/s3';
            if (!is_dir(self::$storageDir)) {
                @mkdir(self::$storageDir, 0755, true);
            }
        }
        return self::$storageDir;
    }

    public static function createBucket(string $bucketName): bool
    {
        $dir = self::getStorageDirectory() . '/' . $bucketName;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        if (!isset(self::$buckets[$bucketName])) {
            self::$buckets[$bucketName] = [];
            return true;
        }
        return false;
    }

    public static function putObject(string $bucket, string $key, string $content, string $contentType = 'application/octet-stream'): array
    {
        if (!isset(self::$buckets[$bucket])) {
            self::createBucket($bucket);
        }

        $etag = md5($content);
        $size = strlen($content);
        $sha256 = hash('sha256', $content);

        // Persistent Disk Storage
        $filePath = self::getStorageDirectory() . '/' . $bucket . '/' . $key;
        $fileDir = dirname($filePath);
        if (!is_dir($fileDir)) {
            @mkdir($fileDir, 0755, true);
        }
        @file_put_contents($filePath, $content);

        self::$buckets[$bucket][$key] = $content;
        self::$metadata[$bucket][$key] = [
            'key' => $key,
            'size' => $size,
            'etag' => '"' . $etag . '"',
            'content_type' => $contentType,
            'sha256' => $sha256,
            'last_modified' => gmdate('D, d M Y H:i:s T'),
            'storage_class' => 'STANDARD_REPLICATED',
        ];

        // Trigger 3-way cluster replication
        ReplicationManager::replicate($bucket, $key, $size);

        return self::$metadata[$bucket][$key];
    }

    public static function getObject(string $bucket, string $key): ?array
    {
        if (isset(self::$buckets[$bucket][$key])) {
            return [
                'content' => self::$buckets[$bucket][$key],
                'metadata' => self::$metadata[$bucket][$key] ?? [],
            ];
        }

        $filePath = self::getStorageDirectory() . '/' . $bucket . '/' . $key;
        if (file_exists($filePath)) {
            $content = (string)@file_get_contents($filePath);
            $size = strlen($content);
            $etag = md5($content);
            $meta = [
                'key' => $key,
                'size' => $size,
                'etag' => '"' . $etag . '"',
                'content_type' => 'application/octet-stream',
                'sha256' => hash('sha256', $content),
                'last_modified' => gmdate('D, d M Y H:i:s T', filemtime($filePath)),
                'storage_class' => 'STANDARD_REPLICATED',
            ];
            self::$buckets[$bucket][$key] = $content;
            self::$metadata[$bucket][$key] = $meta;

            return [
                'content' => $content,
                'metadata' => $meta,
            ];
        }

        return null;
    }

    public static function listObjectsV2(string $bucket, string $prefix = ''): array
    {
        $objects = [];
        if (isset(self::$metadata[$bucket])) {
            foreach (self::$metadata[$bucket] as $key => $meta) {
                if ($prefix === '' || str_starts_with($key, $prefix)) {
                    $objects[] = $meta;
                }
            }
        }
        return [
            'name' => $bucket,
            'prefix' => $prefix,
            'key_count' => count($objects),
            'contents' => $objects,
        ];
    }

    public static function deleteObject(string $bucket, string $key): bool
    {
        $filePath = self::getStorageDirectory() . '/' . $bucket . '/' . $key;
        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        if (isset(self::$buckets[$bucket][$key])) {
            unset(self::$buckets[$bucket][$key]);
            unset(self::$metadata[$bucket][$key]);
            return true;
        }
        return false;
    }

    public static function listBuckets(): array
    {
        return array_keys(self::$buckets);
    }

    /**
     * Generate standard S3 XML representation for ListObjectsV2 response.
     */
    public static function generateListObjectsV2Xml(string $bucket, string $prefix = ''): string
    {
        $list = self::listObjectsV2($bucket, $prefix);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<ListBucketResult xmlns="http://s3.amazonaws.com/doc/2006-03-01/">' . "\n";
        $xml .= '  <Name>' . htmlspecialchars($bucket) . '</Name>' . "\n";
        $xml .= '  <Prefix>' . htmlspecialchars($prefix) . '</Prefix>' . "\n";
        $xml .= '  <KeyCount>' . $list['key_count'] . '</KeyCount>' . "\n";
        $xml .= '  <MaxKeys>1000</MaxKeys>' . "\n";
        $xml .= '  <IsTruncated>false</IsTruncated>' . "\n";

        foreach ($list['contents'] as $obj) {
            $xml .= '  <Contents>' . "\n";
            $xml .= '    <Key>' . htmlspecialchars($obj['key']) . '</Key>' . "\n";
            $xml .= '    <LastModified>' . $obj['last_modified'] . '</LastModified>' . "\n";
            $xml .= '    <ETag>' . $obj['etag'] . '</ETag>' . "\n";
            $xml .= '    <Size>' . $obj['size'] . '</Size>' . "\n";
            $xml .= '    <StorageClass>' . $obj['storage_class'] . '</StorageClass>' . "\n";
            $xml .= '  </Contents>' . "\n";
        }

        $xml .= '</ListBucketResult>';
        return $xml;
    }

    /**
     * Verify AWS SigV4 authorization signature.
     */
    public static function verifySigV4(string $authHeader, string $secretKey, string $canonicalRequest, string $dateStr): bool
    {
        if (!preg_match('/AWS4-HMAC-SHA256\s+Credential=([^,]+),\s*SignedHeaders=([^,]+),\s*Signature=([a-f0-9]+)/i', $authHeader, $m)) {
            return false;
        }

        $credential = $m[1];
        $signature = $m[3];
        $credParts = explode('/', $credential);
        if (count($credParts) < 4) {
            return false;
        }

        $date = $credParts[1];
        $region = $credParts[2];
        $service = $credParts[3];

        $kDate = hash_hmac('sha256', $date, 'AWS4' . $secretKey, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

        $stringToSign = "AWS4-HMAC-SHA256\n{$dateStr}\n{$credential}\n" . hash('sha256', $canonicalRequest);
        $calculatedSignature = hash_hmac('sha256', $stringToSign, $kSigning);

        return hash_equals($calculatedSignature, $signature);
    }
}

