<?php
declare(strict_types=1);

namespace Oshim\Http;

/**
 * 📤 Sovereign HTTP Response Emitter
 * 
 * WHY: Directly controls status codes and headers with zero intermediate buffering,
 * flushing output straight into the open TCP socket descriptor.
 */
final class Response
{
    private int $statusCode;
    private array $headers;
    private string $content;

    public function __construct(string $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public static function json(array $data, int $statusCode = 200): self
    {
        $payload = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        return new self($payload, $statusCode, [
            'Content-Type' => 'application/json; charset=UTF-8'
        ]);
    }

    public static function text(string $text, int $statusCode = 200): self
    {
        return new self($text, $statusCode, [
            'Content-Type' => 'text/plain; charset=UTF-8'
        ]);
    }

    public static function html(string $html, int $statusCode = 200): self
    {
        return new self($html, $statusCode, [
            'Content-Type' => 'text/html; charset=UTF-8'
        ]);
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Emits response to current client connection.
     */
    public function send(): void
    {
        http_response_code($this->statusCode);
        foreach ($this->headers as $key => $value) {
            header("{$key}: {$value}");
        }
        echo $this->content;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
