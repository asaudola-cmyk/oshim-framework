<?php
declare(strict_types=1);

namespace Oshim\Plugins;

interface PluginInterface
{
    /**
     * Unique identifier for the plugin (e.g. 'vendor/payment-nagad' or 'charts/apex-visual')
     */
    public function getName(): string;

    /**
     * Semantic version
     */
    public function getVersion(): string;

    /**
     * Declared permissions: ['database', 'storage', 'ai', 'network']
     * @return list<string>
     */
    public function getPermissions(): array;

    /**
     * Initialize and boot the plugin with OSHIM Kernel
     */
    public function boot(): void;
}
