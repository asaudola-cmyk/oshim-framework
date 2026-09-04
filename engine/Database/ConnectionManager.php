<?php
declare(strict_types=1);

namespace Oshim\Database;

use Oshim\Database\Drivers\DriverInterface;
use Oshim\Database\Drivers\SqliteDriver;
use Oshim\Database\Drivers\MysqlDriver;
use Oshim\Database\Drivers\PostgresDriver;
use InvalidArgumentException;

class ConnectionManager
{
    protected static ?self $instance = null;
    protected array $configs = [];
    protected array $connections = [];
    protected string $defaultConnection = 'default';
    protected array $customDrivers = [];

    public static function getInstance(): self
    {
        if (static::$instance === null) {
            static::$instance = new self();
            static::$instance->bootstrapDefaultConfig();
        }
        return static::$instance;
    }

    public static function setInstance(?self $instance): void
    {
        static::$instance = $instance;
    }

    public function bootstrapDefaultConfig(): void
    {
        $cwdStorage = getcwd() ? getcwd() . '/storage/database/oshim.sqlite' : null;
        $dbPath = getenv('DB_DATABASE')
            ?: (defined('OSHIM_STORAGE_PATH') ? OSHIM_STORAGE_PATH . '/database/oshim.sqlite'
            : ($cwdStorage && is_dir(dirname($cwdStorage)) ? $cwdStorage
            : (is_dir(dirname(__DIR__, 2) . '/storage/database') ? dirname(__DIR__, 2) . '/storage/database/oshim.sqlite'
            : sys_get_temp_dir() . '/oshim_app.sqlite')));

        $this->addConnection([
            'driver'   => 'sqlite',
            'database' => $dbPath,
            'prefix'   => '',
        ], 'default');
    }

    public function addConnection(array $config, string $name = 'default'): self
    {
        $this->configs[$name] = $config;
        unset($this->connections[$name]); // Reset cached connection if reconfigured
        return $this;
    }

    public function connection(?string $name = null): Connection
    {
        $name = $name ?? $this->defaultConnection;

        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->makeConnection($name);
        }

        return $this->connections[$name];
    }

    public function reconnect(?string $name = null): Connection
    {
        $name = $name ?? $this->defaultConnection;
        $this->disconnect($name);
        return $this->connection($name);
    }

    public function disconnect(?string $name = null): void
    {
        $name = $name ?? $this->defaultConnection;
        if (isset($this->connections[$name])) {
            $this->connections[$name]->disconnect();
            unset($this->connections[$name]);
        }
    }

    public function getDefaultConnection(): string
    {
        return $this->defaultConnection;
    }

    public function setDefaultConnection(string $name): self
    {
        $this->defaultConnection = $name;
        return $this;
    }

    public function extend(string $driver, callable $resolver): self
    {
        $this->customDrivers[strtolower($driver)] = $resolver;
        return $this;
    }

    protected function makeConnection(string $name): Connection
    {
        if (!isset($this->configs[$name])) {
            throw new InvalidArgumentException("Database connection [{$name}] is not configured.");
        }

        $config = $this->configs[$name];
        $driver = $this->createDriver($config);

        $conn = new Connection($driver, $config, $name);
        if (isset($config['pdo']) && $config['pdo'] instanceof \PDO) {
            $conn->setPdo($config['pdo']);
        }

        return $conn;
    }

    protected function createDriver(array $config): DriverInterface
    {
        $driverName = strtolower($config['driver'] ?? 'sqlite');

        if (isset($this->customDrivers[$driverName])) {
            return ($this->customDrivers[$driverName])($config);
        }

        return match ($driverName) {
            'sqlite'                          => new SqliteDriver(),
            'mysql'                           => new MysqlDriver(),
            'pgsql', 'postgres', 'postgresql' => new PostgresDriver(),
            default                           => throw new InvalidArgumentException("Unsupported database driver [{$driverName}]."),
        };
    }
}
