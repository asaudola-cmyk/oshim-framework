<?php
declare(strict_types=1);

/**
 * 👑 OSHIM Sovereign Universal Global Helpers
 * Zero-boilerplate developer freedom functions
 */

if (!function_exists('db')) {
    function db(?string $table = null): \Oshim\Database\Connection|\Oshim\Database\Query\QueryBuilder
    {
        $conn = \Oshim\Database\DB::connection();
        return $table !== null ? $conn->table($table) : $conn;
    }
}

if (!function_exists('ai')) {
    function ai(string $provider = 'auto'): \Oshim\Ai\OshimAi
    {
        return \Oshim\Ai\OshimAi::provider($provider);
    }
}

if (!function_exists('response')) {
    function response(mixed $content = '', int $status = 200, array $headers = []): \Oshim\Http\Response
    {
        if (is_array($content)) {
            return \Oshim\Http\Response::json($content, $status, $headers);
        }
        return new \Oshim\Http\Response($content, $status, $headers);
    }
}

if (!function_exists('request')) {
    function request(): \Oshim\Http\Request
    {
        return \Oshim\Http\Request::capture();
    }
}

if (!function_exists('cache')) {
    function cache(?string $key = null, mixed $default = null): mixed
    {
        $manager = \Oshim\Container\Container::getInstance()->get(\Oshim\Cache\CacheManager::class);
        if ($key === null) {
            return $manager;
        }
        return $manager->get($key, $default);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url, int $status = 302): \Oshim\Http\Response
    {
        return \Oshim\Http\Response::redirect($url, $status);
    }
}

if (!function_exists('app')) {
    function app(?string $abstract = null): mixed
    {
        $container = \Oshim\Container\Container::getInstance();
        return $abstract !== null ? $container->get($abstract) : $container;
    }
}

if (!function_exists('dd')) {
    function dd(...$vars): never
    {
        foreach ($vars as $v) {
            echo '<pre>';
            var_dump($v);
            echo '</pre>';
        }
        exit(1);
    }
}
