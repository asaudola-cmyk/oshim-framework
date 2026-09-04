<?php
declare(strict_types=1);

namespace Oshim\Kernel;

use Closure;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use Oshim\Http\Request;

class RouteParameterExtractor
{
    /**
     * Resolve argument list for a handler callable.
     *
     * @param callable|array|string $handler
     * @param Request $request
     * @param array<string, mixed> $params
     * @return list<mixed>
     */
    public static function resolveArgs(callable|array|string $handler, Request $request, array $params): array
    {
        $reflector = null;
        if ($handler instanceof Closure) {
            $reflector = new ReflectionFunction($handler);
        } elseif (is_array($handler) && count($handler) === 2) {
            $reflector = new ReflectionMethod($handler[0], $handler[1]);
        } elseif (is_string($handler) && is_callable($handler)) {
            $reflector = new ReflectionFunction(Closure::fromCallable($handler));
        }

        if ($reflector === null) {
            return [$request, ...$params];
        }

        $args = [];
        $paramsCopy = $params;

        foreach ($reflector->getParameters() as $index => $param) {
            $name = $param->getName();
            $type = $param->getType();
            $typeName = ($type instanceof ReflectionNamedType) ? $type->getName() : null;

            // 1. Inject Request if type-hinted or named $req/$request/$r
            if ($typeName === Request::class
                || is_a($typeName ?? '', Request::class, true)
                || ($index === 0 && in_array($name, ['req', 'request', 'r'], true))
            ) {
                $args[] = $request;
                continue;
            }

            // 2. Resolve value: by parameter name first, then positional fallback
            $val = null;
            if (array_key_exists($name, $paramsCopy)) {
                $val = $paramsCopy[$name];
                unset($paramsCopy[$name]);
            } elseif (!empty($paramsCopy)) {
                $val = array_shift($paramsCopy);
            } elseif ($param->isDefaultValueAvailable()) {
                $val = $param->getDefaultValue();
            } elseif ($param->allowsNull()) {
                $val = null;
            }

            // 3. Safe scalar type coercion
            if ($val !== null && $typeName !== null) {
                if ($typeName === 'int' && is_numeric($val)) {
                    $val = (int)$val;
                } elseif ($typeName === 'float' && is_numeric($val)) {
                    $val = (float)$val;
                } elseif ($typeName === 'bool') {
                    $val = in_array(strtolower((string)$val), ['1', 'true', 'yes', 'on'], true);
                } elseif ($typeName === 'string') {
                    $val = (string)$val;
                }
            }

            $args[] = $val;
        }

        return $args;
    }

    /**
     * Clean and URL-decode extracted route parameters.
     *
     * @param array<string, string> $rawParams
     * @return array<string, string>
     */
    public static function cleanParams(array $rawParams): array
    {
        $cleaned = [];
        foreach ($rawParams as $k => $v) {
            if (is_string($k)) {
                $cleaned[$k] = urldecode($v);
            }
        }
        return $cleaned;
    }
}
