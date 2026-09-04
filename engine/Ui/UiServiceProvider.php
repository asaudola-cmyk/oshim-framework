<?php
declare(strict_types=1);

namespace Oshim\Ui;

use Oshim\Container\Container;
use Oshim\Container\ServiceProviderInterface;
use Oshim\Http\Router\Router;

class UiServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->singleton(ComponentRegistry::class, function () {
            return new ComponentRegistry();
        });

        $container->singleton(DiffEngine::class, function () {
            return new DiffEngine();
        });

        $container->singleton(\Oshim\Ui\LiveDom\MorphEngine::class, function () {
            return new \Oshim\Ui\LiveDom\MorphEngine();
        });

        $container->singleton(\Oshim\Ui\LiveDom\LiveDomManager::class, function (Container $c) {
            $manager = new \Oshim\Ui\LiveDom\LiveDomManager($c->get(\Oshim\Ui\LiveDom\MorphEngine::class));
            \Oshim\Ui\LiveDom\LiveDom::setManager($manager);
            return $manager;
        });

        $container->singleton(UiManager::class, function (Container $c) {
            return new UiManager(
                $c->get(ComponentRegistry::class),
                $c->get(DiffEngine::class)
            );
        });
    }

    public function boot(Container $container): void
    {
        if ($container->has(Router::class)) {
            /** @var Router $router */
            $router = $container->get(Router::class);

            // Register Reactive UI Action Endpoints
            $router->post('/oshim/ui/action', function ($req) use ($container) {
                /** @var UiManager $ui */
                $ui = $container->get(UiManager::class);
                return $ui->handleAction($req);
            });

            $router->post('/__oshim_event', function ($req) use ($container) {
                /** @var UiManager $ui */
                $ui = $container->get(UiManager::class);
                return $ui->handleAction($req);
            });

            // Register Oshim LiveDOM Endpoints
            $router->post('/_oshim/livedom', function ($req) use ($container) {
                /** @var \Oshim\Ui\LiveDom\LiveDomManager $liveDom */
                $liveDom = $container->get(\Oshim\Ui\LiveDom\LiveDomManager::class);
                return $liveDom->handleHttpRequest($req);
            });

            $router->post('/oshim/livedom', function ($req) use ($container) {
                /** @var \Oshim\Ui\LiveDom\LiveDomManager $liveDom */
                $liveDom = $container->get(\Oshim\Ui\LiveDom\LiveDomManager::class);
                return $liveDom->handleHttpRequest($req);
            });

            $router->get('/oshim/ui/sse', function ($req) use ($container) {
                /** @var UiManager $ui */
                $ui = $container->get(UiManager::class);
                return $ui->handleSse($req);
            });
        }
    }
}

