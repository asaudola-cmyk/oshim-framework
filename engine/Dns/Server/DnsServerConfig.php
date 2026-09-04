<?php
declare(strict_types=1);

namespace Oshim\Dns\Server;

/**
 * Configuration Entity for Authoritative DNS Server.
 */
class DnsServerConfig
{
    public string $host;
    public int $port;
    public int $defaultTtl;
    public int $maxUdpSize;
    public float $timeout;

    public function __construct(
        string $host = '0.0.0.0',
        int $port = 53,
        int $defaultTtl = 3600,
        int $maxUdpSize = 512,
        float $timeout = 5.0
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->defaultTtl = $defaultTtl;
        $this->maxUdpSize = $maxUdpSize;
        $this->timeout = $timeout;
    }
}
