<?php
declare(strict_types=1);

namespace Oshim\Virtualization\Node;

use JsonException;
use Oshim\Security\Cipher;
use Oshim\Virtualization\Exceptions\NodeRpcException;
use Throwable;

/**
 * Enterprise security codec providing HMAC-SHA256 authentication, replay defense, and AES-256-GCM AEAD encryption.
 */
class NodeSecurityCodec
{
    /**
     * Seal a JSON-RPC payload into an encrypted and HMAC-signed envelope frame.
     *
     * @param array<string, mixed>|list<array<string, mixed>> $jsonRpcData
     */
    public static function sealPayload(array $jsonRpcData, string $sharedSecretKey, string $nodeId = 'node-local'): string
    {
        $plaintextJson = json_encode($jsonRpcData, JSON_THROW_ON_ERROR);
        $encryptedPayload = Cipher::encrypt($plaintextJson, $sharedSecretKey);

        $envelope = [
            'node_id'   => $nodeId,
            'nonce'     => bin2hex(random_bytes(8)),
            'timestamp' => time(),
            'payload'   => $encryptedPayload,
        ];

        // Signature covers: timestamp + nonce + nodeId + payload
        $signedData = "{$envelope['timestamp']}:{$envelope['nonce']}:{$envelope['node_id']}:{$envelope['payload']}";
        $envelope['signature'] = hash_hmac('sha256', $signedData, $sharedSecretKey);

        return json_encode($envelope, JSON_THROW_ON_ERROR) . "\n";
    }

    /**
     * Open, authenticate, and decrypt an incoming envelope frame.
     *
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    public static function openPayload(string $rawEnvelopeJson, string $sharedSecretKey, int $maxTimeDriftSeconds = 60): array
    {
        $trimmed = trim($rawEnvelopeJson);
        if ($trimmed === '') {
            throw new NodeRpcException("Empty payload received", JsonRpcProtocol::PARSE_ERROR);
        }

        try {
            $envelope = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new NodeRpcException("Malformed JSON frame: " . $e->getMessage(), JsonRpcProtocol::PARSE_ERROR, null, $e);
        }

        if (!is_array($envelope) || !isset($envelope['signature'], $envelope['payload'], $envelope['timestamp'], $envelope['nonce'])) {
            throw new NodeRpcException("Malformed security envelope payload", JsonRpcProtocol::INVALID_REQUEST);
        }

        // 1. Time drift & Replay check
        $timestamp = (int)($envelope['timestamp'] ?? 0);
        if (abs(time() - $timestamp) > $maxTimeDriftSeconds) {
            throw new NodeRpcException("Replay attack protection: Timestamp drift exceeds {$maxTimeDriftSeconds}s", JsonRpcProtocol::UNAUTHORIZED);
        }

        // 2. Constant-time HMAC-SHA256 verification
        $nodeId = (string)($envelope['node_id'] ?? '');
        $nonce = (string)($envelope['nonce'] ?? '');
        $payload = (string)($envelope['payload'] ?? '');

        $signedData = "{$timestamp}:{$nonce}:{$nodeId}:{$payload}";
        $expectedSignature = hash_hmac('sha256', $signedData, $sharedSecretKey);

        if (!hash_equals($expectedSignature, (string)$envelope['signature'])) {
            throw new NodeRpcException("HMAC signature verification failed: Unauthorized", JsonRpcProtocol::UNAUTHORIZED);
        }

        // 3. AES-256-GCM AEAD decryption
        try {
            $plaintext = Cipher::decrypt($payload, $sharedSecretKey);
        } catch (Throwable $e) {
            throw new NodeRpcException("Payload decryption failed: " . $e->getMessage(), JsonRpcProtocol::UNAUTHORIZED, null, $e);
        }

        try {
            $decodedJsonRpc = json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new NodeRpcException("Decrypted ciphertext is not valid JSON", JsonRpcProtocol::PARSE_ERROR, null, $e);
        }

        if (!is_array($decodedJsonRpc)) {
            throw new NodeRpcException("Decrypted payload is not a valid JSON-RPC object/array", JsonRpcProtocol::INVALID_REQUEST);
        }

        return $decodedJsonRpc;
    }

    /**
     * Decode an incoming stream line, supporting both encrypted envelope and plain JSON-RPC.
     *
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    public static function decodeStreamFrame(string $rawFrame, ?string $sharedSecretKey = null, int $maxDrift = 60): array
    {
        $trimmed = trim($rawFrame);
        if ($trimmed === '') {
            throw new NodeRpcException("Empty stream frame", JsonRpcProtocol::PARSE_ERROR);
        }

        // Check if plain JSON-RPC
        if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
            try {
                $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded) && isset($decoded['signature'], $decoded['payload']) && $sharedSecretKey !== null) {
                    return self::openPayload($trimmed, $sharedSecretKey, $maxDrift);
                }
                if (is_array($decoded)) {
                    return $decoded;
                }
            } catch (JsonException $e) {
                throw new NodeRpcException("JSON parse error: " . $e->getMessage(), JsonRpcProtocol::PARSE_ERROR, null, $e);
            }
        }

        if ($sharedSecretKey !== null) {
            return self::openPayload($trimmed, $sharedSecretKey, $maxDrift);
        }

        throw new NodeRpcException("Unrecognized frame format", JsonRpcProtocol::PARSE_ERROR);
    }
}
