<?php
declare(strict_types=1);

namespace Oshim\Http;

use Oshim\Http\Cookie\Cookie;
use Closure;

/**
 * Rich HTTP Response builder and emitter.
 */
class Response
{
    protected int $statusCode = 200;
    protected string $statusText = 'OK';
    protected string $content = '';
    protected HeaderMap $headers;
    /** @var array<string, Cookie> */
    protected array $cookies = [];
    protected ?Closure $streamCallback = null;
    protected bool $sent = false;

    /** Standard HTTP Status Codes */
    public const HTTP_OK = 200;
    public const HTTP_CREATED = 201;
    public const HTTP_ACCEPTED = 202;
    public const HTTP_NO_CONTENT = 204;
    public const HTTP_MOVED_PERMANENTLY = 301;
    public const HTTP_FOUND = 302;
    public const HTTP_SEE_OTHER = 303;
    public const HTTP_NOT_MODIFIED = 304;
    public const HTTP_BAD_REQUEST = 400;
    public const HTTP_UNAUTHORIZED = 401;
    public const HTTP_FORBIDDEN = 403;
    public const HTTP_NOT_FOUND = 404;
    public const HTTP_METHOD_NOT_ALLOWED = 405;
    public const HTTP_CONFLICT = 409;
    public const HTTP_PAGE_EXPIRED = 419;
    public const HTTP_UNPROCESSABLE_ENTITY = 422;
    public const HTTP_TOO_MANY_REQUESTS = 429;
    public const HTTP_INTERNAL_SERVER_ERROR = 500;
    public const HTTP_SERVICE_UNAVAILABLE = 503;

    protected static array $statusTexts = [
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        409 => 'Conflict',
        419 => 'Page Expired',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        503 => 'Service Unavailable',
    ];

    public function __construct(string $content = '', int $status = 200, array|HeaderMap $headers = [])
    {
        $this->content = $content;
        $this->setStatusCode($status);
        $this->headers = $headers instanceof HeaderMap ? $headers : new HeaderMap($headers);
    }

    // --- Static Builders ---
    public static function make(string $content = '', int $status = 200, array $headers = []): static
    {
        return new static($content, $status, $headers);
    }

    public static function html(string $html, int $status = 200, array $headers = []): static
    {
        $response = new static($html, $status, $headers);
        $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
        return $response;
    }

    public static function json(mixed $data, int $status = 200, array $headers = [], int $options = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE): static
    {
        $json = json_encode($data, $options);
        $response = new static($json !== false ? $json : '{}', $status, $headers);
        $response->withHeader('Content-Type', 'application/json; charset=UTF-8');
        return $response;
    }

    public static function redirect(string $url, int $status = 302, array $headers = []): static
    {
        $response = new static('', $status, $headers);
        $response->withHeader('Location', $url);
        return $response;
    }

    public static function download(string $filePath, ?string $filename = null, array $headers = []): static
    {
        if (!is_file($filePath)) {
            return static::make('File not found', 404);
        }

        $targetName = $filename ?? basename($filePath);
        $content = (string)file_get_contents($filePath);
        $mime = mime_content_type($filePath) ?: 'application/octet-stream';

        $response = new static($content, 200, $headers);
        $response->withHeader('Content-Type', $mime);
        $response->withHeader('Content-Disposition', 'attachment; filename="' . str_replace('"', '', $targetName) . '"');
        $response->withHeader('Content-Length', (string)strlen($content));

        return $response;
    }

    public static function sse(callable $generator, array $headers = []): static
    {
        $response = new static('', 200, $headers);
        $response->withHeader('Content-Type', 'text/event-stream; charset=UTF-8');
        $response->withHeader('Cache-Control', 'no-cache');
        $response->withHeader('Connection', 'keep-alive');
        $response->withHeader('X-Accel-Buffering', 'no');
        $response->streamCallback = Closure::fromCallable($generator);
        return $response;
    }

    public static function stream(callable $callback, int $status = 200, array $headers = []): static
    {
        $response = new static('', $status, $headers);
        $response->streamCallback = Closure::fromCallable($callback);
        return $response;
    }

    public static function noContent(int $status = 204, array $headers = []): static
    {
        return new static('', $status, $headers);
    }

    // --- Fluent Modifiers ---
    public function setStatusCode(int $code, ?string $text = null): static
    {
        $this->statusCode = $code;
        $this->statusText = $text ?? self::$statusTexts[$code] ?? 'Unknown Status';
        return $this;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function withHeader(string $name, string $value): static
    {
        $this->headers->set($name, $value);
        return $this;
    }

    public function withHeaders(array $headers): static
    {
        foreach ($headers as $k => $v) {
            $this->headers->set((string)$k, $v);
        }
        return $this;
    }

    public function withCookie(Cookie $cookie): static
    {
        $this->cookies[$cookie->getName()] = $cookie;
        return $this;
    }

    public function withoutCookie(string $name, string $path = '/', string $domain = ''): static
    {
        $this->cookies[$name] = Cookie::forget($name, $path, $domain);
        return $this;
    }

    // --- Inspection ---
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function getStatusText(): string
    {
        return $this->statusText;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function body(): string
    {
        return $this->content;
    }

    public function getHeaders(): HeaderMap
    {
        return $this->headers;
    }

    public function getHeader(string $name, mixed $default = null): mixed
    {
        return $this->headers->get($name, $default);
    }

    public function hasHeader(string $name): bool
    {
        return $this->headers->has($name);
    }

    public function getCookies(): array
    {
        return $this->cookies;
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function isRedirect(): bool
    {
        return in_array($this->statusCode, [301, 302, 303, 307, 308], true);
    }

    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    public function isServerError(): bool
    {
        return $this->statusCode >= 500 && $this->statusCode < 600;
    }

    // --- Emission ---
    public function send(): void
    {
        if ($this->sent) {
            return;
        }

        if (!headers_sent()) {
            http_response_code($this->statusCode);

            // Send headers
            foreach ($this->headers->all() as $name => $values) {
                foreach ($values as $value) {
                    header("{$name}: {$value}", false, $this->statusCode);
                }
            }

            // Send cookies
            foreach ($this->cookies as $cookie) {
                header('Set-Cookie: ' . $cookie->toHeaderString(), false);
            }
        }

        if ($this->streamCallback !== null) {
            ($this->streamCallback)();
        } else {
            echo $this->content;
        }

        $this->sent = true;
    }
}
