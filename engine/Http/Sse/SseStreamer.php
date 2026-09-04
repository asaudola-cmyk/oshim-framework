<?php
declare(strict_types=1);

namespace Oshim\Http\Sse;

use Closure;

/**
 * Server-Sent Events (SSE) Streamer for AI token streaming, live metrics, and real-time pushes.
 */
class SseStreamer
{
    private array $headers = [
        'Content-Type' => 'text/event-stream',
        'Cache-Control' => 'no-cache',
        'Connection' => 'keep-alive',
        'X-Accel-Buffering' => 'no',
    ];

    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Format an SSE data packet.
     */
    public static function formatEvent(
        string|array $data,
        ?string $event = null,
        ?string $id = null,
        ?int $retry = null
    ): string {
        $output = '';

        if ($id !== null) {
            $output .= "id: {$id}\n";
        }

        if ($event !== null) {
            $output .= "event: {$event}\n";
        }

        if ($retry !== null) {
            $output .= "retry: {$retry}\n";
        }

        $payload = is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $data;
        $lines = explode("\n", (string)$payload);
        foreach ($lines as $line) {
            $output .= "data: {$line}\n";
        }

        $output .= "\n";
        return $output;
    }

    /**
     * Format a token chunk specifically for streaming LLM responses.
     */
    public static function formatToken(string $token, bool $isFinal = false): string
    {
        return self::formatEvent([
            'token' => $token,
            'done' => $isFinal,
            'timestamp' => microtime(true)
        ], 'token');
    }

    /**
     * Format a comment line (keeps connection alive).
     */
    public static function formatComment(string $comment = 'ping'): string
    {
        return ": {$comment}\n\n";
    }

    /**
     * Stream an array/generator of tokens directly via a callback or output buffer.
     */
    public static function streamTokens(iterable $tokens, ?callable $writer = null): void
    {
        $write = $writer ?? function (string $chunk) {
            echo $chunk;
            if (function_exists('ob_flush')) {
                @ob_flush();
            }
            flush();
        };

        foreach ($tokens as $token) {
            $write(self::formatToken((string)$token, false));
        }

        $write(self::formatToken('', true));
    }
}
