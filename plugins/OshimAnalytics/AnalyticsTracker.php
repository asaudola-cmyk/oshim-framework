<?php
declare(strict_types=1);

namespace Plugins\OshimAnalytics;

use Oshim\Database\ConnectionManager;
use Throwable;

/**
 * Native Tracker using DB query builder.
 * WHY: Avoids ORM overhead for high-throughput tracking.
 */
class AnalyticsTracker
{
    public function logHit(string $path, string $method, string $ip, ?string $userAgent, float $executionTimeMs): void
    {
        try {
            $conn = ConnectionManager::getInstance()->connection();
            $conn->table('oshim_analytics_events')->insert([
                'path' => $path,
                'method' => $method,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'execution_time_ms' => $executionTimeMs,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            // Silently fail to not interrupt user experience for a tracking failure
            error_log("OshimAnalytics Tracking Error: " . $e->getMessage());
        }
    }
}
