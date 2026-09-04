<?php
declare(strict_types=1);

namespace Oshim\Epp\Model;

/**
 * Value object representing host glue address record (IPv4 or IPv6).
 */
class HostAddress
{
    private string $ip;
    private string $version;

    public function __construct(string $ip, string $version = 'v4')
    {
        $this->ip = $ip;
        $this->version = strtolower($version);
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function isIpv4(): bool
    {
        return $this->version === 'v4';
    }

    public function isIpv6(): bool
    {
        return $this->version === 'v6';
    }
}
