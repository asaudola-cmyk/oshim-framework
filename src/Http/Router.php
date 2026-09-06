<?php
declare(strict_types=1);

namespace Oshim\Http;

use Throwable;

/**
 * ⚡ Sovereign Zero-Overhead HTTP Router
 * 
 * WHY: Provides sub-microsecond route matching using direct hash maps and
 * fast regex tokenization. Bypasses the multi-megabyte routing trees of legacy frameworks.
 */
final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $staticRoutes = [];

    /** @var array<string, array<string, callable>> */
    private array $dynamicRoutes = [];

    public function get(string $path, callable $handler): self
    {
        return $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): self
    {
        return $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, callable $handler): self
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    public function delete(string $path, callable $handler): self
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    public function addRoute(string $method, string $path, callable $handler): self
    {
        $method = strtoupper($method);
        if (str_contains($path, '{')) {
            $regex = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);
            $this->dynamicRoutes[$method]["#^{$regex}$#"] = $handler;
        } else {
            $this->staticRoutes[$method][$path] = $handler;
        }
        return $this;
    }

    /**
     * Dispatches request to the registered handler.
     */
    public function dispatch(Request $request): Response
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        // 1. Fast O(1) static lookup
        if (isset($this->staticRoutes[$method][$path])) {
            try {
                $handler = $this->staticRoutes[$method][$path];
                $res = $handler($request);
                return $res instanceof Response ? $res : Response::html((string)$res);
            } catch (Throwable $e) {
                return Response::json([
                    'error' => 'Internal Server Error',
                    'message' => $e->getMessage()
                ], 500);
            }
        }

        // 2. Dynamic regex matching
        if (isset($this->dynamicRoutes[$method])) {
            foreach ($this->dynamicRoutes[$method] as $pattern => $handler) {
                if (preg_match($pattern, $path, $matches)) {
                    try {
                        $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                        $res = $handler($request, ...$params);
                        return $res instanceof Response ? $res : Response::html((string)$res);
                    } catch (Throwable $e) {
                        return Response::json([
                            'error' => 'Internal Server Error',
                            'message' => $e->getMessage()
                        ], 500);
                    }
                }
            }
        }

        // 3. Fallback 404 Not Found
        return Response::json([
            'error' => 'Not Found',
            'path' => $path,
            'method' => $method
        ], 404);
    }
}
