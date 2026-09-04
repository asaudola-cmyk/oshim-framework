<?php
declare(strict_types=1);

namespace Oshim\Database\Drivers;

use Oshim\Database\Query\Compilers\CompilerInterface;
use Oshim\Database\Query\Compilers\SqliteCompiler;
use Oshim\Database\Schema\Compilers\SchemaCompilerInterface;
use Oshim\Database\Schema\Compilers\SqliteSchemaCompiler;
use PDO;

class SqliteDriver implements DriverInterface
{
    protected ?CompilerInterface $compiler = null;
    protected ?SchemaCompilerInterface $schemaCompiler = null;

    public function getName(): string
    {
        return 'sqlite';
    }

    public function connect(array $config): PDO
    {
        $database = $config['database'] ?? ':memory:';

        if ($database !== ':memory:') {
            $directory = dirname($database);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
        }

        $dsn = "sqlite:{$database}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => true,
            PDO::ATTR_TIMEOUT            => $config['timeout'] ?? 5,
        ];

        $pdo = new PDO($dsn, null, null, $options);

        // Performance & Integrity PRAGMAs
        $pdo->exec("PRAGMA journal_mode = WAL;");
        $pdo->exec("PRAGMA synchronous = NORMAL;");
        $pdo->exec("PRAGMA foreign_keys = ON;");
        $pdo->exec("PRAGMA busy_timeout = 5000;");

        return $pdo;
    }

    public function getCompiler(): CompilerInterface
    {
        return $this->compiler ??= new SqliteCompiler();
    }

    public function getSchemaCompiler(): SchemaCompilerInterface
    {
        return $this->schemaCompiler ??= new SqliteSchemaCompiler();
    }
}
