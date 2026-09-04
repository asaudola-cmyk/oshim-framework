<?php
declare(strict_types=1);

namespace Oshim\Security\AntiDdos;

class RateLimiterShield
{
    private static array $buckets = [];

    public static function check(string $key, int $maxRequests = 1000, int $decaySeconds = 60): bool
    {
        $now = time();

        if (!isset(self::$buckets[$key])) {
            self::$buckets[$key] = [
                'count' => 1,
                'reset_at' => $now + $decaySeconds,
            ];
            return true;
        }

        if ($now > self::$buckets[$key]['reset_at']) {
            self::$buckets[$key] = [
                'count' => 1,
                'reset_at' => $now + $decaySeconds,
            ];
            return true;
        }

        self::$buckets[$key]['count']++;

        if (self::$buckets[$key]['count'] > $maxRequests) {
            // Trigger automatic temporary IP ban in XdpFilter
            XdpFilter::blockIp($key, 'rate_limit_exceeded_' . $maxRequests . '_rpm');
            return false;
        }

        return true;
    }

    public static function reset(string $key): void
    {
        unset(self::$buckets[$key]);
    }

    public static function flush(): void
    {
        self::$buckets = [];
    }
}
