<?php
declare(strict_types=1);

namespace Oshim\Virtualization\Network;

/**
 * Kernel IP forwarding, outbound SNAT / MASQUERADE, and inbound DNAT port-forwarding manager.
 */
class NatManager
{
    /**
     * Enable IPv4 packet forwarding in the Linux kernel (/proc/sys/net/ipv4/ip_forward).
     */
    public function enableIpForwarding(): bool
    {
        $file = '/proc/sys/net/ipv4/ip_forward';
        if (file_exists($file) && is_writable($file)) {
            return @file_put_contents($file, '1') !== false;
        }

        @exec("sysctl -w net.ipv4.ip_forward=1 2>&1", $out, $ret);
        return $ret === 0;
    }

    /**
     * Enable outbound NAT (MASQUERADE) for the container bridge subnet.
     */
    public function enableMasquerade(string $subnetCidr = '10.42.0.0/24', string $bridgeName = 'oshim0'): bool
    {
        $rules = [
            "iptables -t nat -A POSTROUTING -s {$subnetCidr} ! -o {$bridgeName} -j MASQUERADE 2>&1",
            "iptables -A FORWARD -i {$bridgeName} ! -o {$bridgeName} -j ACCEPT 2>&1",
            "iptables -A FORWARD -o {$bridgeName} -m state --state RELATED,ESTABLISHED -j ACCEPT 2>&1",
        ];

        foreach ($rules as $rule) {
            @exec($rule);
        }

        return true;
    }

    /**
     * Add inbound DNAT port-forwarding rule (Public IP:Port -> Guest IP:Port).
     */
    public function addPortForward(string $publicIp, int $publicPort, string $guestIp, int $guestPort, string $proto = 'tcp'): bool
    {
        $proto = strtolower($proto);
        $rules = [
            "iptables -t nat -A PREROUTING -d {$publicIp} -p {$proto} --dport {$publicPort} -j DNAT --to-destination {$guestIp}:{$guestPort} 2>&1",
            "iptables -A FORWARD -p {$proto} -d {$guestIp} --dport {$guestPort} -j ACCEPT 2>&1",
        ];

        foreach ($rules as $rule) {
            @exec($rule);
        }

        return true;
    }

    /**
     * Remove inbound DNAT port-forwarding rule.
     */
    public function removePortForward(string $publicIp, int $publicPort, string $guestIp, int $guestPort, string $proto = 'tcp'): bool
    {
        $proto = strtolower($proto);
        $rules = [
            "iptables -t nat -D PREROUTING -d {$publicIp} -p {$proto} --dport {$publicPort} -j DNAT --to-destination {$guestIp}:{$guestPort} 2>&1",
            "iptables -D FORWARD -p {$proto} -d {$guestIp} --dport {$guestPort} -j ACCEPT 2>&1",
        ];

        foreach ($rules as $rule) {
            @exec($rule);
        }

        return true;
    }

    /**
     * Generate standard iptables command strings for inspection/testing.
     *
     * @return array{masquerade: string, forward_out: string, forward_in: string, dnat: string}
     */
    public static function formatRuleStrings(string $subnetCidr, string $bridgeName, string $publicIp, int $publicPort, string $guestIp, int $guestPort, string $proto = 'tcp'): array
    {
        $proto = strtolower($proto);
        return [
            'masquerade'  => "iptables -t nat -A POSTROUTING -s {$subnetCidr} ! -o {$bridgeName} -j MASQUERADE",
            'forward_out' => "iptables -A FORWARD -i {$bridgeName} ! -o {$bridgeName} -j ACCEPT",
            'forward_in'  => "iptables -A FORWARD -o {$bridgeName} -m state --state RELATED,ESTABLISHED -j ACCEPT",
            'dnat'        => "iptables -t nat -A PREROUTING -d {$publicIp} -p {$proto} --dport {$publicPort} -j DNAT --to-destination {$guestIp}:{$guestPort}",
        ];
    }
}
