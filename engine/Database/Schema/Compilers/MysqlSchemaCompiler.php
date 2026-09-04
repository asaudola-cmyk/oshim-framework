<?php
declare(strict_types=1);

namespace Oshim\Database\Schema\Compilers;

use Oshim\Database\Schema\Blueprint;
use Oshim\Database\Schema\ColumnDefinition;
use Oshim\Database\Schema\ForeignKeyDefinition;

class MysqlSchemaCompiler implements SchemaCompilerInterface
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
                $indexes[] = "UNIQUE KEY {$this->wrap($idxName)} ({$cols})";
            } elseif ($command['type'] === 'index') {
                $idxName = $command['name'] ?? $this->createIndexName('idx', $blueprint->getTable(), $command['columns']);
                $cols = implode(', ', array_map([$this, 'wrap'], $command['columns']));
                $indexes[] = "KEY {$this->wrap($idxName)} ({$cols})";
            } elseif ($command['type'] === 'primary') {
                foreach ($command['columns'] as $col) {
                    $primaryKeys[$col] = $this->wrap($col);
                }
            }
        }

        if (!empty($primaryKeys)) {
            array_unshift($indexes, "PRIMARY KEY (" . implode(', ', array_values($primaryKeys)) . ")");
        }

        $body = array_merge($columns, $indexes, $foreignKeys);
        $createSql = "CREATE TABLE {$table} (\n  " . implode(",\n  ", $body) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        return [$createSql];
    }

    public function compileTable(Blueprint $blueprint): array
    {
        $table = $this->wrap($blueprint->getTable());
        $statements = [];

        foreach ($blueprint->getColumns() as $column) {
            $colSql = $this->compileColumn($column);
            if ($column->change) {
                $statements[] = "ALTER TABLE {$table} MODIFY COLUMN {$colSql}";
            } else {
                $statements[] = "ALTER TABLE {$table} ADD COLUMN {$colSql}";
            }
        }

        foreach ($blueprint->getCommands() as $command) {
            if ($command['type'] === 'index') {
                $idxName = $command['name'] ?? $this->createIndexName('idx', $blueprint->getTable(), $command['columns']);
                $cols = implode(', ', array_map([$this, 'wrap'], $command['columns']));
                $statements[] = "ALTER TABLE {$table} ADD INDEX {$this->wrap($idxName)} ({$cols})";
            } elseif ($command['type'] === 'unique') {
                $idxName = $command['name'] ?? $this->createIndexName('uniq', $blueprint->getTable(), $command['columns']);
                $cols = implode(', ', array_map([$this, 'wrap'], $command['columns']));
                $statements[] = "ALTER TABLE {$table} ADD UNIQUE KEY {$this->wrap($idxName)} ({$cols})";
            } elseif ($command['type'] === 'dropIndex' || $command['type'] === 'dropUnique') {
                $statements[] = "ALTER TABLE {$table} DROP INDEX {$this->wrap($command['name'])}";
            } elseif ($command['type'] === 'dropColumn') {
                foreach ($command['columns'] as $col) {
                    $statements[] = "ALTER TABLE {$table} DROP COLUMN {$this->wrap($col)}";
                }
            } elseif ($command['type'] === 'dropForeign') {
                $statements[] = "ALTER TABLE {$table} DROP FOREIGN KEY {$this->wrap($command['name'])}";
            } elseif ($command['type'] === 'dropPrimary') {
                $statements[] = "ALTER TABLE {$table} DROP PRIMARY KEY";
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
        return "RENAME TABLE {$this->wrap($from)} TO {$this->wrap($to)}";
    }

    public function compileTableExists(string $table): string
    {
        return "SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = " . $this->quote($table);
    }

    public function compileColumnExists(string $table, string $column): string
    {
        $sql = "SELECT COLUMN_NAME AS name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = " . $this->quote($table);
        if ($column !== '') {
            $sql .= " AND COLUMN_NAME = " . $this->quote($column);
        }
        return $sql;
    }

    protected function compileColumn(ColumnDefinition $column): string
    {
        $sql = "{$this->wrap($column->name)} " . $this->getType($column);

        if ($column->autoIncrement) {
            $sql .= " AUTO_INCREMENT";
        }

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

        if ($column->comment !== null) {
            $sql .= " COMMENT " . $this->quote($column->comment);
        }

        if ($column->after !== null) {
            $sql .= " AFTER " . $this->wrap($column->after);
        }

        return $sql;
    }

    protected function getType(ColumnDefinition $column): string
    {
        $unsigned = $column->unsigned ? ' UNSIGNED' : '';

        return match ($column->type) {
            'increments' => 'INT UNSIGNED',
            'bigIncrements' => 'BIGINT UNSIGNED',
            'integer', 'unsignedInteger' => 'INT' . $unsigned,
            'tinyInteger' => 'TINYINT' . $unsigned,
            'smallInteger' => 'SMALLINT' . $unsigned,
            'mediumInteger' => 'MEDIUMINT' . $unsigned,
            'bigInteger', 'unsignedBigInteger' => 'BIGINT' . $unsigned,
            'string' => "VARCHAR(" . ($column->length ?? 255) . ")",
            'text' => 'TEXT',
            'mediumText' => 'MEDIUMTEXT',
            'longText' => 'LONGTEXT',
            'boolean' => 'TINYINT(1)',
            'float' => 'FLOAT',
            'double' => 'DOUBLE',
            'decimal' => "DECIMAL(" . ($column->precision ?? 10) . ", " . ($column->scale ?? 2) . ")",
            'date' => 'DATE',
            'time' => 'TIME',
            'dateTime', 'timestamp' => 'DATETIME',
            'json' => 'JSON',
            'binary' => 'BLOB',
            'uuid' => 'CHAR(36)',
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
            return $value ? '1' : '0';
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
        return "`" . str_replace("`", "``", $value) . "`";
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
