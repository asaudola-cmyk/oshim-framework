<?php
declare(strict_types=1);

namespace Oshim\Storage\Drivers;

/**
 * Pure PHP AWS S3 & Cloudflare R2 / MinIO Storage Driver with SigV4 Signing & REST client.
 */
class S3StorageDriver implements StorageDriverInterface
{
    private string $bucket;
    private string $region;
    private string $accessKey;
    private string $secretKey;
    private string $endpoint;
    private array $localCache = [];

    public function __construct(
        string $bucket = 'default-bucket',
        string $region = 'us-east-1',
        string $accessKey = '',
        string $secretKey = '',
        ?string $endpoint = null
    ) {
        $this->bucket = $bucket;
        $this->region = $region;
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->endpoint = $endpoint ?? "https://{$bucket}.s3.{$region}.amazonaws.com";
    }

    public function put(string $path, string $contents): bool
    {
        $path = ltrim($path, '/');
        $this->localCache[$path] = $contents;

        if (!empty($this->accessKey) && !empty($this->secretKey)) {
            $response = $this->sendSignedRequest('PUT', '/' . $path, $contents, [
                'Content-Type' => 'application/octet-stream',
            ]);
            return $response !== false;
        }

        return true;
    }

    public function get(string $path): ?string
    {
        $path = ltrim($path, '/');

        if (!empty($this->accessKey) && !empty($this->secretKey)) {
            $response = $this->sendSignedRequest('GET', '/' . $path);
            if ($response !== false) {
                return (string)$response;
            }
        }

        return $this->localCache[$path] ?? null;
    }

    public function exists(string $path): bool
    {
        $path = ltrim($path, '/');

        if (!empty($this->accessKey) && !empty($this->secretKey)) {
            $response = $this->sendSignedRequest('HEAD', '/' . $path);
            if ($response !== false) {
                return true;
            }
        }

        return isset($this->localCache[$path]);
    }

    public function delete(string $path): bool
    {
        $path = ltrim($path, '/');
        unset($this->localCache[$path]);

        if (!empty($this->accessKey) && !empty($this->secretKey)) {
            $response = $this->sendSignedRequest('DELETE', '/' . $path);
            return $response !== false;
        }

        return true;
    }

    public function size(string $path): int
    {
        $path = ltrim($path, '/');
        if (isset($this->localCache[$path])) {
            return strlen($this->localCache[$path]);
        }

        $content = $this->get($path);
        return $content !== null ? strlen($content) : 0;
    }

    public function url(string $path): string
    {
        return rtrim($this->endpoint, '/') . '/' . ltrim($path, '/');
    }

    public function presignedUrl(string $path, int $expiresInSeconds = 3600): string
    {
        $date = gmdate('Ymd\THis\Z');
        $expires = $expiresInSeconds;
        $credential = "{$this->accessKey}/" . gmdate('Ymd') . "/{$this->region}/s3/aws4_request";
        
        $params = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $credential,
            'X-Amz-Date' => $date,
            'X-Amz-Expires' => (string)$expires,
            'X-Amz-SignedHeaders' => 'host',
        ];
        
        ksort($params);
        $query = http_build_query($params);
        $canonicalReq = "GET\n/" . ltrim($path, '/') . "\n{$query}\nhost:{$this->bucket}.s3.amazonaws.com\n\nhost\nUNSIGNED-PAYLOAD";
        $stringToSign = "AWS4-HMAC-SHA256\n{$date}\n" . gmdate('Ymd') . "/{$this->region}/s3/aws4_request\n" . hash('sha256', $canonicalReq);

        $kDate = hash_hmac('sha256', gmdate('Ymd'), 'AWS4' . $this->secretKey, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        return $this->url($path) . "?{$query}&X-Amz-Signature={$signature}";
    }

    /**
     * Send SigV4 signed HTTP request to AWS S3 REST API.
     */
    private function sendSignedRequest(string $method, string $path, ?string $body = null, array $headers = []): string|bool
    {
        $date = gmdate('Ymd\THis\Z');
        $shortDate = gmdate('Ymd');
        $payloadHash = hash('sha256', $body ?? '');
        $host = parse_url($this->endpoint, PHP_URL_HOST) ?? "{$this->bucket}.s3.{$this->region}.amazonaws.com";

        $defaultHeaders = [
            'Host' => $host,
            'x-amz-date' => $date,
            'x-amz-content-sha256' => $payloadHash,
        ];
        $allHeaders = array_merge($defaultHeaders, $headers);

        // Canonical headers
        $canonicalHeaders = '';
        $signedHeaderNames = [];
        $sortedKeys = array_keys($allHeaders);
        natcasesort($sortedKeys);

        foreach ($sortedKeys as $key) {
            $lowerKey = strtolower((string)$key);
            $canonicalHeaders .= $lowerKey . ':' . trim((string)$allHeaders[$key]) . "\n";
            $signedHeaderNames[] = $lowerKey;
        }
        $signedHeadersStr = implode(';', $signedHeaderNames);

        $canonicalRequest = "{$method}\n{$path}\n\n{$canonicalHeaders}\n{$signedHeadersStr}\n{$payloadHash}";
        $credentialScope = "{$shortDate}/{$this->region}/s3/aws4_request";
        $stringToSign = "AWS4-HMAC-SHA256\n{$date}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        $kDate = hash_hmac('sha256', $shortDate, 'AWS4' . $this->secretKey, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authHeader = "AWS4-HMAC-SHA256 Credential={$this->accessKey}/{$credentialScope}, SignedHeaders={$signedHeadersStr}, Signature={$signature}";
        $allHeaders['Authorization'] = $authHeader;

        $headerLines = [];
        foreach ($allHeaders as $k => $v) {
            $headerLines[] = "{$k}: {$v}";
        }

        $url = rtrim($this->endpoint, '/') . $path;
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headerLines) . "\r\n",
                'content' => $body,
                'timeout' => 5,
                'ignore_errors' => true,
            ]
        ]);

        $res = @file_get_contents($url, false, $context);
        return $res;
    }
}

