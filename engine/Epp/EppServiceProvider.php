<?php
declare(strict_types=1);

namespace Oshim\Epp;

use Oshim\Container\ContainerInterface;
use Oshim\Container\ServiceProviderInterface;
use Oshim\Epp\Transport\EppTransportInterface;
use Oshim\Epp\Transport\TlsStreamTransport;

/**
 * Service provider for EPP protocol engine.
 */
class EppServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(EppTransportInterface::class, function () {
            return new TlsStreamTransport();
        });

        $container->singleton(EppClientInterface::class, function (ContainerInterface $c) {
            return new EppClient($c->get(EppTransportInterface::class));
        });

        $container->singleton(EppClient::class, function (ContainerInterface $c) {
            return $c->get(EppClientInterface::class);
        });
    }

    public function boot(ContainerInterface $container): void
    {
    }
}
