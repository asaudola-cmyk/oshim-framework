<?php
declare(strict_types=1);

namespace Plugins\OshimAnalytics;

use Oshim\Http\Request;
use Oshim\Http\Response;
use Closure;

/**
 * Middleware to intercept and measure requests.
 */
class AnalyticsMiddleware implements \Oshim\Http\Middleware\MiddlewareInterface
{
    public function __construct(protected AnalyticsTracker $tracker) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        
        /** @var Response $response */
        $response = $next($request);
        
        $executionTimeMs = (microtime(true) - $startTime) * 1000;
        
        // Edge Case: Check IP reliably even behind load balancers
        $ip = $request->server('HTTP_X_FORWARDED_FOR') 
              ?? $request->server('REMOTE_ADDR') 
              ?? '127.0.0.1';
              
        if (str_contains((string)$ip, ',')) {
            $ip = trim(explode(',', (string)$ip)[0]);
        }

        $userAgent = $request->server('HTTP_USER_AGENT');
        $path = $request->getPathInfo();
        $method = $request->getMethod();

        // Track in background if async/swoole is used, otherwise inline
        $this->tracker->logHit($path, $method, (string)$ip, $userAgent, $executionTimeMs);

        return $response;
    }
}
