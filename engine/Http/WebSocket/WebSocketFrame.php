<?php
declare(strict_types=1);

namespace Oshim\Http\WebSocket;

use InvalidArgumentException;

/**
 * RFC 6455 WebSocket Frame Encoder & Decoder.
 */
final class WebSocketFrame
{
    public const OPCODE_CONTINUATION = 0x0;
    public const OPCODE_TEXT         = 0x1;
    public const OPCODE_BINARY       = 0x2;
    public const OPCODE_CLOSE        = 0x8;
    public const OPCODE_PING         = 0x9;
    public const OPCODE_PONG         = 0xA;

    private int $opcode;
    private string $payload;
    private bool $fin;
    private bool $masked;
    private ?string $maskingKey;

    public function __construct(
        int $opcode = self::OPCODE_TEXT,
        string $payload = '',
        bool $fin = true,
        bool $masked = false,
        ?string $maskingKey = null
    ) {
        $this->opcode = $opcode;
        $this->payload = $payload;
        $this->fin = $fin;
        $this->masked = $masked;
        $this->maskingKey = $maskingKey;
    }

    public function getOpcode(): int { return $this->opcode; }
    public function getPayload(): string { return $this->payload; }
    public function isFin(): bool { return $this->fin; }
    public function isMasked(): bool { return $this->masked; }
    public function getMaskingKey(): ?string { return $this->maskingKey; }

    public function isText(): bool { return $this->opcode === self::OPCODE_TEXT; }
    public function isBinary(): bool { return $this->opcode === self::OPCODE_BINARY; }
    public function isClose(): bool { return $this->opcode === self::OPCODE_CLOSE; }
    public function isPing(): bool { return $this->opcode === self::OPCODE_PING; }
    public function isPong(): bool { return $this->opcode === self::OPCODE_PONG; }

    /**
     * Encode frame into RFC 6455 binary format.
     */
    public function encode(bool $maskPayload = false): string
    {
        $payloadLength = strlen($this->payload);
        $firstByte = ($this->fin ? 0x80 : 0x00) | ($this->opcode & 0x0F);
        $maskBit = $maskPayload ? 0x80 : 0x00;

        $header = '';
        if ($payloadLength <= 125) {
            $header = chr($firstByte) . chr($maskBit | $payloadLength);
        } elseif ($payloadLength <= 65535) {
            $header = chr($firstByte) . chr($maskBit | 126) . pack('n', $payloadLength);
        } else {
            $header = chr($firstByte) . chr($maskBit | 127) . pack('J', $payloadLength);
        }

        if ($maskPayload) {
            $maskKey = random_bytes(4);
            $maskedPayload = self::applyMask($this->payload, $maskKey);
            return $header . $maskKey . $maskedPayload;
        }

        return $header . $this->payload;
    }

    /**
     * Decode a binary buffer into a WebSocketFrame.
     * Returns array [WebSocketFrame|null, int $bytesConsumed].
     */
    public static function decode(string $buffer): array
    {
        $bufferLength = strlen($buffer);
        if ($bufferLength < 2) {
            return [null, 0];
        }

        $byte1 = ord($buffer[0]);
        $byte2 = ord($buffer[1]);

        $fin = (bool)($byte1 & 0x80);
        $opcode = $byte1 & 0x0F;
        $isMasked = (bool)($byte2 & 0x80);
        $payloadLength = $byte2 & 0x7F;

        $offset = 2;

        if ($payloadLength === 126) {
            if ($bufferLength < $offset + 2) {
                return [null, 0];
            }
            $payloadLength = unpack('n', substr($buffer, $offset, 2))[1];
            $offset += 2;
        } elseif ($payloadLength === 127) {
            if ($bufferLength < $offset + 8) {
                return [null, 0];
            }
            $payloadLength = unpack('J', substr($buffer, $offset, 8))[1];
            $offset += 8;
        }

        $maskKey = null;
        if ($isMasked) {
            if ($bufferLength < $offset + 4) {
                return [null, 0];
            }
            $maskKey = substr($buffer, $offset, 4);
            $offset += 4;
        }

        if ($bufferLength < $offset + $payloadLength) {
            return [null, 0];
        }

        $payload = substr($buffer, $offset, $payloadLength);
        if ($isMasked && $maskKey !== null) {
            $payload = self::applyMask($payload, $maskKey);
        }

        $frame = new self($opcode, $payload, $fin, $isMasked, $maskKey);
        return [$frame, $offset + $payloadLength];
    }

    /**
     * XOR mask/unmask payload with 4-byte masking key.
     */
    public static function applyMask(string $data, string $key): string
    {
        $len = strlen($data);
        $result = '';
        for ($i = 0; $i < $len; $i++) {
            $result .= chr(ord($data[$i]) ^ ord($key[$i % 4]));
        }
        return $result;
    }

    public static function text(string $text): self
    {
        return new self(self::OPCODE_TEXT, $text, true);
    }

    public static function binary(string $data): self
    {
        return new self(self::OPCODE_BINARY, $data, true);
    }

    public static function ping(string $data = ''): self
    {
        return new self(self::OPCODE_PING, $data, true);
    }

    public static function pong(string $data = ''): self
    {
        return new self(self::OPCODE_PONG, $data, true);
    }

    public static function close(int $code = 1000, string $reason = ''): self
    {
        $payload = pack('n', $code) . $reason;
        return new self(self::OPCODE_CLOSE, $payload, true);
    }
}
