<?php
declare(strict_types=1);

namespace Oshim\Epp\Transport;

use Oshim\Epp\Codec\EppFrameCodec;
use Oshim\Epp\Exceptions\EppConnectionException;
use Oshim\Epp\Exceptions\EppTransportException;

/**
 * Pure PHP TLS Stream Socket Client for RFC 5734 EPP over TCP Port 700.
 */
class TlsStreamTransport implements EppTransportInterface
{
    /** @var resource|null */
    private $stream = null;
    /** @var array<string, mixed> */
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'host' => '127.0.0.1',
            'port' => 700,
            'timeout' => 30,
            'verify_peer' => true,
            'verify_peer_name' => true,
            'cafile' => null,
            'local_cert' => null,
            'local_pk' => null,
            'passphrase' => null,
        ], $config);
    }

    public function connect(): string
    {
        if ($this->isConnected()) {
            return '';
        }

        $sslOptions = [
            'verify_peer' => (bool)$this->config['verify_peer'],
            'verify_peer_name' => (bool)$this->config['verify_peer_name'],
            'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
        ];

        if (!empty($this->config['cafile'])) {
            $sslOptions['cafile'] = $this->config['cafile'];
        }
        if (!empty($this->config['local_cert'])) {
            $sslOptions['local_cert'] = $this->config['local_cert'];
        }
        if (!empty($this->config['local_pk'])) {
            $sslOptions['local_pk'] = $this->config['local_pk'];
        }
        if (!empty($this->config['passphrase'])) {
            $sslOptions['passphrase'] = $this->config['passphrase'];
        }

        $context = stream_context_create([
            'ssl' => $sslOptions,
            'socket' => [
                'tcp_nodelay' => true,
            ],
        ]);

        $remoteSocket = "tls://{$this->config['host']}:{$this->config['port']}";
        $errno = 0;
        $errstr = '';

        $this->stream = @stream_socket_client(
            $remoteSocket,
            $errno,
            $errstr,
            (float)$this->config['timeout'],
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$this->stream) {
            throw new EppConnectionException(
                sprintf("Failed to connect to EPP registry at %s: [%d] %s", $remoteSocket, $errno, $errstr),
                $errno
            );
        }

        stream_set_timeout($this->stream, (int)$this->config['timeout']);
        stream_set_blocking($this->stream, true);

        // Read and return the initial server greeting frame
        return EppFrameCodec::readFromStream($this->stream, (int)$this->config['timeout']);
    }

    public function send(string $xml): void
    {
        if (!$this->isConnected()) {
            throw new EppTransportException("Cannot send EPP frame: transport is not connected.");
        }
        EppFrameCodec::writeToStream($this->stream, $xml);
    }

    public function receive(): string
    {
        if (!$this->isConnected()) {
            throw new EppTransportException("Cannot receive EPP frame: transport is not connected.");
        }
        return EppFrameCodec::readFromStream($this->stream, (int)$this->config['timeout']);
    }

    public function sendAndReceive(string $xml): string
    {
        $this->send($xml);
        return $this->receive();
    }

    public function isConnected(): bool
    {
        return is_resource($this->stream) && !feof($this->stream);
    }

    public function disconnect(): void
    {
        if (is_resource($this->stream)) {
            @fclose($this->stream);
        }
        $this->stream = null;
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
