<?php
declare(strict_types=1);

namespace Oshim\Virtualization\Network;

use RuntimeException;

/**
 * In-memory simulated NAT router and port forwarding lookup table for testing and non-root environments.
 */
class SimulatedNatRouter
{
    /** @var array<string, array{public_ip: string, public_port: int, guest_ip: string, guest_port: int, proto: string}> */
    private array $forwardingTable = [];

    public function reset(): void
    {
        $this->forwardingTable = [];
    }

    /**
     * Add a port forwarding rule and return its lookup key.
     */
    public function addPortForward(string $publicIp, int $publicPort, string $guestIp, int $guestPort, string $proto = 'tcp'): string
    {
        $proto = strtolower($proto);
        $key = "{$proto}:{$publicIp}:{$publicPort}";

        if (isset($this->forwardingTable[$key])) {
            $existing = $this->forwardingTable[$key];
            throw new RuntimeException("Port collision: {$key} is already mapped to {$existing['guest_ip']}:{$existing['guest_port']}");
        }

        $this->forwardingTable[$key] = [
            'public_ip'   => $publicIp,
            'public_port' => $publicPort,
            'guest_ip'    => $guestIp,
            'guest_port'  => $guestPort,
            'proto'       => $proto,
        ];

        return $key;
    }

    /**
     * Remove a port forwarding rule.
     */
    public function removePortForward(string $publicIp, int $publicPort, string $proto = 'tcp'): bool
    {
        $proto = strtolower($proto);
        $key = "{$proto}:{$publicIp}:{$publicPort}";

        if (isset($this->forwardingTable[$key])) {
            unset($this->forwardingTable[$key]);
            return true;
        }

        return false;
    }

    /**
     * Resolve the internal destination of an incoming packet.
     *
     * @return array{public_ip: string, public_port: int, guest_ip: string, guest_port: int, proto: string}|null
     */
    public function resolveDestination(string $publicIp, int $publicPort, string $proto = 'tcp'): ?array
    {
        $proto = strtolower($proto);
        $key = "{$proto}:{$publicIp}:{$publicPort}";
        return $this->forwardingTable[$key] ?? null;
    }

    /**
     * @return array<string, array{public_ip: string, public_port: int, guest_ip: string, guest_port: int, proto: string}>
     */
    public function getAllPortForwards(): array
    {
        return $this->forwardingTable;
    }
}
