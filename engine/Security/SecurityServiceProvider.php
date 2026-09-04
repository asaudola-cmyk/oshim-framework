<?php
declare(strict_types=1);

namespace Oshim\Security;

use Oshim\Container\Container;
use Oshim\Container\ServiceProviderInterface;

class SecurityServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->singleton(RateLimiter::class, function () {
            return new RateLimiter();
        });
    }

    public function boot(Container $container): void
    {
    }
}
