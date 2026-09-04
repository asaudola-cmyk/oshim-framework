<?php
declare(strict_types=1);

namespace Oshim\Database\Schema;

class ForeignKeyDefinition
{
    /** @var list<string> */
    public array $columns;
    public string $foreignTable = '';
    /** @var list<string> */
    public array $foreignColumns = [];
    public ?string $onDelete = null;
    public ?string $onUpdate = null;
    public ?string $name = null;

    public function __construct(string|array $columns)
    {
        $this->columns = (array)$columns;
    }

    public function references(string|array $columns): static
    {
        $this->foreignColumns = (array)$columns;
        return $this;
    }

    public function on(string $table): static
    {
        $this->foreignTable = $table;
        return $this;
    }

    public function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function onDelete(string $action): static
    {
        $this->onDelete = strtoupper($action);
        return $this;
    }

    public function onUpdate(string $action): static
    {
        $this->onUpdate = strtoupper($action);
        return $this;
    }

    public function cascadeOnDelete(): static
    {
        return $this->onDelete('CASCADE');
    }

    public function nullOnDelete(): static
    {
        return $this->onDelete('SET NULL');
    }

    public function restrictOnDelete(): static
    {
        return $this->onDelete('RESTRICT');
    }
}
