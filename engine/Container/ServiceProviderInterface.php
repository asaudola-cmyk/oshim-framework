<?php
declare(strict_types=1);

namespace Oshim\Container;

interface ServiceProviderInterface
{
    /**
     * Register services, singletons, and factory bindings into the container.
     */
    public function register(Container $container): void;

    /**
     * Bootstrap registered services after all providers have completed registration.
     */
    public function boot(Container $container): void;
}
