<?php
declare(strict_types=1);

namespace Oshim\Ai\Schema;

class Type
{
    private string $type;
    private array $properties = [];
    private array $required = [];
    private ?array $enum = null;
    private string $description = '';
    private ?Type $items = null;

    public function __construct(string $type, string $description = '')
    {
        $this->type = $type;
        $this->description = $description;
    }

    public static function string(string $desc = ''): self { return new self('string', $desc); }
    public static function int(string $desc = ''): self { return new self('integer', $desc); }
    public static function float(string $desc = ''): self { return new self('number', $desc); }
    public static function bool(string $desc = ''): self { return new self('boolean', $desc); }
    public static function array(Type $items, string $desc = ''): self {
        $t = new self('array', $desc);
        $t->items = $items;
        return $t;
    }
    public static function object(array $properties = [], array $required = [], string $desc = ''): self {
        $t = new self('object', $desc);
        $t->properties = $properties;
        $t->required = $required;
        return $t;
    }
    public static function enum(array $values, string $desc = ''): self {
        $t = new self('string', $desc);
        $t->enum = $values;
        return $t;
    }

    public function toJsonSchema(): array
    {
        $schema = ['type' => $this->type];
        if (!empty($this->description)) $schema['description'] = $this->description;
        if ($this->enum !== null) $schema['enum'] = $this->enum;
        if ($this->items !== null) $schema['items'] = $this->items->toJsonSchema();

        if ($this->type === 'object') {
            $props = [];
            foreach ($this->properties as $name => $prop) {
                $props[$name] = $prop->toJsonSchema();
            }
            $schema['properties'] = $props;
            if (!empty($this->required)) $schema['required'] = $this->required;
        }

        return $schema;
    }

    public function validate(mixed $data): bool
    {
        if ($this->type === 'string') {
            return is_string($data) && ($this->enum === null || in_array($data, $this->enum, true));
        }

        if ($this->type === 'integer') {
            return is_int($data);
        }

        if ($this->type === 'number') {
            return is_numeric($data);
        }

        if ($this->type === 'boolean') {
            return is_bool($data);
        }

        if ($this->type === 'array') {
            if (!is_array($data)) return false;
            if ($this->items === null) return true;
            foreach ($data as $item) {
                if (!$this->items->validate($item)) return false;
            }
            return true;
        }

        if ($this->type === 'object') {
            if (!is_array($data)) return false;
            // Check required keys
            if (!empty(array_diff($this->required, array_keys($data)))) {
                return false;
            }
            // Check property types
            foreach ($this->properties as $key => $propType) {
                if (array_key_exists($key, $data)) {
                    if (!$propType->validate($data[$key])) {
                        return false;
                    }
                }
            }
            return true;
        }

        return true;
    }
}
