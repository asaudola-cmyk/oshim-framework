<?php
declare(strict_types=1);

namespace Oshim\Dns;

use Oshim\Container\ContainerInterface;
use Oshim\Container\ServiceProviderInterface;
use Oshim\Dns\Resolver\AuthoritativeResolver;
use Oshim\Dns\Server\DnsServer;
use Oshim\Dns\Server\DnsServerConfig;
use Oshim\Dns\Zone\MemoryZoneRepository;
use Oshim\Dns\Zone\ZoneRepositoryInterface;

/**
 * Service Provider for Authoritative DNS Engine.
 */
class DnsServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(ZoneRepositoryInterface::class, function () {
            return new MemoryZoneRepository();
        });

        $container->singleton(AuthoritativeResolver::class, function (ContainerInterface $c) {
            return new AuthoritativeResolver($c->get(ZoneRepositoryInterface::class));
        });

        $container->singleton(DnsServerConfig::class, function () {
            return new DnsServerConfig();
        });

        $container->singleton(DnsServer::class, function (ContainerInterface $c) {
            return new DnsServer(
                $c->get(ZoneRepositoryInterface::class),
                $c->get(DnsServerConfig::class),
                $c->get(AuthoritativeResolver::class)
            );
        });
    }

    public function boot(ContainerInterface $container): void
    {
    }
}
