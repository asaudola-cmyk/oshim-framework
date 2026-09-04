<?php
declare(strict_types=1);

namespace Plugins\OshimMarketplaceDemo;

use Oshim\Ecosystem\PluginInterface;
use Oshim\Container\Container;

class Plugin implements PluginInterface
{
    public function getName(): string
    {
        return 'oshim/marketplace-demo';
    }

    public function getRequestedPermissions(): array
    {
        return [];
    }

    public function register(Container $container): void
    {
        // Bind services here
    }

    public function boot(Container $container): void
    {
        // Boot routes/middleware here
    }
}