<?php
declare(strict_types=1);

namespace Oshim\Http\Middleware;

use Oshim\Http\Request;
use Oshim\Http\Response;
use Oshim\Http\Exceptions\TooManyRequestsHttpException;
use Oshim\Security\RateLimiter;
use Closure;

class RateLimitMiddleware implements MiddlewareInterface
{
    protected static ?RateLimiter $limiter = null;

    public function __construct()
    {
        self::$limiter ??= new RateLimiter();
    }

    public function handle(Request $request, Closure $next, int|string $maxAttempts = 60, int|string $decayMinutes = 1): Response
    {
        $maxAttempts = (int)$maxAttempts;
        $decaySeconds = (int)$decayMinutes * 60;

        $key = 'rate:' . sha1($request->getClientIp() . '|' . $request->getPath());

        if (self::$limiter->tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = self::$limiter->availableIn($key);
            if ($request->wantsJson()) {
                $response = Response::json(['error' => 'Too Many Requests.'], 429);
            } else {
                throw new TooManyRequestsHttpException($retryAfter);
            }
            return $response
                ->withHeader('X-RateLimit-Limit', (string)$maxAttempts)
                ->withHeader('X-RateLimit-Remaining', '0')
                ->withHeader('Retry-After', (string)$retryAfter);
        }

        $attempts = self::$limiter->hit($key, $decaySeconds);
        $remaining = max(0, $maxAttempts - $attempts);

        $response = $next($request);

        return $response
            ->withHeader('X-RateLimit-Limit', (string)$maxAttempts)
            ->withHeader('X-RateLimit-Remaining', (string)$remaining);
    }
}
