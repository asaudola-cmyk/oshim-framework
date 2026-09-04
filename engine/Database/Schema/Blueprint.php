<?php
declare(strict_types=1);

namespace Oshim\Database\Schema;

use Closure;

class Blueprint
{
    public string $table;
    /** @var list<ColumnDefinition> */
    public array $columns = [];
    /** @var list<array> */
    public array $commands = [];
    public bool $creating = false;

    public function __construct(string $table, ?Closure $callback = null)
    {
        $this->table = $table;
        if ($callback !== null) {
            $callback($this);
        }
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getCommands(): array
    {
        return $this->commands;
    }

    public function addColumn(string $name, string $type, array $attributes = []): ColumnDefinition
    {
        $column = new ColumnDefinition($name, $type, $attributes);
        $this->columns[] = $column;
        return $column;
    }

    public function id(string $name = 'id'): ColumnDefinition
    {
        return $this->bigInteger($name)->autoIncrement()->primary()->unsigned();
    }

    public function increments(string $name = 'id'): ColumnDefinition
    {
        return $this->integer($name)->autoIncrement()->primary()->unsigned();
    }

    public function bigIncrements(string $name = 'id'): ColumnDefinition
    {
        return $this->bigInteger($name)->autoIncrement()->primary()->unsigned();
    }

    public function uuid(string $name = 'id'): ColumnDefinition
    {
        return $this->addColumn($name, 'uuid');
    }

    public function string(string $name, int $length = 255): ColumnDefinition
    {
        return $this->addColumn($name, 'string', ['length' => $length]);
    }

    public function text(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'text');
    }

    public function mediumText(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'mediumText');
    }

    public function longText(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'longText');
    }

    public function integer(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'integer');
    }

    public function tinyInteger(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'tinyInteger');
    }

    public function smallInteger(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'smallInteger');
    }

    public function bigInteger(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'bigInteger');
    }

    public function unsignedInteger(string $name): ColumnDefinition
    {
        return $this->integer($name)->unsigned();
    }

    public function unsignedBigInteger(string $name): ColumnDefinition
    {
        return $this->bigInteger($name)->unsigned();
    }

    public function float(string $name, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        return $this->addColumn($name, 'float', ['precision' => $precision, 'scale' => $scale]);
    }

    public function double(string $name, ?int $precision = null, ?int $scale = null): ColumnDefinition
    {
        return $this->addColumn($name, 'double', ['precision' => $precision, 'scale' => $scale]);
    }

    public function decimal(string $name, int $precision = 10, int $scale = 2): ColumnDefinition
    {
        return $this->addColumn($name, 'decimal', ['precision' => $precision, 'scale' => $scale]);
    }

    public function boolean(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'boolean');
    }

    public function json(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'json');
    }

    public function date(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'date');
    }

    public function dateTime(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'dateTime');
    }

    public function time(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'time');
    }

    public function timestamp(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'timestamp');
    }

    public function timestamps(): void
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
    }

    public function softDeletes(string $column = 'deleted_at'): ColumnDefinition
    {
        return $this->timestamp($column)->nullable();
    }

    public function foreignId(string $name): ColumnDefinition
    {
        return $this->unsignedBigInteger($name);
    }

    // --- Commands / Indexes ---
    public function primary(string|array $columns, ?string $name = null): static
    {
        $this->commands[] = [
            'type'    => 'primary',
            'columns' => (array)$columns,
            'name'    => $name,
        ];
        return $this;
    }

    public function unique(string|array $columns, ?string $name = null): static
    {
        $this->commands[] = [
            'type'    => 'unique',
            'columns' => (array)$columns,
            'name'    => $name,
        ];
        return $this;
    }

    public function index(string|array $columns, ?string $name = null): static
    {
        $this->commands[] = [
            'type'    => 'index',
            'columns' => (array)$columns,
            'name'    => $name,
        ];
        return $this;
    }

    public function foreign(string|array $columns, ?string $name = null): ForeignKeyDefinition
    {
        $foreign = new ForeignKeyDefinition($columns);
        if ($name !== null) {
            $foreign->name($name);
        }
        $this->commands[] = [
            'type'       => 'foreign',
            'definition' => $foreign,
        ];
        return $foreign;
    }

    public function dropColumn(string|array $columns): static
    {
        $this->commands[] = [
            'type'    => 'dropColumn',
            'columns' => (array)$columns,
        ];
        return $this;
    }

    public function dropIndex(string $indexName): static
    {
        $this->commands[] = [
            'type' => 'dropIndex',
            'name' => $indexName,
        ];
        return $this;
    }

    public function dropUnique(string $indexName): static
    {
        $this->commands[] = [
            'type' => 'dropUnique',
            'name' => $indexName,
        ];
        return $this;
    }

    public function dropPrimary(?string $name = null): static
    {
        $this->commands[] = [
            'type' => 'dropPrimary',
            'name' => $name,
        ];
        return $this;
    }

    public function dropForeign(string $foreignName): static
    {
        $this->commands[] = [
            'type' => 'dropForeign',
            'name' => $foreignName,
        ];
        return $this;
    }
}
