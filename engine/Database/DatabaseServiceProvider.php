<?php
declare(strict_types=1);

namespace Oshim\Database;

use Oshim\Container\Container;
use Oshim\Container\ServiceProviderInterface;
use Oshim\Database\Migrations\MigrationRepository;
use Oshim\Database\Migrations\Migrator;

class DatabaseServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->singleton(ConnectionManager::class, function () {
            return ConnectionManager::getInstance();
        });

        $container->singleton(Connection::class, function (Container $c) {
            return $c->get(ConnectionManager::class)->connection();
        });

        $container->singleton(MigrationRepository::class, function (Container $c) {
            return new MigrationRepository($c->get(Connection::class));
        });

        $container->singleton(Migrator::class, function (Container $c) {
            return new Migrator(
                $c->get(MigrationRepository::class),
                $c->get(Connection::class)
            );
        });
    }

    public function boot(Container $container): void
    {
    }
}
