<?php
declare(strict_types=1);

namespace Oshim\Dns\Server;

use Oshim\Dns\Exceptions\DnsServerException;
use Oshim\Dns\Resolver\AuthoritativeResolver;
use Oshim\Dns\Zone\ZoneRepositoryInterface;

/**
 * Dual-Stack UDP & TCP Non-Blocking Authoritative DNS Server (RFC 1035 §4.2).
 */
class DnsServer
{
    private DnsServerConfig $config;
    private ZoneRepositoryInterface $repository;
    private AuthoritativeResolver $resolver;

    /** @var resource|null */
    private $udpSocket = null;
    /** @var resource|null */
    private $tcpSocket = null;
    /** @var array<int, array{socket: resource, buffer: string}> */
    private array $tcpClients = [];
    private bool $running = false;

    public function __construct(
        ZoneRepositoryInterface $repository,
        ?DnsServerConfig $config = null,
        ?AuthoritativeResolver $resolver = null
    ) {
        $this->repository = $repository;
        $this->config = $config ?? new DnsServerConfig();
        $this->resolver = $resolver ?? new AuthoritativeResolver($repository);
    }

    public function getResolver(): AuthoritativeResolver
    {
        return $this->resolver;
    }

    public function getRepository(): ZoneRepositoryInterface
    {
        return $this->repository;
    }

    public function getConfig(): DnsServerConfig
    {
        return $this->config;
    }

    /**
     * Initializes UDP and TCP listener sockets.
     *
     * @throws DnsServerException On socket binding failures.
     */
    public function listen(): void
    {
        if ($this->udpSocket !== null || $this->tcpSocket !== null) {
            return;
        }

        $errno = 0;
        $errstr = '';

        // 1. Create UDP Socket
        $udpUri = "udp://{$this->config->host}:{$this->config->port}";
        $this->udpSocket = @stream_socket_server($udpUri, $errno, $errstr, STREAM_SERVER_BIND);
        if (!$this->udpSocket) {
            throw new DnsServerException(
                sprintf("Failed to bind UDP server on %s: [%d] %s", $udpUri, $errno, $errstr),
                $errno
            );
        }
        stream_set_blocking($this->udpSocket, false);

        // 2. Create TCP Socket
        $tcpUri = "tcp://{$this->config->host}:{$this->config->port}";
        $this->tcpSocket = @stream_socket_server($tcpUri, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
        if (!$this->tcpSocket) {
            @fclose($this->udpSocket);
            $this->udpSocket = null;
            throw new DnsServerException(
                sprintf("Failed to bind TCP server on %s: [%d] %s", $tcpUri, $errno, $errstr),
                $errno
            );
        }
        stream_set_blocking($this->tcpSocket, false);
    }

    /**
     * Performs a single non-blocking tick of event multiplexing.
     */
    public function tick(int $timeoutMicroseconds = 20000): void
    {
        if ($this->udpSocket === null || $this->tcpSocket === null) {
            $this->listen();
        }

        $read = [$this->udpSocket, $this->tcpSocket];
        foreach ($this->tcpClients as $client) {
            if (is_resource($client['socket'])) {
                $read[] = $client['socket'];
            }
        }

        $write = null;
        $except = null;
        $tvSec = (int)($timeoutMicroseconds / 1000000);
        $tvUsec = $timeoutMicroseconds % 1000000;

        $numChanged = @stream_select($read, $write, $except, $tvSec, $tvUsec);

        if ($numChanged === false || $numChanged === 0) {
            return;
        }

        foreach ($read as $socket) {
            if ($socket === $this->udpSocket) {
                $this->handleUdpPacket();
            } elseif ($socket === $this->tcpSocket) {
                $this->acceptTcpConnection();
            } else {
                $this->handleTcpClientData($socket);
            }
        }
    }

    /**
     * Starts blocking event loop.
     */
    public function start(): void
    {
        $this->listen();
        $this->running = true;

        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, function () {
                $this->stop();
            });
            pcntl_signal(SIGTERM, function () {
                $this->stop();
            });
        }

        while ($this->running) {
            $this->tick(50000);
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }
    }

    public function stop(): void
    {
        $this->running = false;

        foreach ($this->tcpClients as $client) {
            if (is_resource($client['socket'])) {
                @fclose($client['socket']);
            }
        }
        $this->tcpClients = [];

        if (is_resource($this->udpSocket)) {
            @fclose($this->udpSocket);
        }
        $this->udpSocket = null;

        if (is_resource($this->tcpSocket)) {
            @fclose($this->tcpSocket);
        }
        $this->tcpSocket = null;
    }

    public function isRunning(): bool
    {
        return $this->running || ($this->udpSocket !== null && $this->tcpSocket !== null);
    }

    private function handleUdpPacket(): void
    {
        $peerName = '';
        $data = @stream_socket_recvfrom($this->udpSocket, 4096, 0, $peerName);

        if ($data === false || $data === '' || $peerName === '') {
            return;
        }

        $responseBytes = $this->resolver->resolveQueryWire($data, 'udp', $this->config->maxUdpSize);
        @stream_socket_sendto($this->udpSocket, $responseBytes, 0, $peerName);
    }

    private function acceptTcpConnection(): void
    {
        $clientSocket = @stream_socket_accept($this->tcpSocket, 0);
        if ($clientSocket) {
            stream_set_blocking($clientSocket, false);
            $id = (int)$clientSocket;
            $this->tcpClients[$id] = [
                'socket' => $clientSocket,
                'buffer' => '',
            ];
        }
    }

    private function handleTcpClientData($socket): void
    {
        $id = (int)$socket;
        if (!isset($this->tcpClients[$id])) {
            return;
        }

        $chunk = @fread($socket, 8192);

        if ($chunk === false || $chunk === '' || feof($socket)) {
            @fclose($socket);
            unset($this->tcpClients[$id]);
            return;
        }

        $this->tcpClients[$id]['buffer'] .= $chunk;
        $buf = &$this->tcpClients[$id]['buffer'];

        // Process complete 2-byte length prefixed frames
        while (strlen($buf) >= 2) {
            $expectedLength = unpack('n', substr($buf, 0, 2))[1];
            if (strlen($buf) < 2 + $expectedLength) {
                break; // Awaiting more data
            }

            $packetWire = substr($buf, 2, $expectedLength);
            $buf = substr($buf, 2 + $expectedLength);

            $responseBytes = $this->resolver->resolveQueryWire($packetWire, 'tcp', 0);
            $framedResponse = pack('n', strlen($responseBytes)) . $responseBytes;

            $written = @fwrite($socket, $framedResponse);
            if ($written === false) {
                @fclose($socket);
                unset($this->tcpClients[$id]);
                return;
            }
        }
    }

    public function __destruct()
    {
        $this->stop();
    }
}
