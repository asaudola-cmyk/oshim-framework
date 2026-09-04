<?php
declare(strict_types=1);

namespace Oshim;

require_once __DIR__ . '/Autoloader.php';

use Oshim\Container\Container;
use Oshim\Http\HttpServiceProvider;
use Oshim\Database\DatabaseServiceProvider;
use Oshim\Async\AsyncServiceProvider;
use Oshim\Security\SecurityServiceProvider;
use Oshim\Ui\UiServiceProvider;
use Oshim\Dns\DnsServiceProvider;
use Oshim\Epp\EppServiceProvider;
use Oshim\Virtualization\VirtualizationServiceProvider;
use Throwable;
use ErrorException;

/**
 * Framework Bootstrap Kernel.
 * Initializes error handling, loads environment, prepares storage, and configures container.
 */
final class Bootstrap
{
    private static ?Container $app = null;
    private static string $basePath = '';
    private static bool $booted = false;

    /**
     * Boot the framework kernel and return the configured Container.
     */
    public static function boot(?string $basePath = null): Container
    {
        if (self::$booted && self::$app !== null) {
            return self::$app;
        }

        self::$basePath = $basePath !== null ? rtrim($basePath, '/\\') : dirname(__DIR__);

        // 1. Register Autoloader if not already active
        if (!Autoloader::isRegistered()) {
            Autoloader::register([], self::$basePath);
        }

        // 2. Load Environment Variables
        self::loadEnvironment(self::$basePath);

        // 3. Register Error & Exception Handlers
        self::registerErrorHandlers();

        // 4. Ensure Storage Directories Exist
        self::ensureDirectories(self::$basePath);

        // 5. Initialize DI Container
        $container = Container::getInstance();
        $container->instance(Container::class, $container);

        // Bind Base Paths
        $container->bind('path.base', fn() => self::$basePath);
        $container->bind('path.storage', fn() => self::$basePath . '/storage');
        $container->bind('path.app', fn() => self::$basePath . '/app');
        $container->bind('path.public', fn() => self::$basePath . '/public');
        $container->bind('path.database', fn() => self::$basePath . '/database');

        if (!defined('OSHIM_BASE_PATH')) {
            define('OSHIM_BASE_PATH', self::$basePath);
        }
        if (!defined('OSHIM_STORAGE_PATH')) {
            define('OSHIM_STORAGE_PATH', self::$basePath . '/storage');
        }

        // 6. Register Core Service Providers
        $container->register(new SecurityServiceProvider());
        $container->register(new DatabaseServiceProvider());
        $container->register(new HttpServiceProvider());
        $container->register(new AsyncServiceProvider());
        $container->register(new UiServiceProvider());
        $container->register(new DnsServiceProvider());
        $container->register(new EppServiceProvider());
        $container->register(new VirtualizationServiceProvider());

        
        // --- SOVEREIGN ECOSYSTEM (PLUGINS) ---
        // WHY: Load drop-in plugins automatically, but allow developers to block them.
        $pluginManager = new \Oshim\Ecosystem\PluginManager($container, self::$basePath . "/plugins");
        $container->instance(\Oshim\Ecosystem\PluginManager::class, $pluginManager);
        $pluginManager->discoverAndLoad();
        $pluginManager->bootAll();

        $container->boot();

        self::$app = $container;
        self::$booted = true;

        return $container;
    }

    /**
     * Load environment variables from .env file into $_ENV and putenv().
     */
    public static function loadEnvironment(string $basePath): void
    {
        $envFile = $basePath . '/.env';
        if (!is_file($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Strip quotes if wrapped
                if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }

                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
    }

    /**
     * Register PHP error, exception, and shutdown handlers.
     */
    public static function registerErrorHandlers(): void
    {
        error_reporting(E_ALL);

        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    /**
     * Handle PHP errors by converting them into ErrorException.
     */
    public static function handleError(int $level, string $message, string $file = '', int $line = 0): bool
    {
        if (!(error_reporting() & $level)) {
            return false;
        }

        throw new ErrorException($message, 0, $level, $file, $line);
    }

    /**
     * Render uncaught exceptions.
     */
    public static function handleException(Throwable $e): void
    {
        $isCli = PHP_SAPI === 'cli';
        $logPath = self::$basePath ? self::$basePath . '/storage/logs/error.log' : 'error.log';

        $logMsg = sprintf("[%s] %s in %s:%d\nStack trace:\n%s\n",
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );

        @file_put_contents($logPath, $logMsg, FILE_APPEND);

        if ($isCli) {
            fwrite(STDERR, "\033[31;1mUncaught Exception: " . $e->getMessage() . "\033[0m\n");
            fwrite(STDERR, "in " . $e->getFile() . ":" . $e->getLine() . "\n");
            fwrite(STDERR, $e->getTraceAsString() . "\n");
            exit(1);
        } else {
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'error' => 'Internal Server Error',
                'message' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true' ? $e->getMessage() : 'An error occurred.',
                'file' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true' ? $e->getFile() . ':' . $e->getLine() : null,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit(1);
        }
    }

    /**
     * Catch fatal errors on process shutdown.
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            self::handleException(new ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            ));
        }
    }

    /**
     * Ensure required storage directories exist.
     */
    public static function ensureDirectories(string $basePath): void
    {
        $dirs = [
            $basePath . '/storage',
            $basePath . '/storage/database',
            $basePath . '/storage/logs',
            $basePath . '/storage/sessions',
            $basePath . '/storage/cache',
            $basePath . '/storage/framework',
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
        }

        $sessionDir = $basePath . '/storage/sessions';
        if (is_dir($sessionDir) && is_writable($sessionDir)) {
            @session_save_path($sessionDir);
        }
    }

    /**
     * Get the configured base path.
     */
    public static function getBasePath(): string
    {
        return self::$basePath;
    }

    /**
     * Reset kernel state (for testing).
     */
    public static function reset(): void
    {
        self::$booted = false;
        self::$app = null;
    }
}
