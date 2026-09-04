<?php
declare(strict_types=1);

namespace Oshim\Database\Schema\Compilers;

use Oshim\Database\Schema\Blueprint;
use Oshim\Database\Schema\ColumnDefinition;
use Oshim\Database\Schema\ForeignKeyDefinition;

class PostgresSchemaCompiler implements SchemaCompilerInterface
{
    public function compileCreate(Blueprint $blueprint): array
    {
        $table = $this->wrap($blueprint->getTable());
        $columns = [];
        $foreignKeys = [];
        $indexes = [];
        $primaryKeys = [];

        foreach ($blueprint->getColumns() as $column) {
            $columns[] = $this->compileColumn($column);
            if ($column->primary) {
                $primaryKeys[$column->name] = $this->wrap($column->name);
            }
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
                foreach ($command['columns'] as $col) {
                    $primaryKeys[$col] = $this->wrap($col);
                }
            }
        }

        if (!empty($primaryKeys)) {
            $columns[] = "PRIMARY KEY (" . implode(', ', array_values($primaryKeys)) . ")";
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
            if ($column->change) {
                $statements[] = "ALTER TABLE {$table} ALTER COLUMN {$this->wrap($column->name)} TYPE " . $this->getType($column);
                if ($column->nullable) {
                    $statements[] = "ALTER TABLE {$table} ALTER COLUMN {$this->wrap($column->name)} DROP NOT NULL";
                } else {
                    $statements[] = "ALTER TABLE {$table} ALTER COLUMN {$this->wrap($column->name)} SET NOT NULL";
                }
                if ($column->hasDefault || $column->default !== null) {
                    $statements[] = "ALTER TABLE {$table} ALTER COLUMN {$this->wrap($column->name)} SET DEFAULT " . $this->formatDefault($column->default);
                }
            } else {
                $colSql = $this->compileColumn($column);
                $statements[] = "ALTER TABLE {$table} ADD COLUMN {$colSql}";
            }
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
            } elseif ($command['type'] === 'dropUnique') {
                $statements[] = "ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$this->wrap($command['name'])}";
            } elseif ($command['type'] === 'dropColumn') {
                foreach ($command['columns'] as $col) {
                    $statements[] = "ALTER TABLE {$table} DROP COLUMN IF EXISTS {$this->wrap($col)}";
                }
            } elseif ($command['type'] === 'dropForeign') {
                $statements[] = "ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$this->wrap($command['name'])}";
            } elseif ($command['type'] === 'dropPrimary') {
                $pkName = $command['name'] ?? ($blueprint->getTable() . '_pkey');
                $statements[] = "ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$this->wrap($pkName)}";
            } elseif ($command['type'] === 'foreign') {
                /** @var ForeignKeyDefinition $def */
                $def = $command['definition'];
                $statements[] = "ALTER TABLE {$table} ADD " . $this->compileForeignKey($def);
            } elseif ($command['type'] === 'primary') {
                $cols = implode(', ', array_map([$this, 'wrap'], $command['columns']));
                $statements[] = "ALTER TABLE {$table} ADD PRIMARY KEY ({$cols})";
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
        return "SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename = " . $this->quote($table);
    }

    public function compileColumnExists(string $table, string $column): string
    {
        $sql = "SELECT column_name AS name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = " . $this->quote($table);
        if ($column !== '') {
            $sql .= " AND column_name = " . $this->quote($column);
        }
        return $sql;
    }

    protected function compileColumn(ColumnDefinition $column): string
    {
        if ($column->autoIncrement) {
            $type = ($column->type === 'bigInteger' || $column->type === 'bigIncrements') ? 'BIGSERIAL' : 'SERIAL';
            return "{$this->wrap($column->name)} {$type}";
        }

        $sql = "{$this->wrap($column->name)} " . $this->getType($column);

        if ($column->nullable) {
            $sql .= " NULL";
        } else {
            $sql .= " NOT NULL";
        }

        if ($column->hasDefault || $column->default !== null) {
            $sql .= " DEFAULT " . $this->formatDefault($column->default);
        }

        if ($column->unique && !$column->primary) {
            $sql .= " UNIQUE";
        }

        return $sql;
    }

    protected function getType(ColumnDefinition $column): string
    {
        if ($column->autoIncrement) {
            return match ($column->type) {
                'bigInteger', 'bigIncrements' => 'BIGSERIAL',
                default => 'SERIAL',
            };
        }

        return match ($column->type) {
            'increments' => 'SERIAL',
            'bigIncrements' => 'BIGSERIAL',
            'integer', 'unsignedInteger' => 'INTEGER',
            'tinyInteger', 'smallInteger' => 'SMALLINT',
            'mediumInteger' => 'INTEGER',
            'bigInteger', 'unsignedBigInteger' => 'BIGINT',
            'string' => "VARCHAR(" . ($column->length ?? 255) . ")",
            'text', 'mediumText', 'longText' => 'TEXT',
            'boolean' => 'BOOLEAN',
            'float' => 'REAL',
            'double' => 'DOUBLE PRECISION',
            'decimal' => "NUMERIC(" . ($column->precision ?? 10) . ", " . ($column->scale ?? 2) . ")",
            'date' => 'DATE',
            'time' => 'TIME WITHOUT TIME ZONE',
            'dateTime', 'timestamp' => 'TIMESTAMP WITHOUT TIME ZONE',
            'json' => 'JSONB',
            'binary' => 'BYTEA',
            'uuid' => 'UUID',
            default => 'VARCHAR(255)',
        };
    }

    protected function compileForeignKey(ForeignKeyDefinition $def): string
    {
        $cols = implode(', ', array_map([$this, 'wrap'], $def->columns));
        $foreignTable = $this->wrap($def->foreignTable);
        $foreignCols = implode(', ', array_map([$this, 'wrap'], $def->foreignColumns));

        $sql = '';
        if ($def->name !== null && $def->name !== '') {
            $sql .= "CONSTRAINT {$this->wrap($def->name)} ";
        }
        $sql .= "FOREIGN KEY ({$cols}) REFERENCES {$foreignTable} ({$foreignCols})";

        if ($def->onDelete !== null) {
            $sql .= " ON DELETE {$def->onDelete}";
        }
        if ($def->onUpdate !== null) {
            $sql .= " ON UPDATE {$def->onUpdate}";
        }

        return $sql;
    }

    protected function formatDefault(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }
        if (is_numeric($value)) {
            return (string)$value;
        }
        if ($value === 'CURRENT_TIMESTAMP') {
            return 'CURRENT_TIMESTAMP';
        }
        return $this->quote((string)$value);
    }

    public function wrap(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }

    public function quote(string $value): string
    {
        return "'" . addslashes($value) . "'";
    }

    protected function createIndexName(string $type, string $table, array $columns): string
    {
        return strtolower("{$table}_" . implode('_', $columns) . "_{$type}");
    }
}
