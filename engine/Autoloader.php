<?php
declare(strict_types=1);

namespace Oshim;

/**
 * PSR-4 compliant class autoloader for OSHIM Engine.
 * Operates without Composer or third-party dependencies.
 */
final class Autoloader
{
    /**
     * Namespace prefix to base directories mapping.
     * @var array<string, list<string>>
     */
    private static array $prefixes = [];

    /**
     * Whether the autoloader is currently registered with SPL.
     */
    private static bool $registered = false;

    /**
     * Root directory of the application.
     */
        private static string $baseDir = '';
    
    /** @var array<string, string> */
    private static array $compiledClassMap = [];

    /**
     * Register the PSR-4 autoloader.
     *
     * @param array<string, string|list<string>> $prefixes
     */
    public static function register(array $prefixes = [], ?string $baseDir = null): void
    {
        if (self::$registered) {
            return;
        }

        self::$baseDir = $baseDir !== null ? self::normalizePath($baseDir) : self::normalizePath(dirname(__DIR__));
        $engineDir = self::normalizePath(__DIR__);

        // Default namespace prefixes
        self::addNamespace('Oshim\\', $engineDir);
        self::addNamespace('Oshim\\', self::$baseDir . 'engine');
        self::addNamespace('App\\', self::$baseDir . 'app');
        self::addNamespace('Database\\Seeders\\', self::$baseDir . 'database/seeders');
        self::addNamespace('Database\\', self::$baseDir . 'database');
        self::addNamespace('Tests\\', self::$baseDir . 'tests');
        self::addNamespace('Oshim\\Tests\\', self::$baseDir . 'tests');

        foreach ($prefixes as $prefix => $dirs) {
            if (is_array($dirs)) {
                foreach ($dirs as $dir) {
                    self::addNamespace($prefix, $dir);
                }
            } else {
                self::addNamespace($prefix, $dirs);
            }
        }

        // Auto-load universal helpers
        $helpersFile = $engineDir . 'Support/helpers.php';
        if (file_exists($helpersFile)) {
            require_once $helpersFile;
        }

        spl_autoload_register([self::class, 'loadClass'], true, true);
        self::$registered = true;
    }

    /**
     * Unregister the autoloader from SPL.
     */
    public static function unregister(): void
    {
        if (self::$registered) {
            spl_autoload_unregister([self::class, 'loadClass']);
            self::$registered = false;
        }
    }

    /**
     * Whether autoloader is registered.
     */
    public static function isRegistered(): bool
    {
        return self::$registered;
    }

    /**
     * Add a base directory for a namespace prefix.
     */
    public static function addNamespace(string $prefix, string $baseDir, bool $prepend = false): void
    {
        // Normalize namespace prefix with trailing backslash
        $prefix = trim($prefix, '\\') . '\\';
        $normalizedDir = self::normalizePath($baseDir);

        if (!isset(self::$prefixes[$prefix])) {
            self::$prefixes[$prefix] = [];
        }

        if ($prepend) {
            array_unshift(self::$prefixes[$prefix], $normalizedDir);
        } else {
            self::$prefixes[$prefix][] = $normalizedDir;
        }

        // Keep unique directories
        self::$prefixes[$prefix] = array_values(array_unique(self::$prefixes[$prefix]));
    }

    /**
     * Load the class file for a given class name.
     */
    public static function loadClass(string $class): bool
    {
        // Reject null bytes and path traversal attempts in class names
        if (str_contains($class, "\0") || str_contains($class, '..')) {
            return false;
        }

        // 1. Global Facades & Aliases for Ultimate Freedom
        $globalAliases = [
            'DB' => \Oshim\Database\DB::class,
            'Route' => \Oshim\Http\Router\RouteFacade::class,
            'AI' => \Oshim\Ai\OshimAi::class,
            'OshimAi' => \Oshim\Ai\OshimAi::class,
            'Cache' => \Oshim\Cache\CacheManager::class,
            'Storage' => \Oshim\Storage\Storage::class,
            'Queue' => \Oshim\Queue\QueueManager::class,
            'Schedule' => \Oshim\Cron\Scheduler::class,
            'Response' => \Oshim\Http\Response::class,
            'Request' => \Oshim\Http\Request::class,
                        'Response' => \Oshim\Http\Response::class,
            'Request' => \Oshim\Http\Request::class,
            'Html' => \Oshim\Ui\Dsl\Html::class,
            // ADVANCED OPTIMIZATION: New Core Facades
            'Log' => \Oshim\Log\Logger::class,
            'Event' => \Oshim\Events\EventDispatcher::class,
            'Validator' => \Oshim\Validation\Validator::class,
        ];

        if (isset($globalAliases[$class])) {
            $target = $globalAliases[$class];
            if (!class_exists($target, false)) {
                self::loadClass($target);
            }
            if (class_exists($target, false)) {
                @class_alias($target, $class, false);
                return true;
            }
        }

        $prefix = $class;

        // 2. Work backwards through registered namespaces
        while (false !== ($pos = strrpos($prefix, '\\'))) {
            $prefix = substr($class, 0, $pos + 1);
            $relativeClass = substr($class, $pos + 1);

            $mappedFile = self::loadMappedFile($prefix, $relativeClass);
            if ($mappedFile !== null) {
                return true;
            }

            $prefix = rtrim($prefix, '\\');
        }

        // 3. Dynamic Custom Directory Auto-Discovery (Total Freedom)
        // If developer created any custom folder (e.g. Shop\, Modules\, Ecommerce\, Src\, Domain\)
        if (str_contains($class, '\\')) {
            $parts = explode('\\', $class);
            $rootNamespace = $parts[0];
            $relativeParts = array_slice($parts, 1);
            $relativeClass = implode(DIRECTORY_SEPARATOR, $relativeParts);

            $candidateDirs = [
                self::$baseDir . strtolower($rootNamespace),
                self::$baseDir . $rootNamespace,
                self::$baseDir . 'src/' . strtolower($rootNamespace),
                self::$baseDir . 'src/' . $rootNamespace,
                self::$baseDir . 'modules/' . strtolower($rootNamespace),
                self::$baseDir . 'modules/' . $rootNamespace,
            ];

            foreach ($candidateDirs as $dir) {
                if (is_dir($dir)) {
                    self::addNamespace($rootNamespace . '\\', $dir);
                    $file = self::normalizePath($dir) . $relativeClass . '.php';
                    if (is_file($file)) {
                        require_once $file;
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Get all registered namespace prefixes.
     *
     * @return array<string, list<string>>
     */
    public static function getNamespaces(): array
    {
        return self::$prefixes;
    }

    /**
     * Reset registered namespace prefixes.
     */
    public static function reset(): void
    {
        self::$prefixes = [];
    }

    /**
     * Internal helper to load mapped file for a namespace prefix.
     */
    private static function loadMappedFile(string $prefix, string $relativeClass): ?string
    {
        if (!isset(self::$prefixes[$prefix])) {
            return null;
        }

        foreach (self::$prefixes[$prefix] as $baseDir) {
            $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

            if (is_file($file)) {
                require_once $file;
                return $file;
            }
        }

        return null;
    }

    /**
     * Normalize directory path with a trailing directory separator.
     */
    private static function normalizePath(string $path): string
    {
        $path = rtrim($path, '/\\');
        return $path . DIRECTORY_SEPARATOR;
    }
}
