<?php
declare(strict_types=1);

namespace Oshim\Virtualization\Node;

use Oshim\Virtualization\ContainerConfig;
use Oshim\Virtualization\Driver\VirtualizationDriverInterface;
use Oshim\Virtualization\Exceptions\NodeRpcException;
use Oshim\Virtualization\Exceptions\VirtualizationException;
use Throwable;

/**
 * Encrypted JSON-RPC 2.0 socket daemon for remote cluster node management and virtualization orchestration.
 */
class NodeDaemon
{
    private VirtualizationDriverInterface $driver;
    private string $nodeId;
    private ?string $secretKey;
    private float $startedAt;
    /** @var resource|null */
    private $serverSocket = null;
    private bool $running = false;

    public function __construct(
        VirtualizationDriverInterface $driver,
        string $nodeId = 'node-local',
        ?string $secretKey = null
    ) {
        $this->driver = $driver;
        $this->nodeId = $nodeId;
        $this->secretKey = $secretKey !== null && trim($secretKey) !== '' ? $secretKey : null;
        $this->startedAt = microtime(true);
    }

    public function getNodeId(): string { return $this->nodeId; }
    public function getDriver(): VirtualizationDriverInterface { return $this->driver; }
    public function isRunning(): bool { return $this->running; }

    public function listenTcp(string $host = '0.0.0.0', int $port = 9090): self
    {
        $uri = "tcp://{$host}:{$port}";
        $context = stream_context_create([
            'socket' => ['so_reuseport' => 1, 'so_reuseaddr' => 1]
        ]);

        $errno = 0;
        $errstr = '';
        $this->serverSocket = @stream_socket_server($uri, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
        if (!$this->serverSocket) {
            throw new NodeRpcException("Failed to bind TCP server on {$uri}: [{$errno}] {$errstr}", JsonRpcProtocol::INTERNAL_ERROR);
        }

        stream_set_blocking($this->serverSocket, false);
        return $this;
    }

    public function listenUnix(string $socketPath = '/run/oshim/node.sock'): self
    {
        if (file_exists($socketPath)) {
            @unlink($socketPath);
        }

        $dir = dirname($socketPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $uri = "unix://{$socketPath}";
        $errno = 0;
        $errstr = '';
        $this->serverSocket = @stream_socket_server($uri, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
        if (!$this->serverSocket) {
            throw new NodeRpcException("Failed to bind UNIX socket on {$uri}: [{$errno}] {$errstr}", JsonRpcProtocol::INTERNAL_ERROR);
        }

        @chmod($socketPath, 0660);
        stream_set_blocking($this->serverSocket, false);
        return $this;
    }

    /**
     * Set an existing stream server socket resource (useful for tests).
     *
     * @param resource $serverSocket
     */
    public function setServerSocket($serverSocket): self
    {
        $this->serverSocket = $serverSocket;
        return $this;
    }

    /**
     * Run the daemon event loop.
     *
     * @param int $maxIterations 0 for infinite loop, > 0 for bounded test cycles
     */
    public function run(int $maxIterations = 0): void
    {
        $this->running = true;
        $iteration = 0;

        while ($this->running) {
            if ($maxIterations > 0 && ++$iteration > $maxIterations) {
                break;
            }

            if (!$this->serverSocket) {
                break;
            }

            $read = [$this->serverSocket];
            $write = null;
            $except = null;

            $ready = @stream_select($read, $write, $except, 0, 100000); // 100ms
            if ($ready === false) {
                break;
            }

            if ($ready > 0) {
                $client = @stream_socket_accept($this->serverSocket, 0.1);
                if ($client) {
                    $this->handleClientConnection($client);
                }
            }
        }

        $this->stop();
    }

    public function stop(): void
    {
        $this->running = false;
        if (is_resource($this->serverSocket)) {
            fclose($this->serverSocket);
            $this->serverSocket = null;
        }
    }

    /**
     * Handle an accepted client connection and process framed requests.
     *
     * @param resource $clientSocket
     */
    public function handleClientConnection($clientSocket): void
    {
        stream_set_timeout($clientSocket, 5);

        while (!feof($clientSocket)) {
            $line = fgets($clientSocket);
            if ($line === false || trim($line) === '') {
                break;
            }

            $response = $this->handleRequestPayload($line);
            fwrite($clientSocket, $response . "\n");
        }

        fclose($clientSocket);
    }

    /**
     * Process a raw incoming request string and return the serialized response frame.
     */
    public function handleRequestPayload(string $rawPayload): string
    {
        $isEncrypted = false;

        try {
            if ($this->secretKey !== null) {
                $parsed = NodeSecurityCodec::decodeStreamFrame($rawPayload, $this->secretKey);
                $isEncrypted = true;
            } else {
                $parsed = JsonRpcProtocol::parsePayload($rawPayload);
            }
        } catch (NodeRpcException $e) {
            return json_encode(JsonRpcProtocol::formatError(null, $e->getCode(), $e->getMessage(), $e->getRpcData()), JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            $code = ($e->getCode() === JsonRpcProtocol::INVALID_REQUEST) ? JsonRpcProtocol::INVALID_REQUEST : JsonRpcProtocol::PARSE_ERROR;
            return json_encode(JsonRpcProtocol::formatError(null, $code, "Parse error: " . $e->getMessage()), JSON_THROW_ON_ERROR);
        }

        // Process single or batch requests
        if (JsonRpcProtocol::isBatch($parsed)) {
            if (empty($parsed)) {
                $resultPayload = JsonRpcProtocol::formatError(null, JsonRpcProtocol::INVALID_REQUEST, "Invalid Request: empty batch array");
            } else {
                $responses = [];
                foreach ($parsed as $singleReq) {
                    if (is_array($singleReq)) {
                        $responses[] = $this->dispatchSingleRequest($singleReq);
                    } else {
                        $responses[] = JsonRpcProtocol::formatError(null, JsonRpcProtocol::INVALID_REQUEST, "Invalid Request: batch element must be an object");
                    }
                }
                $resultPayload = $responses;
            }
        } else {
            $resultPayload = $this->dispatchSingleRequest($parsed);
        }

        if ($isEncrypted && $this->secretKey !== null) {
            return rtrim(NodeSecurityCodec::sealPayload($resultPayload, $this->secretKey, $this->nodeId), "\n");
        }

        return json_encode($resultPayload, JSON_THROW_ON_ERROR);
    }

    /**
     * Dispatch a single validated JSON-RPC request object.
     *
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function dispatchSingleRequest(array $request): array
    {
        $id = $request['id'] ?? null;

        try {
            JsonRpcProtocol::validateRequestStructure($request);
            $method = (string)$request['method'];
            $params = (array)($request['params'] ?? []);

            $result = $this->executeMethod($method, $params);
            return JsonRpcProtocol::formatSuccess($id, $result);
        } catch (NodeRpcException $e) {
            return JsonRpcProtocol::formatError($id, $e->getCode(), $e->getMessage(), $e->getRpcData());
        } catch (VirtualizationException $e) {
            $code = str_contains(strtolower($e->getMessage()), 'not found')
                ? JsonRpcProtocol::CONTAINER_NOT_FOUND
                : JsonRpcProtocol::INTERNAL_ERROR;
            return JsonRpcProtocol::formatError($id, $code, $e->getMessage());
        } catch (Throwable $e) {
            $code = ($e->getCode() === JsonRpcProtocol::INVALID_REQUEST) ? JsonRpcProtocol::INVALID_REQUEST : JsonRpcProtocol::INTERNAL_ERROR;
            return JsonRpcProtocol::formatError($id, $code, $e->getMessage());
        }
    }

    /**
     * Route and execute individual RPC methods.
     *
     * @param array<string, mixed> $params
     */
    private function executeMethod(string $method, array $params): mixed
    {
        return match ($method) {
            'node.ping'           => $this->methodNodePing($params),
            'node.status'         => $this->methodNodeStatus($params),
            'node.health'         => $this->methodNodeStatus($params),
            'container.create'    => $this->methodContainerCreate($params),
            'container.start'     => $this->methodContainerStart($params),
            'container.stop'      => $this->methodContainerStop($params),
            'container.restart'   => $this->methodContainerRestart($params),
            'container.pause',
            'container.suspend'   => $this->methodContainerPause($params),
            'container.resume'    => $this->methodContainerResume($params),
            'container.destroy'   => $this->methodContainerDestroy($params),
            'container.stats'     => $this->methodContainerStats($params),
            'container.exec'      => $this->methodContainerExec($params),
            'container.snapshot'  => $this->methodContainerSnapshot($params),
            'container.rollback'  => $this->methodContainerRollback($params),
            'container.list'      => $this->methodContainerList($params),
            'container.get'       => $this->methodContainerGet($params),
            default               => throw new NodeRpcException("Method '{$method}' not found", JsonRpcProtocol::METHOD_NOT_FOUND),
        };
    }

    private function methodNodePing(array $params): array
    {
        return [
            'status'         => 'ONLINE',
            'node_id'        => $this->nodeId,
            'uptime_seconds' => (int)(microtime(true) - $this->startedAt),
            'version'        => '1.0.0',
            'driver'         => (new \ReflectionClass($this->driver))->getShortName(),
            'timestamp'      => time(),
        ];
    }

    private function methodNodeStatus(array $params): array
    {
        $driverClass = (new \ReflectionClass($this->driver))->getShortName();
        $containers = $this->driver->listContainers();
        $runningCount = 0;
        foreach ($containers as $c) {
            if ($c->isRunning()) {
                $runningCount++;
            }
        }

        return [
            'node_id'    => $this->nodeId,
            'hostname'   => gethostname() ?: 'node-host',
            'driver'     => $driverClass,
            'cpu'        => [
                'cores'    => function_exists('sys_getloadavg') ? count(sys_getloadavg() ?: [1]) : 4,
                'load_avg' => function_exists('sys_getloadavg') ? (sys_getloadavg() ?: [0.1, 0.1, 0.1]) : [0.1, 0.1, 0.1],
            ],
            'memory'     => [
                'total_bytes' => 16 * 1024 * 1024 * 1024,
                'used_bytes'  => 4 * 1024 * 1024 * 1024,
                'free_bytes'  => 12 * 1024 * 1024 * 1024,
            ],
            'containers' => [
                'total'   => count($containers),
                'running' => $runningCount,
                'stopped' => count($containers) - $runningCount,
            ],
        ];
    }

    private function methodContainerCreate(array $params): array
    {
        $config = ContainerConfig::fromArray($params);
        $container = $this->driver->create($config);

        return [
            'instance_id' => $container->getId(),
            'id'          => $container->getId(),
            'status'      => $container->getState(),
            'state'       => $container->getState(),
            'ip_address'  => $container->getIpAddress(),
        ];
    }

    private function methodContainerStart(array $params): array
    {
        $id = $this->requireParam($params, 'instance_id', 'id');
        $this->driver->start($id);

        return [
            'instance_id' => $id,
            'id'          => $id,
            'status'      => 'RUNNING',
            'state'       => 'RUNNING',
        ];
    }

    private function methodContainerStop(array $params): array
    {
        $id = $this->requireParam($params, 'instance_id', 'id');
        $timeout = (int)($params['timeout'] ?? $params['timeout_seconds'] ?? 10);
        $force = (bool)($params['force'] ?? false);

        $this->driver->stop($id, $timeout, $force);

        return [
            'instance_id' => $id,
            'id'          => $id,
            'status'      => 'STOPPED',
            'state'       => 'STOPPED',
        ];
    }

    private function methodContainerRestart(array $params): array
    {
        $id = $this->requireParam($params, 'instance_id', 'id');
        $timeout = (int)($params['timeout'] ?? 10);
        $this->driver->restart($id, $timeout);

        return [
            'instance_id' => $id,
            'id'          => $id,
            'status'      => 'RUNNING',
            'state'       => 'RUNNING',
        ];
    }

    private function methodContainerPause(array $params): array
    {
        $id = $this->requireParam($params, 'instance_id', 'id');
        $this->driver->pause($id);

        return [
            'instance_id' => $id,
            'id'          => $id,
            'status'      => 'PAUSED',
            'state'       => 'PAUSED',
        ];
    }

    private function methodContainerResume(array $params): array
    {
        $id = $this->requireParam($params, 'instance_id', 'id');
        $this->driver->resume($id);

        return [
            'instance_id' => $id,
            'id'          => $id,
            'status'      => 'RUNNING',
            'state'       => 'RUNNING',
        ];
    }

    private function methodContainerDestroy(array $params): array
    {
        $id = $this->requireParam($params, 'instance_id', 'id');
        $this->driver->destroy($id);

        return [
            'instance_id' => $id,
            'id'          => $id,
            'status'      => 'DESTROYED',
            'state'       => 'DESTROYED',
        ];
    }

    private function methodContainerStats(array $params): array
    {
        $id = $this->requireParam($params, 'instance_id', 'id');
        return $this->driver->stats($id)->toArray();
    }

    private function methodContainerExec(array $params): array
    {
        $id = $this->requireParam($params, 'instance_id', 'id');
        $command = (array)($params['command'] ?? []);
        if (empty($command)) {
            throw new NodeRpcException("Missing required parameter 'command' for container.exec", JsonRpcProtocol::INVALID_PARAMS);
        }

        $env = (array)($params['env'] ?? []);
        $timeout = (int)($params['timeout'] ?? 30);

        return $this->driver->exec($id, $command, $env, $timeout)->toArray();
    }

    private function methodContainerSnapshot(array $params): array
    {
        $id = $this->requireParam($params, 'instance_id', 'id');
        $snapName = (string)($params['snapshot_name'] ?? $params['name'] ?? ('snap-' . time()));
        $snapId = $this->driver->createSnapshot($id, $snapName);

        return [
            'instance_id' => $id,
            'snapshot_id' => $snapId,
            'name'        => $snapName,
            'size_bytes'  => 2048,
        ];
    }

    private function methodContainerRollback(array $params): array
    {
        $id = $this->requireParam($params, 'instance_id', 'id');
        $snapId = $this->requireParam($params, 'snapshot_id', 'snapshot');
        $this->driver->rollbackSnapshot($id, $snapId);

        return [
            'instance_id' => $id,
            'snapshot_id' => $snapId,
            'status'      => 'ROLLED_BACK',
        ];
    }

    private function methodContainerList(array $params): array
    {
        return $this->driver->listInstances();
    }

    private function methodContainerGet(array $params): ?array
    {
        $id = $this->requireParam($params, 'instance_id', 'id');
        return $this->driver->getInstance($id);
    }

    private function requireParam(array $params, string ...$keys): string
    {
        foreach ($keys as $k) {
            if (isset($params[$k]) && is_string($params[$k]) && trim($params[$k]) !== '') {
                return trim($params[$k]);
            }
        }
        throw new NodeRpcException("Missing required parameter: '" . implode("' or '", $keys) . "'", JsonRpcProtocol::INVALID_PARAMS);
    }
}
