<?php
declare(strict_types=1);

namespace Oshim\Virtualization\Node;

use InvalidArgumentException;
use JsonException;
use Oshim\Virtualization\Exceptions\NodeRpcException;
use RuntimeException;

/**
 * Strict JSON-RPC 2.0 protocol encoder, decoder, and validator.
 */
final class JsonRpcProtocol
{
    // Standard JSON-RPC 2.0 Error Codes
    public const PARSE_ERROR      = -32700;
    public const INVALID_REQUEST  = -32600;
    public const METHOD_NOT_FOUND = -32601;
    public const INVALID_PARAMS   = -32602;
    public const INTERNAL_ERROR   = -32603;

    // Custom OSHIM Domain Error Codes
    public const UNAUTHORIZED        = -32001;
    public const CONTAINER_NOT_FOUND = -32002;
    public const INVALID_STATE       = -32003;
    public const QUOTA_EXCEEDED      = -32004;
    public const STORAGE_ERROR       = -32005;
    public const NETWORK_ERROR       = -32006;

    /**
     * Format a JSON-RPC 2.0 request payload.
     *
     * @param array<string, mixed>|list<mixed> $params
     */
    public static function formatRequest(string $method, array $params = [], int|string|null $id = null): array
    {
        $req = [
            'jsonrpc' => '2.0',
            'method'  => $method,
            'params'  => $params,
        ];

        if ($id !== null) {
            $req['id'] = $id;
        }

        return $req;
    }

    /**
     * Format a JSON-RPC 2.0 success response.
     */
    public static function formatSuccess(int|string|null $id, mixed $result): array
    {
        return [
            'jsonrpc' => '2.0',
            'result'  => $result,
            'id'      => $id,
        ];
    }

    /**
     * Format a JSON-RPC 2.0 error response.
     */
    public static function formatError(int|string|null $id, int $code, string $message, mixed $data = null): array
    {
        $err = [
            'code'    => $code,
            'message' => $message,
        ];

        if ($data !== null) {
            $err['data'] = $data;
        }

        return [
            'jsonrpc' => '2.0',
            'error'   => $err,
            'id'      => $id,
        ];
    }

    /**
     * Parse raw incoming JSON string into associative array or batch list.
     *
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    public static function parsePayload(string $rawJson): array
    {
        $trimmed = trim($rawJson);
        if ($trimmed === '') {
            throw new NodeRpcException("Empty JSON payload", self::PARSE_ERROR);
        }

        try {
            $data = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new NodeRpcException("Parse error: Malformed JSON string", self::PARSE_ERROR, null, $e);
        }

        if (!is_array($data)) {
            throw new NodeRpcException("Invalid Request: Top-level payload must be JSON object or array", self::INVALID_REQUEST);
        }

        return $data;
    }

    /**
     * Check if a parsed JSON payload represents a batch request.
     */
    public static function isBatch(array $parsed): bool
    {
        return array_is_list($parsed);
    }

    /**
     * Validate structure of a single JSON-RPC 2.0 request object.
     *
     * @param array<string, mixed> $request
     */
    public static function validateRequestStructure(array $request): void
    {
        if (!isset($request['jsonrpc']) || $request['jsonrpc'] !== '2.0') {
            throw new NodeRpcException("Invalid Request: Missing or invalid 'jsonrpc' version. Must be '2.0'.", self::INVALID_REQUEST);
        }

        if (!isset($request['method']) || !is_string($request['method']) || trim($request['method']) === '') {
            throw new NodeRpcException("Invalid Request: Missing or invalid 'method' string.", self::INVALID_REQUEST);
        }

        if (isset($request['params']) && !is_array($request['params'])) {
            throw new NodeRpcException("Invalid Request: 'params' must be an object or array.", self::INVALID_REQUEST);
        }
    }
}
