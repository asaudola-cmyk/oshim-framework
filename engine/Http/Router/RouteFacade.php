<?php
declare(strict_types=1);

namespace Oshim\Http\Router;

use Oshim\Container\Container;
use Closure;

/**
 * 👑 Static Route Facade for Developer Freedom
 */
class RouteFacade
{
    private static ?Router $router = null;

    public static function getRouter(): Router
    {
        if (self::$router === null) {
            $container = Container::getInstance();
            if ($container->has('router')) {
                self::$router = $container->make('router');
            } else {
                self::$router = new Router($container);
                $container->instance('router', self::$router);
            }
        }
        return self::$router;
    }

    public static function setRouter(Router $router): void
    {
        self::$router = $router;
    }

    public static function get(string $path, mixed $action): Route
    {
        return self::getRouter()->get($path, $action);
    }

    public static function post(string $path, mixed $action): Route
    {
        return self::getRouter()->post($path, $action);
    }

    public static function put(string $path, mixed $action): Route
    {
        return self::getRouter()->put($path, $action);
    }

    public static function delete(string $path, mixed $action): Route
    {
        return self::getRouter()->delete($path, $action);
    }

    public static function patch(string $path, mixed $action): Route
    {
        return self::getRouter()->patch($path, $action);
    }

    public static function any(string $path, mixed $action): Route
    {
        return self::getRouter()->any($path, $action);
    }

    public static function group(array $attributes, Closure $callback): void
    {
        self::getRouter()->group($attributes, $callback);
    }

    public static function __callStatic(string $method, array $args): mixed
    {
        return self::getRouter()->$method(...$args);
    }
}
