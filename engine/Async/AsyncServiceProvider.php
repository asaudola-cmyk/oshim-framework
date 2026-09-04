<?php
declare(strict_types=1);

namespace Oshim\Async;

use Oshim\Container\Container;
use Oshim\Container\ServiceProviderInterface;

class AsyncServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->singleton(EventLoop::class, function () {
            return EventLoop::getInstance();
        });

        $container->singleton(FiberScheduler::class, function (Container $c) {
            return FiberScheduler::getInstance();
        });
    }

    public function boot(Container $container): void
    {
    }
}
