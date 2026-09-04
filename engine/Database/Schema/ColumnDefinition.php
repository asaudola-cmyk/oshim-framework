<?php
declare(strict_types=1);

namespace Oshim\Database\Schema;

class ColumnDefinition
{
    public string $name;
    public string $type;
    public bool $nullable = false;
    public mixed $default = null;
    public bool $hasDefault = false;
    public bool $autoIncrement = false;
    public bool $primary = false;
    public bool $unique = false;
    public bool $unsigned = false;
    public ?int $length = null;
    public ?int $precision = null;
    public ?int $scale = null;
    public ?string $comment = null;
    public ?string $after = null;
    public bool $change = false;

    public function __construct(string $name, string $type, array $attributes = [])
    {
        $this->name = $name;
        $this->type = $type;

        foreach ($attributes as $key => $val) {
            if (property_exists($this, $key)) {
                $this->$key = $val;
            }
        }
    }

    public function nullable(bool $nullable = true): static
    {
        $this->nullable = $nullable;
        return $this;
    }

    public function default(mixed $value): static
    {
        $this->default = $value;
        $this->hasDefault = true;
        return $this;
    }

    public function unique(bool $unique = true): static
    {
        $this->unique = $unique;
        return $this;
    }

    public function primary(bool $primary = true): static
    {
        $this->primary = $primary;
        return $this;
    }

    public function autoIncrement(bool $autoIncrement = true): static
    {
        $this->autoIncrement = $autoIncrement;
        return $this;
    }

    public function unsigned(bool $unsigned = true): static
    {
        $this->unsigned = $unsigned;
        return $this;
    }

    public function comment(string $comment): static
    {
        $this->comment = $comment;
        return $this;
    }

    public function after(string $column): static
    {
        $this->after = $column;
        return $this;
    }

    public function change(): static
    {
        $this->change = true;
        return $this;
    }
}
