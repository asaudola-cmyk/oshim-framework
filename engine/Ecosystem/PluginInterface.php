<?php
declare(strict_types=1);

namespace Oshim\Ecosystem;

use Oshim\Container\Container;

/**
 * 👑 Sovereign Plugin Interface
 * 
 * WHY: This defines the contract for OSHIM's native ecosystem.
 * It gives the ecosystem a standard way to boot, while giving the developer
 * the freedom to inspect exactly what permissions a plugin requires.
 */
interface PluginInterface
{
    /**
     * Get the unique name of the plugin (Ecosystem identity).
     */
    public function getName(): string;

    /**
     * Developer Freedom: Plugins must declare what they want to do.
     * The developer can block permissions in the PluginManager.
     * 
     * @return array<string> e.g. ['database.read', 'network.outbound']
     */
    public function getRequestedPermissions(): array;

    /**
     * Register bindings in the container (Ecosystem integration).
     * Developer Freedom: The developer can override these bindings later in their own app.
     */
    public function register(Container $container): void;

    /**
     * Boot the plugin after all services are registered.
     */
    public function boot(Container $container): void;
}
