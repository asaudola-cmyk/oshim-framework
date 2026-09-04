<?php
declare(strict_types=1);

namespace Oshim\Http\Router;

use Oshim\Http\Request;
use Oshim\Http\Response;
use Oshim\Container\Container;
use Closure;
use ReflectionFunction;
use ReflectionMethod;

class Route
{
    /** @var list<string> */
    protected array $methods = [];
    protected string $path;
    protected mixed $action;
    protected ?string $name = null;
    /** @var list<string> */
    protected array $middlewares = [];
    /** @var array<string, string> */
    protected array $wheres = [];
    /** @var array<string, mixed> */
    protected array $defaults = [];
    protected ?string $compiledRegex = null;
    /** @var list<string> */
    protected array $paramNames = [];

    public function __construct(array|string $methods, string $path, mixed $action)
    {
        $this->methods = array_map('strtoupper', (array)$methods);
        if (in_array('GET', $this->methods, true) && !in_array('HEAD', $this->methods, true)) {
            $this->methods[] = 'HEAD';
        }

        $this->path = '/' . trim($path, '/');
        if ($this->path !== '/') {
            $this->path = rtrim($this->path, '/');
        }

        $this->action = $action;
    }

    public function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function middleware(mixed ...$middlewares): static
    {
        foreach ($middlewares as $mw) {
            if (is_array($mw)) {
                $this->middlewares = array_merge($this->middlewares, $mw);
            } else {
                $this->middlewares[] = $mw;
            }
        }
        return $this;
    }

    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    public function where(string|array $name, ?string $pattern = null): static
    {
        if (is_array($name)) {
            foreach ($name as $k => $v) {
                $this->wheres[$k] = $v;
            }
        } elseif ($pattern !== null) {
            $this->wheres[$name] = $pattern;
        }
        $this->compiledRegex = null; // Invalidate compiled cache
        return $this;
    }

    public function whereNumber(string ...$names): static
    {
        foreach ($names as $name) {
            $this->where($name, '[0-9]+');
        }
        return $this;
    }

    public function whereAlpha(string ...$names): static
    {
        foreach ($names as $name) {
            $this->where($name, '[a-zA-Z]+');
        }
        return $this;
    }

    public function whereAlphaNumeric(string ...$names): static
    {
        foreach ($names as $name) {
            $this->where($name, '[a-zA-Z0-9]+');
        }
        return $this;
    }

    public function whereUuid(string ...$names): static
    {
        foreach ($names as $name) {
            $this->where($name, '[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}');
        }
        return $this;
    }

    public function default(string $name, mixed $value): static
    {
        $this->defaults[$name] = $value;
        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getAction(): mixed
    {
        return $this->action;
    }

    public function isStatic(): bool
    {
        return !str_contains($this->path, '{') && !str_contains($this->path, '*');
    }

    /**
     * Compile route pattern into a PCRE regular expression.
     */
    public function compile(): string
    {
        if ($this->compiledRegex !== null) {
            return $this->compiledRegex;
        }

        $pattern = $this->path;
        $this->paramNames = [];

        // 1. Wildcard routes: /*filepath or *
        if (str_contains($pattern, '*')) {
            $pattern = preg_replace_callback('/\*(?<name>[a-zA-Z0-9_]+)?/', function ($matches) {
                $name = !empty($matches['name']) ? $matches['name'] : 'wildcard';
                $this->paramNames[] = $name;
                return '(?P<' . $name . '>.*)';
            }, $pattern);
        }

        // 2. Optional parameters: /{param?}
        $pattern = preg_replace_callback('/\/\{(?<name>[a-zA-Z0-9_]+)\?\}/', function ($matches) {
            $name = $matches['name'];
            $this->paramNames[] = $name;
            $constraint = $this->wheres[$name] ?? '[^/]+';
            return '(?:/(?P<' . $name . '>' . $constraint . '))?';
        }, $pattern);

        // 3. Required parameters: {param}
        $pattern = preg_replace_callback('/\{(?<name>[a-zA-Z0-9_]+)\}/', function ($matches) {
            $name = $matches['name'];
            $this->paramNames[] = $name;
            $constraint = $this->wheres[$name] ?? '[^/]+';
            return '(?P<' . $name . '>' . $constraint . ')';
        }, $pattern);

        $this->compiledRegex = '#^' . $pattern . '$#u';
        return $this->compiledRegex;
    }

    /**
     * Check if route matches method and path.
     */
    public function matches(string $method, string $path, array &$extractedParams = []): bool
    {
        if (!in_array(strtoupper($method), $this->methods, true)) {
            return false;
        }

        $cleanPath = '/' . trim($path, '/');
        if ($cleanPath !== '/') {
            $cleanPath = rtrim($cleanPath, '/');
        }

        if ($this->isStatic()) {
            if ($this->path === $cleanPath) {
                $extractedParams = $this->defaults;
                return true;
            }
            return false;
        }

        $regex = $this->compile();
        if (preg_match($regex, $cleanPath, $matches)) {
            $params = $this->defaults;
            foreach ($matches as $key => $value) {
                if (is_string($key) && $value !== '') {
                    $params[$key] = urldecode($value);
                }
            }
            $extractedParams = $params;
            return true;
        }

        return false;
    }

    /**
     * Run the route handler with dependency injection.
     */
    public function run(Request $request, Container $container): Response
    {
        $action = $this->action;
        $parameters = array_merge($request->routeParams(), [
            'request' => $request,
            Request::class => $request,
        ]);

        $result = $container->call($action, $parameters);

        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result) || is_object($result)) {
            return Response::json($result);
        }

        if (is_string($result) || is_numeric($result) || $result === null) {
            return Response::html((string)$result);
        }

        return Response::make((string)$result);
    }
}
