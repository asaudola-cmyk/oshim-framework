<?php
declare(strict_types=1);

namespace Oshim\Http\Middleware;

use Oshim\Container\Container;
use Oshim\Http\Request;
use Oshim\Http\Response;
use Closure;
use RuntimeException;

class Pipeline
{
    protected Request $passable;
    /** @var list<string|object|Closure> */
    protected array $pipes = [];

    public function __construct(protected ?Container $container = null)
    {
        $this->container ??= Container::getInstance();
    }

    public function send(Request $request): static
    {
        $this->passable = $request;
        return $this;
    }

    public function through(array $pipes): static
    {
        $this->pipes = array_values($pipes);
        return $this;
    }

    public function then(Closure $destination): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            function (Closure $next, mixed $pipe) {
                return function (Request $request) use ($next, $pipe) {
                    return $this->resolvePipe($pipe, $request, $next);
                };
            },
            $destination
        );

        return $pipeline($this->passable);
    }

    protected function resolvePipe(mixed $pipe, Request $request, Closure $next): Response
    {
        // 1. Direct Closure
        if ($pipe instanceof Closure) {
            return $pipe($request, $next);
        }

        // 2. Class instance implementing MiddlewareInterface
        if ($pipe instanceof MiddlewareInterface) {
            return $pipe->handle($request, $next);
        }

        // 3. String specification with parameters, e.g. "rbac:admin,superadmin" or "rate_limit:60,1"
        if (is_string($pipe)) {
            $name = $pipe;
            $params = [];

            if (str_contains($pipe, ':')) {
                [$name, $paramStr] = explode(':', $pipe, 2);
                $params = explode(',', $paramStr);
            }

            $instance = $this->container->make($name);

            if ($instance instanceof MiddlewareInterface) {
                return $instance->handle($request, $next, ...$params);
            }

            if (is_callable([$instance, 'handle'])) {
                return $instance->handle($request, $next, ...$params);
            }
        }

        if (is_callable($pipe)) {
            return $pipe($request, $next);
        }

        throw new RuntimeException("Invalid middleware pipe provided in pipeline.");
    }
}
