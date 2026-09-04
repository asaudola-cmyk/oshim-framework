<?php
declare(strict_types=1);

namespace Oshim\Http\Router;

use Oshim\Container\Container;
use Oshim\Http\Request;
use Oshim\Http\Response;
use Oshim\Http\Middleware\Pipeline;
use Oshim\Http\Middleware\MiddlewareInterface;
use Oshim\Http\Exceptions\HttpException;
use Closure;
use InvalidArgumentException;

class Router
{
    /** @var array<string, array<string, Route>> staticRoutes[VERB][PATH] => Route */
    protected array $staticRoutes = [];
    /** @var list<Route> */
    protected array $dynamicRoutes = [];
    /** @var array<string, Route> */
    protected array $namedRoutes = [];
    /** @var list<RouteGroup> */
    protected array $groupStack = [];
    /** @var array<string, string> */
    protected array $middlewareAliases = [];
    /** @var list<string|MiddlewareInterface|Closure> */
    protected array $globalMiddlewares = [];

    public function __construct(protected ?Container $container = null)
    {
        $this->container ??= Container::getInstance();
    }

    // --- Route Definition Verbs ---
    public function get(string $path, mixed $action): Route
    {
        return $this->addRoute(['GET', 'HEAD'], $path, $action);
    }

    public function post(string $path, mixed $action): Route
    {
        return $this->addRoute('POST', $path, $action);
    }

    public function put(string $path, mixed $action): Route
    {
        return $this->addRoute('PUT', $path, $action);
    }

    public function delete(string $path, mixed $action): Route
    {
        return $this->addRoute('DELETE', $path, $action);
    }

    public function patch(string $path, mixed $action): Route
    {
        return $this->addRoute('PATCH', $path, $action);
    }

    public function options(string $path, mixed $action): Route
    {
        return $this->addRoute('OPTIONS', $path, $action);
    }

