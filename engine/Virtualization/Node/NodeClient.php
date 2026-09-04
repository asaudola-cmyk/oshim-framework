<?php
declare(strict_types=1);

namespace Oshim\Virtualization\Node;

use Oshim\Virtualization\Exceptions\NodeRpcException;
use RuntimeException;
use Throwable;

/**
 * Client SDK for communicating with OSHIM Node Daemon over TCP / UNIX stream sockets.
 */
class NodeClient
{
    private string $targetUri;
    private ?string $secretKey;
    private string $clientId;
    private int $timeoutSeconds;
    /** @var resource|null */
    private $streamSocket = null;
    private int $requestId = 0;

    public function __construct(
        string $targetUri = 'tcp://127.0.0.1:9090',
        ?string $secretKey = null,
        string $clientId = 'superadmin',
        int $timeoutSeconds = 10
    ) {
        $this->targetUri = $targetUri;
        $this->secretKey = $secretKey !== null && trim($secretKey) !== '' ? $secretKey : null;
        $this->clientId = $clientId;
        $this->timeoutSeconds = $timeoutSeconds;
    }

    public function getTargetUri(): string { return $this->targetUri; }
    public function getClientId(): string { return $this->clientId; }

    public function connect(): self
    {
        $errno = 0;
        $errstr = '';

        $this->streamSocket = @stream_socket_client(
            $this->targetUri,
            $errno,
            $errstr,
            $this->timeoutSeconds,
            STREAM_CLIENT_CONNECT
        );

        if (!$this->streamSocket) {
            throw new NodeRpcException("Failed to connect to Node Daemon at {$this->targetUri}: [{$errno}] {$errstr}", JsonRpcProtocol::INTERNAL_ERROR);
        }

        stream_set_timeout($this->streamSocket, $this->timeoutSeconds);
        return $this;
    }

    /**
     * Send a JSON-RPC 2.0 call and return the unwrapped result.
     *
     * @param array<string, mixed> $params
     */
    public function call(string $method, array $params = []): mixed
    {
        if (!is_resource($this->streamSocket)) {
            $this->connect();
        }

        $id = ++$this->requestId;
        $request = JsonRpcProtocol::formatRequest($method, $params, $id);

        if ($this->secretKey !== null) {
            $frame = NodeSecurityCodec::sealPayload($request, $this->secretKey, $this->clientId);
        } else {
            $frame = json_encode($request, JSON_THROW_ON_ERROR) . "\n";
        }

        $written = @fwrite($this->streamSocket, $frame);
        if ($written === false || $written === 0) {
            throw new NodeRpcException("Failed to write request to socket stream", JsonRpcProtocol::INTERNAL_ERROR);
        }

        $responseLine = @fgets($this->streamSocket);
        if ($responseLine === false || trim($responseLine) === '') {
            throw new NodeRpcException("Node Daemon closed connection or timed out", JsonRpcProtocol::INTERNAL_ERROR);
        }

        if ($this->secretKey !== null) {
            $responseRpc = NodeSecurityCodec::openPayload($responseLine, $this->secretKey);
        } else {
            $responseRpc = JsonRpcProtocol::parsePayload($responseLine);
        }

        if (isset($responseRpc['error'])) {
            $err = (array)$responseRpc['error'];
            throw new NodeRpcException(
                message: (string)($err['message'] ?? 'JSON-RPC Error'),
                code: (int)($err['code'] ?? JsonRpcProtocol::INTERNAL_ERROR),
                rpcData: $err['data'] ?? null
            );
        }

        return $responseRpc['result'] ?? null;
    }

    // --- Fluent API Helpers ---

    public function ping(): array
    {
        return (array)$this->call('node.ping');
    }

    public function getStatus(): array
    {
        return (array)$this->call('node.status');
    }

    public function createContainer(array $spec): array
    {
        return (array)$this->call('container.create', $spec);
    }

    public function startContainer(string $instanceId): array
    {
        return (array)$this->call('container.start', ['instance_id' => $instanceId]);
    }

    public function stopContainer(string $instanceId, int $timeout = 10, bool $force = false): array
    {
        return (array)$this->call('container.stop', ['instance_id' => $instanceId, 'timeout' => $timeout, 'force' => $force]);
    }

    public function restartContainer(string $instanceId): array
    {
        return (array)$this->call('container.restart', ['instance_id' => $instanceId]);
    }

    public function pauseContainer(string $instanceId): array
    {
        return (array)$this->call('container.pause', ['instance_id' => $instanceId]);
    }

    public function resumeContainer(string $instanceId): array
    {
        return (array)$this->call('container.resume', ['instance_id' => $instanceId]);
    }

    public function destroyContainer(string $instanceId): array
    {
        return (array)$this->call('container.destroy', ['instance_id' => $instanceId]);
    }

    public function getStats(string $instanceId): array
    {
        return (array)$this->call('container.stats', ['instance_id' => $instanceId]);
    }

    public function exec(string $instanceId, array $command, array $env = [], int $timeout = 30): array
    {
        return (array)$this->call('container.exec', ['instance_id' => $instanceId, 'command' => $command, 'env' => $env, 'timeout' => $timeout]);
    }

    public function createSnapshot(string $instanceId, string $snapshotName): array
    {
        return (array)$this->call('container.snapshot', ['instance_id' => $instanceId, 'snapshot_name' => $snapshotName]);
    }

    public function rollbackSnapshot(string $instanceId, string $snapshotId): array
    {
        return (array)$this->call('container.rollback', ['instance_id' => $instanceId, 'snapshot_id' => $snapshotId]);
    }

    public function listContainers(): array
    {
        return (array)$this->call('container.list');
    }

    public function getContainer(string $instanceId): ?array
    {
        $res = $this->call('container.get', ['instance_id' => $instanceId]);
        return is_array($res) ? $res : null;
    }

    public function close(): void
    {
        if (is_resource($this->streamSocket)) {
            fclose($this->streamSocket);
            $this->streamSocket = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
