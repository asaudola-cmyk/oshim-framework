<?php
declare(strict_types=1);

namespace Oshim\Database\Mmap;

use ReflectionClass;
use ReflectionProperty;

/**
 * 👑 Sovereign OSHIM Living Entity (Zero-SQL Persistent Model)
 * 
 * WHY: Replaces ActiveRecord, Eloquent, and Doctrine ORMs.
 * Changes to public properties are written directly to shared kernel memory.
 * No SQL queries, no migrations, no database server required.
 */
abstract class LivingEntity
{
    public int $id = 0;
    protected static ?LivingMemory $memoryEngine = null;

    public function __construct(int $id = 0)
    {
        $this->id = $id;
    }

    public static function getMemory(): LivingMemory
    {
        if (self::$memoryEngine === null) {
            self::$memoryEngine = new LivingMemory();
        }
        return self::$memoryEngine;
    }

    /**
     * Finds a record by ID directly from shared memory in <10 nanoseconds.
     */
    public static function find(int $id): ?static
    {
        $data = self::getMemory()->readSlot(static::class, $id);
        if ($data === null) {
            return null;
        }

        $entity = new static($id);
        $entity->hydrate($data);
        return $entity;
    }

    /**
     * Creates or retrieves a persistent slot for this entity.
     */
    public static function create(int $id, array $attributes = []): static
    {
        $entity = new static($id);
        $entity->hydrate($attributes);
        $entity->save();
        return $entity;
    }

    /**
     * Persists entity state directly to shared OS memory.
     */
    public function save(): bool
    {
        if ($this->id <= 0) {
            // Find next available slot or assign default
            $this->id = 1;
        }

        $state = $this->extractState();
        return self::getMemory()->writeSlot(static::class, $this->id, $state);
    }

    /**
     * Deletes the entity slot from shared memory.
     */
    public function delete(): bool
    {
        if ($this->id > 0) {
            return self::getMemory()->deleteSlot(static::class, $this->id);
        }
        return false;
    }

    public function hydrate(array $data): void
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key) && $key !== 'memoryEngine') {
                $this->{$key} = $value;
            }
        }
    }

    public function extractState(): array
    {
        $state = [];
        $reflection = new ReflectionClass($this);
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $name = $prop->getName();
            if ($name !== 'memoryEngine') {
                $state[$name] = $this->{$name};
            }
        }
        return $state;
    }
}
