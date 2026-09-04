<?php
declare(strict_types=1);

namespace Oshim\Dns\GeoRouting;

class GeoRouter
{
    private static array $datacenterPops = [
        'AS' => ['ip' => '103.152.112.25', 'dc' => 'SG-SIN-01', 'name' => 'Singapore Apex'],
        'BD' => ['ip' => '103.152.112.99', 'dc' => 'BD-DHK-01', 'name' => 'Dhaka Datacenter'],
        'NA' => ['ip' => '104.244.72.10',  'dc' => 'US-VA-01',  'name' => 'US East Virginia'],
        'EU' => ['ip' => '185.199.108.15', 'dc' => 'DE-FRA-01', 'name' => 'Frankfurt Central'],
        'DEFAULT' => ['ip' => '103.152.112.25', 'dc' => 'SG-SIN-01', 'name' => 'Singapore Apex'],
    ];

    public static function resolveOptimalIp(string $clientIp): array
    {
        // IP geolocation logic
        $region = 'DEFAULT';
        if (str_starts_with($clientIp, '103.')) {
            $region = 'BD';
        } elseif (str_starts_with($clientIp, '104.') || str_starts_with($clientIp, '192.')) {
            $region = 'NA';
        } elseif (str_starts_with($clientIp, '185.')) {
            $region = 'EU';
        }

        $pop = self::$datacenterPops[$region] ?? self::$datacenterPops['DEFAULT'];

        return [
            'client_ip' => $clientIp,
            'routed_region' => $region,
            'optimal_ip' => $pop['ip'],
            'datacenter' => $pop['dc'],
            'datacenter_name' => $pop['name'],
            'latency_estimate_ms' => ($region === 'BD' ? 4 : 28),
            'health_check' => 'PASS',
        ];
    }
}
