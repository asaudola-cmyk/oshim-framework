<?php
declare(strict_types=1);

namespace Oshim\Swarm;

use InvalidArgumentException;

class SwarmProtocol
{
    public const MAGIC = "OSHM_SWRM_V1\n";

    public const TYPE_HANDSHAKE     = 'HANDSHAKE';
    public const TYPE_JOIN          = 'JOIN';
    public const TYPE_JOIN_ACK      = 'JOIN_ACK';
    public const TYPE_HEARTBEAT     = 'HEARTBEAT';
    public const TYPE_HEARTBEAT_ACK = 'HEARTBEAT_ACK';
    public const TYPE_STATE_SYNC    = 'STATE_SYNC';
    public const TYPE_TASK_DISPATCH = 'TASK_DISPATCH';
    public const TYPE_TASK_RESULT   = 'TASK_RESULT';
    public const TYPE_LEAVE         = 'LEAVE';
    public const TYPE_ELECT_LEADER  = 'ELECT_LEADER';

    /**
     * Encode a message into an authenticated JSON frame.
     *
     * @param string $type
     * @param array<string, mixed> $payload
     * @param string $secret
     * @return string
     */
    public static function encode(string $type, array $payload = [], string $secret = ''): string
    {
        $data = [
            'type' => $type,
            'timestamp' => microtime(true),
            'payload' => $payload,
        ];

        $json = json_encode($data, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new InvalidArgumentException("Failed to JSON encode payload data");
        }

        $signature = hash_hmac('sha256', $json, $secret);

        $frame = [
            'sig' => $signature,
            'body' => $json,
        ];

        return json_encode($frame) . "\n";
    }

    /**
     * Decode and authenticate an incoming frame.
     *
     * @param string $raw
     * @param string $secret
     * @return array{type: string, timestamp: float, payload: array<string, mixed>}
     * @throws InvalidArgumentException
     */
    public static function decode(string $raw, string $secret = ''): array
    {
        $raw = trim($raw);
        if (str_starts_with($raw, "OSHM_SWRM_V1")) {
            $raw = trim(substr($raw, strlen("OSHM_SWRM_V1")));
        }

        if ($raw === '') {
            throw new InvalidArgumentException("Empty frame received");
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['sig'], $decoded['body']) || !is_string($decoded['sig']) || !is_string($decoded['body'])) {
            throw new InvalidArgumentException("Invalid protocol frame structure");
        }

        $body = $decoded['body'];
        $expectedSig = hash_hmac('sha256', $body, $secret);

        if (!hash_equals($expectedSig, $decoded['sig'])) {
            throw new InvalidArgumentException("Invalid frame signature / cluster token mismatch");
        }

        $payload = json_decode($body, true);
        if (!is_array($payload) || !isset($payload['type'], $payload['payload']) || !is_string($payload['type']) || !is_array($payload['payload'])) {
            throw new InvalidArgumentException("Corrupted frame payload");
        }

        return $payload;
    }
}
