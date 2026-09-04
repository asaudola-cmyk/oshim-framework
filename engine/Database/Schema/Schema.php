<?php
declare(strict_types=1);

namespace Oshim\Database\Schema;

use Oshim\Database\Connection;
use Oshim\Database\ConnectionManager;
use Closure;

class Schema
{
    public static function connection(?string $name = null): SchemaBuilder
    {
        $conn = ConnectionManager::getInstance()->connection($name);
        return new SchemaBuilder($conn);
    }

    public static function create(string $table, Closure $callback): void
    {
        static::connection()->create($table, $callback);
    }

    public static function table(string $table, Closure $callback): void
    {
        static::connection()->table($table, $callback);
    }

    public static function drop(string $table): void
    {
        static::connection()->drop($table);
    }

    public static function dropIfExists(string $table): void
    {
        static::connection()->dropIfExists($table);
    }

    public static function rename(string $from, string $to): void
    {
        static::connection()->rename($from, $to);
    }

    public static function hasTable(string $table): bool
    {
        return static::connection()->hasTable($table);
    }

    public static function hasColumn(string $table, string $column): bool
    {
        return static::connection()->hasColumn($table, $column);
    }

    public static function getColumnListing(string $table): array
    {
        return static::connection()->getColumnListing($table);
    }
}

class SchemaBuilder
{
    public function __construct(protected Connection $connection)
    {
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    public function create(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint($table, $callback);
        $blueprint->creating = true;

        $compiler = $this->connection->getDriver()->getSchemaCompiler();
        $statements = $compiler->compileCreate($blueprint);

        foreach ($statements as $statement) {
            $this->connection->statement($statement);
        }
    }

    public function table(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint($table, $callback);

        $compiler = $this->connection->getDriver()->getSchemaCompiler();
        $statements = $compiler->compileTable($blueprint);

        foreach ($statements as $statement) {
            $this->connection->statement($statement);
        }
    }

    public function drop(string $table): void
    {
        $sql = $this->connection->getDriver()->getSchemaCompiler()->compileDrop($table);
        $this->connection->statement($sql);
    }

    public function dropIfExists(string $table): void
    {
        $sql = $this->connection->getDriver()->getSchemaCompiler()->compileDropIfExists($table);
        $this->connection->statement($sql);
    }

    public function rename(string $from, string $to): void
    {
        $sql = $this->connection->getDriver()->getSchemaCompiler()->compileRename($from, $to);
        $this->connection->statement($sql);
    }

    public function hasTable(string $table): bool
    {
        $sql = $this->connection->getDriver()->getSchemaCompiler()->compileTableExists($table);
        $result = $this->connection->selectOne($sql);
        return $result !== null;
    }

    public function hasColumn(string $table, string $column): bool
    {
        $columns = $this->getColumnListing($table);
        return in_array($column, $columns, true);
    }

    public function getColumnListing(string $table): array
    {
        $sql = $this->connection->getDriver()->getSchemaCompiler()->compileColumnExists($table, '');
        $results = $this->connection->select($sql);

        $cols = [];
        foreach ($results as $row) {
            if (isset($row['name'])) {
                $cols[] = $row['name'];
            }
        }
        return $cols;
    }
}
