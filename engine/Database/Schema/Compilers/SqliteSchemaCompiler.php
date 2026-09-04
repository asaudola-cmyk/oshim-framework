<?php
declare(strict_types=1);

namespace Oshim\Database\Schema\Compilers;

use Oshim\Database\Schema\Blueprint;
use Oshim\Database\Schema\ColumnDefinition;
use Oshim\Database\Schema\ForeignKeyDefinition;

class SqliteSchemaCompiler implements SchemaCompilerInterface
{
    public function compileCreate(Blueprint $blueprint): array
    {
        $table = $this->wrap($blueprint->getTable());
        $columns = [];
        $foreignKeys = [];
        $indexes = [];

        foreach ($blueprint->getColumns() as $column) {
            $columns[] = $this->compileColumn($column);
        }

        foreach ($blueprint->getCommands() as $command) {
            if ($command['type'] === 'foreign') {
                /** @var ForeignKeyDefinition $def */
                $def = $command['definition'];
                $foreignKeys[] = $this->compileForeignKey($def);
            } elseif ($command['type'] === 'unique') {
                $idxName = $command['name'] ?? $this->createIndexName('uniq', $blueprint->getTable(), $command['columns']);
                $cols = implode(', ', array_map([$this, 'wrap'], $command['columns']));
                $indexes[] = "CREATE UNIQUE INDEX IF NOT EXISTS {$this->wrap($idxName)} ON {$table} ({$cols})";
            } elseif ($command['type'] === 'index') {
                $idxName = $command['name'] ?? $this->createIndexName('idx', $blueprint->getTable(), $command['columns']);
                $cols = implode(', ', array_map([$this, 'wrap'], $command['columns']));
                $indexes[] = "CREATE INDEX IF NOT EXISTS {$this->wrap($idxName)} ON {$table} ({$cols})";
            } elseif ($command['type'] === 'primary') {
                $cols = implode(', ', array_map([$this, 'wrap'], $command['columns']));
                $columns[] = "PRIMARY KEY ({$cols})";
            }
        }

        $body = array_merge($columns, $foreignKeys);
        $createSql = "CREATE TABLE {$table} (\n  " . implode(",\n  ", $body) . "\n)";

        return array_merge([$createSql], $indexes);
    }

    public function compileTable(Blueprint $blueprint): array
    {
        $table = $this->wrap($blueprint->getTable());
        $statements = [];

        foreach ($blueprint->getColumns() as $column) {
            $colSql = $this->compileColumn($column);
            $statements[] = "ALTER TABLE {$table} ADD COLUMN {$colSql}";
        }

        foreach ($blueprint->getCommands() as $command) {
            if ($command['type'] === 'index') {
                $idxName = $command['name'] ?? $this->createIndexName('idx', $blueprint->getTable(), $command['columns']);
                $cols = implode(', ', array_map([$this, 'wrap'], $command['columns']));
                $statements[] = "CREATE INDEX IF NOT EXISTS {$this->wrap($idxName)} ON {$table} ({$cols})";
            } elseif ($command['type'] === 'unique') {
                $idxName = $command['name'] ?? $this->createIndexName('uniq', $blueprint->getTable(), $command['columns']);
                $cols = implode(', ', array_map([$this, 'wrap'], $command['columns']));
                $statements[] = "CREATE UNIQUE INDEX IF NOT EXISTS {$this->wrap($idxName)} ON {$table} ({$cols})";
            } elseif ($command['type'] === 'dropIndex') {
                $statements[] = "DROP INDEX IF EXISTS {$this->wrap($command['name'])}";
            }
        }

        return $statements;
    }

    public function compileDrop(string $table): string
    {
        return "DROP TABLE {$this->wrap($table)}";
    }

    public function compileDropIfExists(string $table): string
    {
        return "DROP TABLE IF EXISTS {$this->wrap($table)}";
    }

    public function compileRename(string $from, string $to): string
    {
        return "ALTER TABLE {$this->wrap($from)} RENAME TO {$this->wrap($to)}";
    }

    public function compileTableExists(string $table): string
    {
        return "SELECT name FROM sqlite_master WHERE type = 'table' AND name = " . $this->quote($table);
    }

    public function compileColumnExists(string $table, string $column): string
    {
        return "PRAGMA table_info({$this->wrap($table)})";
    }

    protected function compileColumn(ColumnDefinition $column): string
    {
        $name = $this->wrap($column->name);

        if ($column->autoIncrement) {
            return "{$name} INTEGER PRIMARY KEY AUTOINCREMENT";
        }

        $type = $this->mapType($column);
        $sql = "{$name} {$type}";

        if ($column->primary) {
            $sql .= ' PRIMARY KEY';
        }

        if ($column->unique) {
            $sql .= ' UNIQUE';
        }

        if (!$column->nullable) {
            $sql .= ' NOT NULL';
        }

        if ($column->hasDefault) {
            $sql .= ' DEFAULT ' . $this->formatDefault($column->default);
        }

        return $sql;
    }

    protected function mapType(ColumnDefinition $column): string
    {
        return match ($column->type) {
            'string'                                             => $column->length ? "VARCHAR({$column->length})" : 'VARCHAR(255)',
            'text', 'mediumText', 'longText', 'json'            => 'TEXT',
            'integer', 'tinyInteger', 'smallInteger', 'boolean' => 'INTEGER',
            'bigInteger', 'unsignedBigInteger'                  => 'BIGINT',
            'float', 'double', 'decimal'                        => 'NUMERIC',
            'date'                                              => 'DATE',
            'time'                                              => 'TIME',
            'dateTime', 'timestamp'                             => 'DATETIME',
            default                                             => 'TEXT',
        };
    }

    protected function formatDefault(mixed $default): string
    {
        if ($default === null) {
            return 'NULL';
        }
        if (is_bool($default)) {
            return $default ? '1' : '0';
        }
        if (is_numeric($default)) {
            return (string)$default;
        }
        return $this->quote((string)$default);
    }

    protected function compileForeignKey(ForeignKeyDefinition $def): string
    {
        $cols = implode(', ', array_map([$this, 'wrap'], $def->columns));
        $foreignTable = $this->wrap($def->foreignTable);
        $foreignCols = implode(', ', array_map([$this, 'wrap'], $def->foreignColumns));

        $sql = "FOREIGN KEY ({$cols}) REFERENCES {$foreignTable} ({$foreignCols})";

        if ($def->onDelete !== null) {
            $sql .= " ON DELETE {$def->onDelete}";
        }
        if ($def->onUpdate !== null) {
            $sql .= " ON UPDATE {$def->onUpdate}";
        }

        return $sql;
    }

    protected function createIndexName(string $prefix, string $table, array $columns): string
    {
        return $prefix . '_' . $table . '_' . implode('_', $columns);
    }

    public function wrap(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }

    public function quote(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
