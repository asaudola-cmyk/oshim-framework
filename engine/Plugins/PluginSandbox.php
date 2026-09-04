<?php
declare(strict_types=1);

namespace Oshim\Plugins;

use RuntimeException;

class PluginSandbox
{
    private array $loadedPlugins = [];
    private array $allowedPermissions = ['database', 'storage', 'ai', 'network'];

    public function registerPlugin(PluginInterface $plugin): array
    {
        $name = $plugin->getName();
        $permissions = $plugin->getPermissions();

        // Validate declared permissions
        foreach ($permissions as $perm) {
            if (!in_array($perm, $this->allowedPermissions, true)) {
                throw new RuntimeException("Plugin '{$name}' requested unauthorized permission: '{$perm}'");
            }
        }

        // Initialize plugin
        $plugin->boot();

        $this->loadedPlugins[$name] = [
            'name' => $name,
            'version' => $plugin->getVersion(),
            'permissions' => $permissions,
            'status' => 'ACTIVE',
            'sandboxed' => true,
        ];

        return $this->loadedPlugins[$name];
    }

    public function getLoadedPlugins(): array
    {
        return $this->loadedPlugins;
    }
}
