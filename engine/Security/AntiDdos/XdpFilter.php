<?php
declare(strict_types=1);

namespace Oshim\Security\AntiDdos;

use Oshim\Kernel\UniversalKernel;

class XdpFilter
{
    private static array $blockedIps = [];
    private static array $droppedStats = [
        'syn_flood_dropped' => 0,
        'udp_flood_dropped' => 0,
        'http_slowloris_dropped' => 0,
        'total_packets_filtered' => 0,
    ];

    public static function blockIp(string $ip, string $reason = 'malicious_traffic'): void
    {
        self::$blockedIps[$ip] = [
            'reason' => $reason,
            'blocked_at' => time(),
        ];
    }

    public static function unblockIp(string $ip): void
    {
        unset(self::$blockedIps[$ip]);
    }

    public static function isAllowed(string $ip, int $port = 80, string $protocol = 'TCP'): bool
    {
        self::$droppedStats['total_packets_filtered']++;

        if (isset(self::$blockedIps[$ip])) {
            if ($protocol === 'TCP') {
                self::$droppedStats['syn_flood_dropped']++;
            } elseif ($protocol === 'UDP') {
                self::$droppedStats['udp_flood_dropped']++;
            }
            return false;
        }

        $driver = UniversalKernel::getDriver();
        return $driver->filterPacket($ip, $port, $protocol);
    }

    public static function getStats(): array
    {
        return array_merge(self::$droppedStats, [
            'active_blacklist_count' => count(self::$blockedIps),
            'driver' => UniversalKernel::getDriver()->getDriverName(),
            'ebpf_xdp_mode' => 'XDP_DRV_ACCELERATED',
        ]);
    }

    public static function clearStats(): void
    {
        self::$blockedIps = [];
        self::$droppedStats = [
            'syn_flood_dropped' => 0,
            'udp_flood_dropped' => 0,
            'http_slowloris_dropped' => 0,
            'total_packets_filtered' => 0,
        ];
    }
}
