<?php
declare(strict_types=1);

namespace Oshim\Ecosystem;

use Oshim\Container\Container;
use RuntimeException;

/**
 * 👑 Sovereign Plugin Manager
 * 
 * WHY: Handles the OSHIM Ecosystem. It scans the `plugins/` directory
 * and boots native plugins. However, it respects DEVELOPER FREEDOM by
 * allowing strict permission control and easy opt-outs.
 */
class PluginManager
{
    /** @var array<string, PluginInterface> */
    protected array $plugins = [];

    /** @var array<string, bool> */
    protected array $deniedPermissions = [];

    public function __construct(
        protected Container $container,
        protected string $pluginDir
    ) {}

    /**
     * Developer Freedom: The developer can block specific ecosystem behaviors globally.
     * Edge Case: A rogue plugin trying to access 'file.write' can be blocked here.
     */
    public function denyPermission(string $permission): self
    {
        $this->deniedPermissions[$permission] = true;
        return $this;
    }

    /**
     * Discover and load all drop-in plugins from the ecosystem.
     * WHY: No composer needed. Just drop a folder into `plugins/`.
     */
    public function discoverAndLoad(): void
    {
        if (!is_dir($this->pluginDir)) {
            return; // Edge case: No plugins directory exists yet.
        }

        $dirs = array_filter(glob($this->pluginDir . '/*'), 'is_dir');
        
        foreach ($dirs as $dir) {
            $pluginFile = $dir . '/Plugin.php';
            if (is_file($pluginFile)) {
                $class = $this->extractClassFromFile($pluginFile);
                if ($class && class_exists($class)) {
                    $instance = new $class();
                    if ($instance instanceof PluginInterface) {
                        $this->registerPlugin($instance);
                    }
                }
            }
        }
    }

    /**
     * Register a specific plugin, enforcing developer constraints.
     */
    public function registerPlugin(PluginInterface $plugin): void
    {
        $name = $plugin->getName();
        
        // Developer Freedom: Security Check
        foreach ($plugin->getRequestedPermissions() as $perm) {
            if (isset($this->deniedPermissions[$perm])) {
                throw new RuntimeException("Sovereign Security: Plugin [{$name}] requested denied permission [{$perm}].");
            }
        }

        $this->plugins[$name] = $plugin;
        $plugin->register($this->container);
    }

    public function bootAll(): void
    {
        foreach ($this->plugins as $plugin) {
            $plugin->boot($this->container);
        }
    }

    /**
     * Helper to load the class from a pure PHP file safely without Composer PSR-4 overhead.
     * WHY: Maintains zero dependency freedom.
     */
    protected function extractClassFromFile(string $file): ?string
    {
        require_once $file;
        
        // Simple heuristic: Assume the class name matches the directory name + 'Plugin' or is defined in the file.
        // For absolute robustness in zero-dependency, we can parse the tokens.
        $contents = file_get_contents($file);
        if (!$contents) return null;

        $namespace = '';
        $class = '';
        $tokens = token_get_all($contents);
        $count = count($tokens);
        
        for ($i = 0; $i < $count; $i++) {
            if ($tokens[$i][0] === T_NAMESPACE) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($tokens[$j][0] === T_NAME_QUALIFIED || $tokens[$j][0] === T_STRING) {
                        $namespace = $tokens[$j][1];
                        break;
                    }
                }
            }
            if ($tokens[$i][0] === T_CLASS) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($tokens[$j][0] === T_STRING) {
                        $class = $tokens[$j][1];
                        break;
                    }
                }
                break;
            }
        }

        if ($class) {
            return $namespace ? $namespace . '\\' . $class : $class;
        }

        return null;
    }
}