    public function any(string $path, mixed $action): Route
    {
        return $this->addRoute(['GET', 'HEAD', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'], $path, $action);
    }

    public function match(array $methods, string $path, mixed $action): Route
    {
        return $this->addRoute($methods, $path, $action);
    }

    /**
     * Add route with group prefixes and middleware applied.
     */
    protected function addRoute(array|string $methods, string $path, mixed $action): Route
    {
        $currentGroup = $this->hasGroup() ? $this->getLastGroup() : null;

        if ($currentGroup !== null) {
            // Apply prefix
            $prefix = rtrim($currentGroup->getPrefix(), '/');
            $childPath = '/' . ltrim($path, '/');
            $fullPath = $prefix . ($childPath === '/' ? '' : $childPath);
            if ($fullPath === '') {
                $fullPath = '/';
            }

            // Namespace action if controller string
            if (is_string($action) && $currentGroup->getNamespace() !== null && !str_starts_with($action, '\\')) {
                $action = rtrim($currentGroup->getNamespace(), '\\') . '\\' . $action;
            }

            $route = new Route($methods, $fullPath, $action);

            // Apply group middlewares
            if (!empty($currentGroup->getMiddlewares())) {
                $route->middleware($currentGroup->getMiddlewares());
            }

            // Apply group wheres
            if (!empty($currentGroup->getWheres())) {
                $route->where($currentGroup->getWheres());
            }

            // Apply group name prefix (as)
            if ($currentGroup->getAs() !== null) {
                // Will be applied when name() is called or we store it
            }
        } else {
            $route = new Route($methods, $path, $action);
        }

        // Register route into index
        if ($route->isStatic()) {
            foreach ($route->getMethods() as $method) {
                $this->staticRoutes[$method][$route->getPath()] = $route;
            }
        } else {
            $this->dynamicRoutes[] = $route;
        }

        return $route;
    }

    // --- Groups & Middlewares ---
    public function group(array $attributes, callable $routes): static
    {
        if ($this->hasGroup()) {
            $group = RouteGroup::merge($this->getLastGroup(), $attributes);
        } else {
            $group = new RouteGroup(
                prefix: '/' . trim($attributes['prefix'] ?? '', '/'),
                middlewares: (array)($attributes['middleware'] ?? $attributes['middlewares'] ?? []),
                as: $attributes['as'] ?? null,
                namespace: $attributes['namespace'] ?? null,
                wheres: (array)($attributes['where'] ?? $attributes['wheres'] ?? [])
            );
        }

        $this->groupStack[] = $group;

        $routes($this);

        array_pop($this->groupStack);

        return $this;
    }

    protected function hasGroup(): bool
    {
        return !empty($this->groupStack);
    }

    protected function getLastGroup(): RouteGroup
    {
        return end($this->groupStack);
    }

    public function aliasMiddleware(string $alias, string $class): static
    {
        $this->middlewareAliases[$alias] = $class;
        return $this;
    }

    public function use(string|MiddlewareInterface|Closure ...$middlewares): static
    {
        foreach ($middlewares as $mw) {
            $this->globalMiddlewares[] = $mw;
        }
        return $this;
    }

    public function nameRoute(string $name, Route $route): static
    {
        $this->namedRoutes[$name] = $route;
        return $this;
    }

    /**
     * Dispatch HTTP request through global & route middlewares to target action.
     */
    public function dispatch(Request $request): Response
    {
        try {
            $matched = RouteMatcher::match(
                $request->getMethod(),
                $request->getPath(),
                $this->staticRoutes,
                $this->dynamicRoutes
            );

            /** @var Route $route */
            $route = $matched['route'];
            $params = $matched['params'];

            $request->setRouteParams($params);

            // Collect middlewares
            $pipes = $this->globalMiddlewares;

            foreach ($route->getMiddlewares() as $mw) {
                $resolved = $this->resolveMiddlewareName($mw);
                $pipes[] = $resolved;
            }

            return (new Pipeline($this->container))
                ->send($request)
                ->through($pipes)
                ->then(function (Request $req) use ($route) {
                    return $route->run($req, $this->container);
                });
        } catch (HttpException $e) {
            if ($request->wantsJson() || $request->isAjax()) {
                return Response::json([
                    'error'   => $e->getMessage(),
                    'code'    => $e->getStatusCode(),
                    'errors'  => method_exists($e, 'getErrors') ? $e->getErrors() : null,
                ], $e->getStatusCode(), $e->getHeaders());
            }

            return Response::html(
                "<h1>{$e->getStatusCode()} {$e->getMessage()}</h1>",
                $e->getStatusCode(),
                $e->getHeaders()
            );
        }
    }

    /**
     * Resolve middleware alias or parameters.
     */
    protected function resolveMiddlewareName(string|object $middleware): mixed
    {
        if (!is_string($middleware)) {
            return $middleware;
        }

        $name = $middleware;
        $params = '';

        if (str_contains($middleware, ':')) {
            [$name, $params] = explode(':', $middleware, 2);
        }

        if (isset($this->middlewareAliases[$name])) {
            $className = $this->middlewareAliases[$name];
            return $params !== '' ? "{$className}:{$params}" : $className;
        }

        return $middleware;
    }

    /**
     * Generate URL for a named route.
     */
    public function route(string $name, array $parameters = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new InvalidArgumentException("Named route [{$name}] is not registered.");
        }

        $route = $this->namedRoutes[$name];
        $path = $route->getPath();

        foreach ($parameters as $key => $val) {
            if (str_contains($path, "{" . $key . "}")) {
                $path = str_replace("{" . $key . "}", (string)$val, $path);
                unset($parameters[$key]);
            } elseif (str_contains($path, "{" . $key . "?}")) {
                $path = str_replace("{" . $key . "?}", (string)$val, $path);
                unset($parameters[$key]);
            }
        }

        // Clean any remaining optional parameters
        $path = preg_replace('/\/\{[a-zA-Z0-9_]+\?\}/', '', $path);

        if (!empty($parameters)) {
            $path .= '?' . http_build_query($parameters);
        }

        return $path;
    }

    public function getRoutes(): array
    {
        $all = [];
        foreach ($this->staticRoutes as $verb => $routes) {
            foreach ($routes as $route) {
                $all[] = $route;
            }
        }
        foreach ($this->dynamicRoutes as $route) {
            $all[] = $route;
        }
        return array_values(array_unique($all, SORT_REGULAR));
    }
}
