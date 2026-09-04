<?php
declare(strict_types=1);

namespace Oshim\Tests\Harness;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Isolated SQLite in-memory and ephemeral database sandbox with migration and transactional isolation.
 */
class DatabaseSandbox
{
    private static ?PDO $activePdo = null;
    private ?PDO $pdo = null;
    private string $dbPath;
    private bool $isDiskFile = false;
    private bool $inTransaction = false;

    public function __construct(string $dbPath = ':memory:')
    {
        $this->dbPath = $dbPath;
    }

    public static function getActivePdo(): ?PDO
    {
        return self::$activePdo;
    }

    public static function setActivePdo(?PDO $pdo): void
    {
        self::$activePdo = $pdo;
    }

    public function initialize(?string $path = null): self
    {
        if ($path !== null) {
            $this->dbPath = $path;
        }

        $this->isDiskFile = ($this->dbPath !== ':memory:' && !str_starts_with($this->dbPath, 'file::memory:'));

        if ($this->isDiskFile) {
            $dir = dirname($this->dbPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
        }

        $dsn = 'sqlite:' . $this->dbPath;
        $this->pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->configurePragmas();
        self::$activePdo = $this->pdo;

        // Register in framework container if available
        $this->bindToFramework();

        return $this;
    }

    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->initialize();
        }
        return $this->pdo;
    }

    public function configurePragmas(): void
    {
        if ($this->pdo === null) {
            return;
        }

        $this->pdo->exec("PRAGMA foreign_keys = ON;");
        $this->pdo->exec("PRAGMA busy_timeout = 5000;");

        if (!$this->isDiskFile) {
            $this->pdo->exec("PRAGMA journal_mode = MEMORY;");
            $this->pdo->exec("PRAGMA synchronous = OFF;");
        } else {
            $this->pdo->exec("PRAGMA journal_mode = WAL;");
            $this->pdo->exec("PRAGMA synchronous = NORMAL;");
        }
    }

    public function runMigrations(?string $migrationsDir = null): int
    {
        $pdo = $this->getPdo();

        $pdo->exec("CREATE TABLE IF NOT EXISTS _migrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            migration VARCHAR(255) NOT NULL UNIQUE,
            batch INTEGER NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $dir = $migrationsDir ?? dirname(__DIR__, 2) . '/database/migrations';
        if (!is_dir($dir)) {
            return 0;
        }

        $files = glob($dir . '/*.php') ?: [];
        sort($files);

        $executedCount = 0;
        $stmt = $pdo->prepare("SELECT migration FROM _migrations");
        $stmt->execute();
        $alreadyRun = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (in_array($name, $alreadyRun, true)) {
                continue;
            }

            $migration = require $file;
            if ($migration instanceof \Oshim\Database\Migrations\Migration) {
                $migration->up();
            } else {
                $className = $this->resolveMigrationClass($file);
                if (class_exists($className)) {
                    $instance = new $className($pdo);
                    if (method_exists($instance, 'up')) {
                        $instance->up();
                    }
                }
            }

            $insertStmt = $pdo->prepare("INSERT INTO _migrations (migration, batch) VALUES (?, 1)");
            $insertStmt->execute([$name]);
            $executedCount++;
        }

        return $executedCount;
    }

    public function rollbackMigrations(?int $steps = 1): int
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("SELECT migration FROM _migrations ORDER BY id DESC LIMIT ?");
        $stmt->bindValue(1, $steps, PDO::PARAM_INT);
        $stmt->execute();
        $migrationsToRollback = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $dir = dirname(__DIR__, 2) . '/database/migrations';
        $rolledBack = 0;

        foreach ($migrationsToRollback as $migrationName) {
            $file = $dir . '/' . $migrationName . '.php';
            if (file_exists($file)) {
                require_once $file;
                $className = $this->resolveMigrationClass($file);
                if (class_exists($className)) {
                    $instance = new $className($pdo);
                    if (method_exists($instance, 'down')) {
                        $instance->down();
                    }
                }
            }

            $delStmt = $pdo->prepare("DELETE FROM _migrations WHERE migration = ?");
            $delStmt->execute([$migrationName]);
            $rolledBack++;
        }

        return $rolledBack;
    }

    public function beginTransaction(): void
    {
        if ($this->pdo !== null && !$this->inTransaction) {
            $this->pdo->beginTransaction();
            $this->inTransaction = true;
        }
    }

    public function rollBack(): void
    {
        if ($this->pdo !== null && $this->inTransaction) {
            try {
                $this->pdo->rollBack();
            } catch (Throwable $e) {
                // Catch inactive transaction
            }
            $this->inTransaction = false;
        }
    }

    public function commit(): void
    {
        if ($this->pdo !== null && $this->inTransaction) {
            $this->pdo->commit();
            $this->inTransaction = false;
        }
    }

    public function seed(string|callable $seeder): void
    {
        $pdo = $this->getPdo();
        if (is_callable($seeder)) {
            $seeder($pdo);
            return;
        }

        if (class_exists($seeder)) {
            $instance = new $seeder($pdo);
            if (method_exists($instance, 'run')) {
                $instance->run();
            }
        }
    }

    public function seedBaseline(): void
    {
        $pdo = $this->getPdo();

        // Baseline schema creation if not already created by migrations
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL DEFAULT 'client',
            kyc_status VARCHAR(50) NOT NULL DEFAULT 'unverified',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS instances (
            id VARCHAR(64) PRIMARY KEY,
            user_id INTEGER NOT NULL,
            name VARCHAR(255) NOT NULL,
            state VARCHAR(50) NOT NULL DEFAULT 'STOPPED',
            vcpu INTEGER NOT NULL DEFAULT 1,
            ram_mb INTEGER NOT NULL DEFAULT 1024,
            disk_gb INTEGER NOT NULL DEFAULT 20,
            os VARCHAR(100) NOT NULL DEFAULT 'ubuntu-22.04',
            ipv4 VARCHAR(45) NULL,
            ipv6 VARCHAR(100) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS domains (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            domain VARCHAR(255) NOT NULL UNIQUE,
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            nameservers TEXT NULL,
            expires_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS invoices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            invoice_number VARCHAR(100) NOT NULL UNIQUE,
            amount_cents INTEGER NOT NULL,
            currency VARCHAR(10) NOT NULL DEFAULT 'USD',
            status VARCHAR(50) NOT NULL DEFAULT 'unpaid',
            due_date DATE NOT NULL,
            paid_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Insert SuperAdmin user
        $passwordHash = password_hash('password123', PASSWORD_ARGON2ID);
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO users (id, name, email, password, role, kyc_status) VALUES (1, 'Super Admin', 'superadmin@oshim.cloud', ?, 'superadmin', 'verified')");
        $stmt->execute([$passwordHash]);

        // Insert Test Client
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO users (id, name, email, password, role, kyc_status) VALUES (2, 'Jane Client', 'client@oshim.cloud', ?, 'client', 'verified')");
        $stmt->execute([$passwordHash]);
    }

    public function assertHas(string $table, array $criteria, string $message = ''): void
    {
        Assert::assertDatabaseHas($table, $criteria, $this->getPdo(), $message);
    }

    public function assertMissing(string $table, array $criteria, string $message = ''): void
    {
        Assert::assertDatabaseMissing($table, $criteria, $this->getPdo(), $message);
    }

    public function assertCount(string $table, int $expectedCount, array $criteria = []): void
    {
        Assert::recordAssertion();
        $conditions = [];
        $values = [];
        foreach ($criteria as $col => $val) {
            $conditions[] = "{$col} = ?";
            $values[] = $val;
        }
        $whereSql = count($conditions) > 0 ? " WHERE " . implode(' AND ', $conditions) : '';
        $sql = "SELECT COUNT(*) AS total FROM {$table}{$whereSql}";

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($values);
        $count = (int)$stmt->fetchColumn();

        if ($count !== $expectedCount) {
            throw new AssertionException(
                "Expected table '{$table}' to have {$expectedCount} matching records, but found {$count}."
            );
        }
    }

    public function fetchOne(string $table, array $criteria = []): ?array
    {
        $conditions = [];
        $values = [];
        foreach ($criteria as $col => $val) {
            $conditions[] = "{$col} = ?";
            $values[] = $val;
        }
        $whereSql = count($conditions) > 0 ? " WHERE " . implode(' AND ', $conditions) : '';
        $sql = "SELECT * FROM {$table}{$whereSql} LIMIT 1";

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($values);
        $res = $stmt->fetch();
        return $res !== false ? $res : null;
    }

    public function fetchAll(string $table, array $criteria = []): array
    {
        $conditions = [];
        $values = [];
        foreach ($criteria as $col => $val) {
            $conditions[] = "{$col} = ?";
            $values[] = $val;
        }
        $whereSql = count($conditions) > 0 ? " WHERE " . implode(' AND ', $conditions) : '';
        $sql = "SELECT * FROM {$table}{$whereSql}";

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($values);
        return $stmt->fetchAll();
    }

    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function exec(string $sql): int
    {
        return $this->getPdo()->exec($sql);
    }

    public function truncate(string ...$tables): void
    {
        $pdo = $this->getPdo();
        $pdo->exec("PRAGMA foreign_keys = OFF;");
        foreach ($tables as $table) {
            $pdo->exec("DELETE FROM {$table};");
        }
        $pdo->exec("PRAGMA foreign_keys = ON;");
    }

    public function cleanup(): void
    {
        $this->rollBack();
        $this->pdo = null;
        self::$activePdo = null;

        if ($this->isDiskFile && file_exists($this->dbPath)) {
            @unlink($this->dbPath);
            @unlink($this->dbPath . '-wal');
            @unlink($this->dbPath . '-shm');
        }
    }

    private function resolveMigrationClass(string $file): string
    {
        $basename = basename($file, '.php');
        $clean = preg_replace('/^[0-9_]+/', '', $basename);
        $words = str_replace('_', ' ', (string)$clean);
        return str_replace(' ', '', ucwords($words));
    }

    private function bindToFramework(): void
    {
        if (class_exists('\\Oshim\\Container\\Container') && method_exists('\\Oshim\\Container\\Container', 'getInstance')) {
            try {
                $container = \Oshim\Container\Container::getInstance();
                $container->singleton(PDO::class, fn() => $this->pdo);
                $container->singleton('db', fn() => $this->pdo);
            } catch (Throwable $e) {
                // Ignore
            }
        }

        if (class_exists('\\Oshim\\Database\\ConnectionManager')) {
            try {
                \Oshim\Database\ConnectionManager::getInstance()->addConnection([
                    'driver'   => 'sqlite',
                    'database' => ':memory:',
                    'pdo'      => $this->pdo,
                ], 'default');
                \Oshim\Database\ConnectionManager::getInstance()->setDefaultConnection('default');
            } catch (Throwable $e) {
                // Ignore
            }
        }
    }
}
