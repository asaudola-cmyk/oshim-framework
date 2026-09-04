<?php
declare(strict_types=1);

namespace Oshim\Http\Router;

use Oshim\Http\Exceptions\NotFoundHttpException;
use Oshim\Http\Exceptions\MethodNotAllowedHttpException;

class RouteMatcher
{
    /**
     * Match a request against static and dynamic route collections.
     *
     * @param string $method
     * @param string $path
     * @param array<string, array<string, Route>> $staticRoutes
     * @param list<Route> $dynamicRoutes
     * @throws NotFoundHttpException
     * @throws MethodNotAllowedHttpException
     * @return array{route: Route, params: array<string, string>}
     */
    public static function match(
        string $method,
        string $path,
        array $staticRoutes,
        array $dynamicRoutes
    ): array {
        $method = strtoupper($method);
        $cleanPath = '/' . trim($path, '/');
        if ($cleanPath !== '/') {
            $cleanPath = rtrim($cleanPath, '/');
        }

        // 1. Fast O(1) static route lookup
        if (isset($staticRoutes[$method][$cleanPath])) {
            return [
                'route'  => $staticRoutes[$method][$cleanPath],
                'params' => [],
            ];
        }

                // ADVANCED OPTIMIZATION: APCu Route Index Caching
        // WHY: Bypasses O(N) linear regex matching for dynamic routes on subsequent hits.
        $apcuEnabled = function_exists('apcu_fetch') && ini_get('apc.enabled');
        $cacheKey = "oshim_route_idx_" . md5($method . $cleanPath);
        
        if ($apcuEnabled) {
            $success = false;
            $cachedIndex = apcu_fetch($cacheKey, $success);
            if ($success && isset($dynamicRoutes[$cachedIndex])) {
                $params = [];
                if ($dynamicRoutes[$cachedIndex]->matches($method, $cleanPath, $params)) {
                    return [
                        'route'  => $dynamicRoutes[$cachedIndex],
                        'params' => $params,
                    ];
                }
            }
        }

        // 2. Dynamic regex route matching (Linear Fallback)
        $params = [];
        foreach ($dynamicRoutes as $index => $route) {
            if ($route->matches($method, $cleanPath, $params)) {
                if ($apcuEnabled) {
                    apcu_store($cacheKey, $index, 3600); // Cache route index for 1 hour
                }
                return [
                    'route'  => $route,
                    'params' => $params,
                ];
            }
        }

        // 3. Check for 405 Method Not Allowed
        $allowedMethods = [];

        // Check static routes for other methods
        foreach ($staticRoutes as $otherMethod => $routes) {
            if ($otherMethod !== $method && isset($routes[$cleanPath])) {
                $allowedMethods = array_merge($allowedMethods, $routes[$cleanPath]->getMethods());
            }
        }

        // Check dynamic routes for other methods
        foreach ($dynamicRoutes as $route) {
            $dummyParams = [];
            foreach ($route->getMethods() as $m) {
                if ($m !== $method && $route->matches($m, $cleanPath, $dummyParams)) {
                    $allowedMethods = array_merge($allowedMethods, $route->getMethods());
                }
            }
        }

        $allowedMethods = array_values(array_unique($allowedMethods));

        if (!empty($allowedMethods)) {
            throw new MethodNotAllowedHttpException($allowedMethods, "Method [{$method}] not allowed for [{$cleanPath}]. Allowed: " . implode(', ', $allowedMethods));
        }

        throw new NotFoundHttpException("Route not found for [{$method}] [{$cleanPath}].");
    }
}
