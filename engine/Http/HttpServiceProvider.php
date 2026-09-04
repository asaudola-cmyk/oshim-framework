<?php
declare(strict_types=1);

namespace Oshim\Http;

use Oshim\Container\Container;
use Oshim\Container\ServiceProviderInterface;
use Oshim\Http\Router\Router;
use Oshim\Http\Middleware\CorsMiddleware;
use Oshim\Http\Middleware\CsrfMiddleware;
use Oshim\Http\Middleware\SessionMiddleware;
use Oshim\Http\Middleware\AuthMiddleware;
use Oshim\Http\Middleware\RbacMiddleware;
use Oshim\Http\Middleware\RateLimitMiddleware;
use Oshim\Http\Middleware\SecurityHeadersMiddleware;
use Oshim\Http\Session\SessionStoreInterface;
use Oshim\Http\Session\EncryptedFileSessionStore;

class HttpServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->singleton(Router::class, function (Container $c) {
            $router = new Router($c);

            // Register default middleware aliases
            $router->aliasMiddleware('cors', CorsMiddleware::class);
            $router->aliasMiddleware('csrf', CsrfMiddleware::class);
            $router->aliasMiddleware('session', SessionMiddleware::class);
            $router->aliasMiddleware('auth', AuthMiddleware::class);
            $router->aliasMiddleware('rbac', RbacMiddleware::class);
            $router->aliasMiddleware('rate_limit', RateLimitMiddleware::class);
            $router->aliasMiddleware('security_headers', SecurityHeadersMiddleware::class);

            return $router;
        });

        $container->singleton(SessionStoreInterface::class, function (Container $c) {
            $storagePath = defined('OSHIM_STORAGE_PATH') ? OSHIM_STORAGE_PATH . '/sessions' : dirname(__DIR__, 2) . '/storage/sessions';
            $appKey = $_ENV['APP_KEY'] ?? 'oshim_default_secret_key_32_bytes_len';
            return new EncryptedFileSessionStore($storagePath, $appKey);
        });
    }

    public function boot(Container $container): void
    {
    }
}
