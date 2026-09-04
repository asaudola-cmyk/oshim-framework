<?php
declare(strict_types=1);

namespace Oshim\Kernel;

use Closure;
use Oshim\Http\Request;
use Oshim\Http\Response;

/**
 * MicroKernel: Ultra-Fast Zero-Cost Autonomous Micro-Engine.
 * Operates without booting service providers, configuration files, or database connections.
 * Latency: < 0.1ms | Memory: < 1MB.
 */
class MicroKernel
{
    /** @var array<string, array<string, Closure|array|string>> */
    private array $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => [],
        'PATCH' => [],
        'OPTIONS' => [],
    ];

    /** @var list<Closure> */
    private array $middlewares = [];

    public function __construct()
    {
    }

    public static function create(): self
    {
        return new self();
    }

    public function get(string $path, Closure|array|string $handler): self
    {
        return $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, Closure|array|string $handler): self
    {
        return $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, Closure|array|string $handler): self
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    public function delete(string $path, Closure|array|string $handler): self
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    public function addRoute(string $method, string $path, Closure|array|string $handler): self
    {
        $normalizedMethod = strtoupper($method);
        $normalizedPath = '/' . trim($path, '/');
        if ($normalizedPath === '//') {
            $normalizedPath = '/';
        }
        $this->routes[$normalizedMethod][$normalizedPath] = $handler;
        return $this;
    }

    public function middleware(Closure $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    public function handle(?Request $request = null): Response
    {
        $request ??= Request::capture();
        $method = $request->method();
        $path = '/' . trim($request->path(), '/');
        if ($path === '//') {
            $path = '/';
        }

        // Execute global middlewares
        foreach ($this->middlewares as $middleware) {
            $res = $middleware($request);
            if ($res instanceof Response) {
                return $res;
            }
        }

        $routesForMethod = $this->routes[$method] ?? [];

        // Exact match
        if (isset($routesForMethod[$path])) {
            $request->setRouteParams([]);
            return $this->dispatch($routesForMethod[$path], $request, []);
        }

        // Parametric match (/user/{id} and /catalog/{category?})
        foreach ($routesForMethod as $routePattern => $handler) {
            $regex = preg_replace('/\/\{([a-zA-Z0-9_]+)\?\}/', '(?:/(?P<$1>[^/]+))?', $routePattern);
            $regex = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $regex);
            $regex = '#^' . str_replace('#', '\#', $regex) . '$#';

            if (preg_match($regex, $path, $matches)) {
                $rawParams = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $params = RouteParameterExtractor::cleanParams($rawParams);
                $request->setRouteParams($params);
                return $this->dispatch($handler, $request, $params);
            }
        }

        return Response::json([
            'error' => 'Not Found',
            'method' => $method,
            'path' => $path,
            'kernel' => 'OSHIM_MICRO_KERNEL_V1',
        ], 404);
    }

    private function dispatch(Closure|array|string $handler, Request $request, array $params): Response
    {
        $result = null;

        if ($handler instanceof Closure || is_callable($handler)) {
            $args = RouteParameterExtractor::resolveArgs($handler, $request, $params);
            $result = $handler(...$args);
        } elseif (is_array($handler) && count($handler) === 2) {
            [$class, $action] = $handler;
            $instance = is_string($class) ? new $class() : $class;
            $args = RouteParameterExtractor::resolveArgs([$instance, $action], $request, $params);
            $result = $instance->$action(...$args);
        } elseif (is_string($handler)) {
            $result = $handler;
        }

        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result) || is_object($result)) {
            return Response::json($result);
        }

        return Response::html((string)$result);
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }
}
