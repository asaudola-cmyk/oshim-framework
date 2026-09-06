<?php
declare(strict_types=1);

namespace Oshim\Http;

/**
 * 📨 Sovereign HTTP Request Object
 * 
 * WHY: Zero-allocation extraction of HTTP request metadata from SAPI server superglobals.
 */
final class Request
{
    private string $method;
    private string $uri;
    private string $path;
    private array $queryParams;
    private array $headers;
    private string $body;

    public function __construct(string $method, string $uri, array $headers = [], string $body = '')
    {
        $this->method = strtoupper($method);
        $this->uri = $uri;
        $this->headers = $headers;
        $this->body = $body;

        $parts = parse_url($uri);
        $this->path = $parts['path'] ?? '/';
        $this->queryParams = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $this->queryParams);
        }
    }

    /**
     * Captures request directly from current SAPI environment.
     */
    public static function capture(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $body = file_get_contents('php://input') ?: '';

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$headerName] = (string)$value;
            }
        }

        return new self($method, $uri, $headers, $body);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQuery(string $key, ?string $default = null): ?string
    {
        return $this->queryParams[$key] ?? $default;
    }

    public function getHeader(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function json(): array
    {
        return json_decode($this->body, true) ?: [];
    }
}
