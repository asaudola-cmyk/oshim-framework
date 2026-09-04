<?php
declare(strict_types=1);

namespace Oshim\Virtualization\Network;

use InvalidArgumentException;
use Oshim\Virtualization\Exceptions\NetworkException;

/**
 * High-performance IP Address Management (IPAM) and MAC allocation engine.
 */
class IpamService
{
    private string $cidr;
    private int $networkLong;
    private int $netmaskLong;
    private int $broadcastLong;
    private string $gatewayIp;
    /** @var array<string, string> */
    private array $allocatedIps = []; // ['10.42.0.10' => 'instance_id']

    public function __construct(string $cidr = '10.42.0.0/24', ?string $gatewayIp = null)
    {
        $this->cidr = $cidr;
        $subnetInfo = self::parseCidr($cidr);
        $this->networkLong = $subnetInfo['network_long'];
        $this->netmaskLong = $subnetInfo['netmask_long'];
        $this->broadcastLong = $subnetInfo['broadcast_long'];
        $this->gatewayIp = $gatewayIp ?? $subnetInfo['gateway_ip'];

        // Automatically reserve Network, Gateway, and Broadcast
        $this->allocatedIps[$subnetInfo['network_ip']] = '__NETWORK__';
        $this->allocatedIps[$this->gatewayIp] = '__GATEWAY__';
        if ($subnetInfo['netmask_bits'] < 31) {
            $this->allocatedIps[$subnetInfo['broadcast_ip']] = '__BROADCAST__';
        }
    }

    public function getCidr(): string
    {
        return $this->cidr;
    }

    public function getGatewayIp(): string
    {
        return $this->gatewayIp;
    }

    /**
     * Parse and slice an IPv4 CIDR notation into network boundaries and host counts.
     *
     * @return array{
     *   cidr: string,
     *   network_ip: string,
     *   network_long: int,
     *   netmask_ip: string,
     *   netmask_long: int,
     *   netmask_bits: int,
     *   gateway_ip: string,
     *   first_usable: string,
     *   last_usable: string,
     *   broadcast_ip: string,
     *   broadcast_long: int,
     *   total_hosts: int
     * }
     */
    public static function parseCidr(string $cidr): array
    {
        if (!str_contains($cidr, '/')) {
            throw new InvalidArgumentException("Invalid CIDR notation [{$cidr}]: Missing prefix length.");
        }

        [$ipStr, $maskBitsStr] = explode('/', $cidr, 2);
        if (!filter_var($ipStr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new InvalidArgumentException("Invalid IPv4 address [{$ipStr}].");
        }

        $maskBits = (int)$maskBitsStr;
        if ($maskBits < 1 || $maskBits > 32) {
            throw new InvalidArgumentException("Invalid CIDR mask bits [/{$maskBits}]. Must be between 1 and 32.");
        }

        $netmaskLong = $maskBits === 32 ? 0xFFFFFFFF : (~((1 << (32 - $maskBits)) - 1)) & 0xFFFFFFFF;
        $ipLong = ip2long($ipStr);
        if ($ipLong === false) {
            throw new InvalidArgumentException("Failed to convert IP to long: {$ipStr}");
        }

        $networkLong = $ipLong & $netmaskLong;
        $broadcastLong = $networkLong | (~$netmaskLong & 0xFFFFFFFF);

        $totalHosts = match ($maskBits) {
            32 => 1,
            31 => 2,
            default => max(0, (1 << (32 - $maskBits)) - 2),
        };

        $gatewayLong = $maskBits >= 31 ? $networkLong : ($networkLong + 1);
        $firstUsableLong = match ($maskBits) {
            32 => $networkLong,
            31 => $networkLong,
            default => $networkLong + 2, // after network and gateway
        };
        $lastUsableLong = match ($maskBits) {
            32 => $networkLong,
            31 => $broadcastLong,
            default => $broadcastLong - 1,
        };

        return [
            'cidr'           => $cidr,
            'network_ip'     => long2ip($networkLong),
            'network_long'   => $networkLong,
            'netmask_ip'     => long2ip($netmaskLong),
            'netmask_long'   => $netmaskLong,
            'netmask_bits'   => $maskBits,
            'gateway_ip'     => long2ip($gatewayLong),
            'first_usable'   => long2ip($firstUsableLong),
            'last_usable'    => long2ip($lastUsableLong),
            'broadcast_ip'   => long2ip($broadcastLong),
            'broadcast_long' => $broadcastLong,
            'total_hosts'    => $totalHosts,
        ];
    }

    /**
     * Check if two CIDR subnets overlap.
     */
    public static function checkSubnetOverlap(string $cidrA, string $cidrB): bool
    {
        $subA = self::parseCidr($cidrA);
        $subB = self::parseCidr($cidrB);

        $minBits = min($subA['netmask_bits'], $subB['netmask_bits']);
        $commonMask = $minBits === 32 ? 0xFFFFFFFF : (~((1 << (32 - $minBits)) - 1)) & 0xFFFFFFFF;

        return ($subA['network_long'] & $commonMask) === ($subB['network_long'] & $commonMask);
    }

    /**
     * Allocate the next available IPv4 address in the subnet or verify preferred IP.
     */
    public function allocateIp(string $instanceId, ?string $preferredIp = null): string
    {
        if ($preferredIp !== null) {
            $prefLong = ip2long($preferredIp);
            if ($prefLong === false || ($prefLong & $this->netmaskLong) !== $this->networkLong) {
                throw new InvalidArgumentException("Preferred IP '{$preferredIp}' does not belong to subnet {$this->cidr}");
            }
            if (isset($this->allocatedIps[$preferredIp])) {
                throw new NetworkException("Preferred IP '{$preferredIp}' is already allocated to {$this->allocatedIps[$preferredIp]}");
            }
            $this->allocatedIps[$preferredIp] = $instanceId;
            return $preferredIp;
        }

        $subnet = self::parseCidr($this->cidr);
        $startLong = ip2long($subnet['first_usable']);
        $endLong = ip2long($subnet['last_usable']);

        if ($startLong !== false && $endLong !== false) {
            for ($curr = $startLong; $curr <= $endLong; $curr++) {
                $candidateIp = long2ip($curr);
                if (!isset($this->allocatedIps[$candidateIp])) {
                    $this->allocatedIps[$candidateIp] = $instanceId;
                    return $candidateIp;
                }
            }
        }

        throw new NetworkException("IPAM Subnet Exhausted: No available IPv4 addresses in {$this->cidr}");
    }

    /**
     * Release all IP allocations owned by an instance.
     */
    public function releaseIp(string $instanceId): bool
    {
        $found = false;
        foreach ($this->allocatedIps as $ip => $owner) {
            if ($owner === $instanceId) {
                unset($this->allocatedIps[$ip]);
                $found = true;
            }
        }
        return $found;
    }

    public function isIpAllocated(string $ip): bool
    {
        return isset($this->allocatedIps[$ip]);
    }

    /**
     * @return array<string, string>
     */
    public function getAllocatedIps(): array
    {
        return $this->allocatedIps;
    }

    /**
     * Generate an IEEE 802 / OUI-compliant locally-administered MAC address.
     */
    public static function generateMacAddress(string $prefix = '52:54:00'): string
    {
        $randomBytes = random_bytes(3);
        return sprintf(
            '%s:%02x:%02x:%02x',
            rtrim($prefix, ':'),
            ord($randomBytes[0]),
            ord($randomBytes[1]),
            ord($randomBytes[2])
        );
    }
}
