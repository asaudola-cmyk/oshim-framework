<?php
declare(strict_types=1);

namespace Oshim\Virtualization\Network;

use Oshim\Virtualization\Exceptions\NetworkException;

/**
 * Dual-ended Virtual Ethernet (veth) pair manager for container network isolation.
 */
class VethManager
{
    /**
     * Create a virtual ethernet pair (host end + guest end).
     */
    public function createVethPair(string $hostIfName, string $guestIfName): bool
    {
        $cmd = "ip link add name {$hostIfName} type veth peer name {$guestIfName} 2>&1";
        @exec($cmd, $out, $ret);
        return $ret === 0;
    }

    /**
     * Move the guest end of the veth pair into the container's network namespace (by PID).
     */
    public function moveInterfaceToNetns(string $guestIfName, int $targetPid): bool
    {
        $cmd = "ip link set dev {$guestIfName} netns {$targetPid} 2>&1";
        @exec($cmd, $out, $ret);
        return $ret === 0;
    }

    /**
     * Configure IP, MAC, MTU, and default route on the guest interface.
     */
    public function configureGuestInterface(string $ifName, string $ipAddress, string $netmask, string $gateway, ?string $mac = null): bool
    {
        if ($mac !== null) {
            @exec("ip link set dev {$ifName} address {$mac} 2>&1");
        }

        // Compute CIDR mask bits from netmask
        $maskLong = ip2long($netmask);
        $bits = 24;
        if ($maskLong !== false) {
            $bits = substr_count(decbin($maskLong), '1');
        }

        @exec("ip addr add {$ipAddress}/{$bits} dev {$ifName} 2>&1");
        @exec("ip link set dev {$ifName} up 2>&1");
        @exec("ip link set dev lo up 2>&1");
        @exec("ip route add default via {$gateway} dev {$ifName} 2>&1");

        return true;
    }

    /**
     * Delete the veth pair by removing the host end.
     */
    public function deleteVethPair(string $hostIfName): bool
    {
        @exec("ip link delete {$hostIfName} 2>&1", $out, $ret);
        return $ret === 0;
    }
}
