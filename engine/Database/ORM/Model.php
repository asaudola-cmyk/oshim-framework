<?php
declare(strict_types=1);

namespace Oshim\Database\ORM;

use Oshim\Database\Connection;
use Oshim\Database\ConnectionManager;
use Oshim\Database\Query\QueryBuilder;
use Oshim\Database\ORM\Relations\Relation;
use Oshim\Database\ORM\Relations\HasOne;
use Oshim\Database\ORM\Relations\HasMany;
use Oshim\Database\ORM\Relations\BelongsTo;
use Oshim\Database\ORM\Relations\BelongsToMany;
use Oshim\Database\ORM\Traits\HasTimestamps;
use Oshim\Database\ORM\Traits\SoftDeletes;
use Oshim\Database\Exceptions\ModelNotFoundException;
use ArrayAccess;
use JsonSerializable;
use ReflectionClass;
use Closure;

/**
 * High-performance Active-Record ORM Model.
 */
abstract class Model implements ArrayAccess, JsonSerializable
{
    use HasTimestamps;

    protected ?string $connection = null;
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected string $keyType = 'int';
    public bool $incrementing = true;
    public bool $exists = false;

    /** @var array<string, mixed> */
    protected array $attributes = [];
    /** @var array<string, mixed> */
    protected array $original = [];
    /** @var array<string, mixed> */
    protected array $changes = [];

    /** @var list<string> */
    protected array $fillable = [];
    /** @var list<string> */
    protected array $guarded = [];
    /** @var array<string, string> */
    protected array $casts = [];
    /** @var list<string> */
    protected array $hidden = [];
    /** @var array<string, mixed> */
    protected array $relations = [];
    /** @var list<string> */
    protected array $with = [];

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
        $this->syncOriginal();
    }

    public function getTable(): string
    {
        if ($this->table !== '') {
            return $this->table;
        }

        $shortName = (new ReflectionClass($this))->getShortName();
        // Convert PascalCase to snake_case and pluralize simple trailing 's'
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName));
        $this->table = str_ends_with($snake, 's') ? $snake : $snake . 's';

        return $this->table;
    }

    public function setTable(string $table): static
    {
        $this->table = $table;
        return $this;
    }

    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    public function getKey(): mixed
    {
        return $this->getAttribute($this->getKeyName());
    }

    public function getConnection(): Connection
    {
        return ConnectionManager::getInstance()->connection($this->connection);
    }

    public function newInstance(array $attributes = [], bool $exists = false): static
    {
        $model = new static();
        $model->exists = $exists;
        $model->setRawAttributes($attributes, true);
        return $model;
    }

    // --- Attributes & Casting ---
    public function setRawAttributes(array $attributes, bool $sync = false): static
    {
        $this->attributes = $attributes;
        if ($sync) {
            $this->syncOriginal();
        }
        return $this;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute(string $key): mixed
    {
        if (array_key_exists($key, $this->attributes)) {
            $value = $this->attributes[$key];
            return $this->castAttribute($key, $value);
        }

        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        // Check if relation method exists
        if (method_exists($this, $key)) {
            return $this->getRelationshipFromMethod($key);
        }

        return null;
    }

    public function setAttribute(string $key, mixed $value): static
    {
        $this->attributes[$key] = $this->uncastAttribute($key, $value);
        return $this;
    }

    protected function castAttribute(string $key, mixed $value): mixed
    {
        if ($value === null || !isset($this->casts[$key])) {
            return $value;
        }

        $type = strtolower(trim($this->casts[$key]));

        return match ($type) {
            'int', 'integer' => (int)$value,
            'float', 'double', 'real' => (float)$value,
            'string' => (string)$value,
            'bool', 'boolean' => (bool)$value,
            'array', 'json' => is_string($value) ? json_decode($value, true) : (array)$value,
            'object' => is_string($value) ? json_decode($value) : (object)$value,
            default => $value,
        };
    }

    protected function uncastAttribute(string $key, mixed $value): mixed
    {
        if ($value === null || !isset($this->casts[$key])) {
            return $value;
        }

        $type = strtolower(trim($this->casts[$key]));

        if (in_array($type, ['array', 'json', 'object'], true) && (is_array($value) || is_object($value))) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return $value;
    }

    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }
        return $this;
    }

    public function isFillable(string $key): bool
    {
        if (!empty($this->fillable)) {
            return in_array($key, $this->fillable, true);
        }

        return !in_array($key, $this->guarded, true);
    }

    // --- Dirty Tracking ---
    public function syncOriginal(): static
    {
        $this->original = $this->attributes;
        $this->changes = [];
        return $this;
    }

    public function isDirty(?string $key = null): bool
    {
        $dirty = $this->getDirty();
        if ($key === null) {
            return !empty($dirty);
        }
        return array_key_exists($key, $dirty);
    }

    public function isClean(?string $key = null): bool
    {
        return !$this->isDirty($key);
    }

    public function getDirty(): array
    {
        $dirty = [];
        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }
        return $dirty;
    }

    public function getOriginal(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->original;
        }
        return $this->original[$key] ?? $default;
    }

    // --- Relationships ---
    public function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): HasOne
    {
        $instance = new $related();
        $foreignKey = $foreignKey ?? $this->getForeignKey();
        $localKey = $localKey ?? $this->getKeyName();

        return new HasOne($instance->newQuery(), $this, $instance, $foreignKey, $localKey);
    }

    public function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): HasMany
    {
        $instance = new $related();
        $foreignKey = $foreignKey ?? $this->getForeignKey();
        $localKey = $localKey ?? $this->getKeyName();

        return new HasMany($instance->newQuery(), $this, $instance, $foreignKey, $localKey);
    }

    public function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): BelongsTo
    {
        $instance = new $related();
        $foreignKey = $foreignKey ?? $instance->getForeignKey();
        $ownerKey = $ownerKey ?? $instance->getKeyName();

        return new BelongsTo($instance->newQuery(), $this, $instance, $foreignKey, $ownerKey);
    }

    public function belongsToMany(
        string $related,
        ?string $table = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        ?string $parentKey = null,
        ?string $relatedKey = null
    ): BelongsToMany {
        $instance = new $related();
        $foreignPivotKey = $foreignPivotKey ?? $this->getForeignKey();
        $relatedPivotKey = $relatedPivotKey ?? $instance->getForeignKey();
        $parentKey = $parentKey ?? $this->getKeyName();
        $relatedKey = $relatedKey ?? $instance->getKeyName();

        if ($table === null) {
            $segments = [$this->getTable(), $instance->getTable()];
            sort($segments);
            $table = implode('_', $segments);
        }

        return new BelongsToMany(
            $instance->newQuery(),
            $this,
            $instance,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey
        );
    }

    public function getForeignKey(): string
    {
        $shortName = (new ReflectionClass($this))->getShortName();
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName)) . '_id';
    }

    protected function getRelationshipFromMethod(string $method): mixed
    {
        $relation = $this->$method();
        if (!$relation instanceof Relation) {
            return null;
        }

        $results = $relation->getResults();
        $this->setRelation($method, $results);

        return $results;
    }

    public function getRelation(string $relation): mixed
    {
        return $this->relations[$relation] ?? null;
    }

    public function setRelation(string $relation, mixed $value): static
    {
        $this->relations[$relation] = $value;
        return $this;
    }

    public function relationLoaded(string $relation): bool
    {
        return array_key_exists($relation, $this->relations);
    }

    // --- Query Scopes & Builder ---
    public function newQuery(): QueryBuilder
    {
        $builder = $this->getConnection()->table($this->getTable());

        // Apply SoftDeletes scope if trait is used
        if (in_array(SoftDeletes::class, class_uses_recursive($this), true)) {
            $builder->whereNull($this->getTable() . '.' . ($this->deletedAtColumn ?? 'deleted_at'));
        }

        return $builder;
    }

    public static function query(): ModelQueryProxy
    {
        return new ModelQueryProxy(new static());
    }

    public static function on(?string $connection = null): static
    {
        $model = new static();
        $model->connection = $connection;
        return $model;
    }

    public static function all(array $columns = ['*']): Collection
    {
        return static::query()->get($columns);
    }

    public static function find(mixed $id, array $columns = ['*']): ?static
    {
        return static::query()->find($id, $columns);
    }

    public static function findOrFail(mixed $id, array $columns = ['*']): static
    {
        $result = static::find($id, $columns);
        if ($result === null) {
            throw new ModelNotFoundException(static::class, [(string)$id]);
        }
        return $result;
    }

    public static function create(array $attributes = []): static
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    public static function where(string|Closure|array $column, mixed $operator = null, mixed $value = null): ModelQueryProxy
    {
        return static::query()->where(...func_get_args());
    }

    public static function with(string|array ...$relations): ModelQueryProxy
    {
        return (new ModelQueryProxy(new static()))->with(...$relations);
    }

    public static function withTrashed(?string $column = null): ModelQueryProxy
    {
        $instance = new static();
        $proxy = new ModelQueryProxy($instance);
        return $proxy->withTrashed($column);
    }

    public static function onlyTrashed(?string $column = null): ModelQueryProxy
    {
        $instance = new static();
        $proxy = new ModelQueryProxy($instance);
        return $proxy->onlyTrashed($column);
    }

    public static function paginate(int $perPage = 15, int $page = 1, array $columns = ['*']): \Oshim\Database\Pagination\LengthAwarePaginator
    {
        return static::query()->paginate($perPage, $page, $columns);
    }

    // --- Lifecycle Events ---
    protected function onCreating(): bool { return true; }
    protected function onCreated(): void {}
    protected function onUpdating(): bool { return true; }
    protected function onUpdated(): void {}
    protected function onDeleting(): bool { return true; }
    protected function onDeleted(): void {}

    // --- Persistence ---
    public function save(): bool
    {
        if ($this->timestamps) {
            $this->updateTimestamps();
        }

        if ($this->exists) {
            if ($this->onUpdating() === false) {
                return false;
            }

            $dirty = $this->getDirty();
            if (empty($dirty)) {
                return true;
            }

            $affected = $this->newQueryWithoutScopes()
                ->where($this->getKeyName(), '=', $this->getKey())
                ->update($dirty);

            $this->syncOriginal();
            $this->onUpdated();
            return $affected > 0;
        }

        if ($this->onCreating() === false) {
            return false;
        }

        $attributes = $this->attributes;

        if ($this->incrementing) {
            $id = $this->getConnection()->table($this->getTable())->insertGetId($attributes);
            $this->setAttribute($this->getKeyName(), $id);
        } else {
            $this->getConnection()->table($this->getTable())->insert($attributes);
        }

        $this->exists = true;
        $this->syncOriginal();
        $this->onCreated();

        return true;
    }

    public function update(array $attributes = []): bool
    {
        if (!$this->exists) {
            return false;
        }

        return $this->fill($attributes)->save();
    }

    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        if ($this->onDeleting() === false) {
            return false;
        }

        if (in_array(SoftDeletes::class, class_uses_recursive($this), true)) {
            $time = date('Y-m-d H:i:s');
            $this->setAttribute($this->deletedAtColumn ?? 'deleted_at', $time);
            $res = $this->save();
            $this->onDeleted();
            return $res;
        }

        $affected = $this->newQueryWithoutScopes()
            ->where($this->getKeyName(), '=', $this->getKey())
            ->delete();

        $this->exists = false;
        $this->onDeleted();
        return $affected > 0;
    }

    public function forceDelete(): bool
    {
        $affected = $this->newQueryWithoutScopes()
            ->where($this->getKeyName(), '=', $this->getKey())
            ->delete();

        $this->exists = false;
        return $affected > 0;
    }

    public function newQueryWithoutScopes(): QueryBuilder
    {
        return $this->getConnection()->table($this->getTable());
    }

    public static function destroy(mixed $ids): int
    {
        $count = 0;
        $ids = is_array($ids) ? $ids : func_get_args();

        foreach ($ids as $id) {
            $model = static::find($id);
            if ($model && $model->delete()) {
                $count++;
            }
        }

        return $count;
    }

    // --- Array & JSON Conversion ---
    public function toArray(): array
    {
        $attributes = [];

        foreach ($this->attributes as $key => $value) {
            if (!in_array($key, $this->hidden, true)) {
                $attributes[$key] = $this->castAttribute($key, $value);
            }
        }

        foreach ($this->relations as $relation => $value) {
            if (!in_array($relation, $this->hidden, true)) {
                if (is_object($value) && method_exists($value, 'toArray')) {
                    $attributes[$relation] = $value->toArray();
                } else {
                    $attributes[$relation] = $value;
                }
            }
        }

        return $attributes;
    }

    public function toJson(int $options = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE): string
    {
        return (string)json_encode($this->toArray(), $options);
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    // --- Magic & ArrayAccess Methods ---
    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    public function __isset(string $key): bool
    {
        return $this->getAttribute($key) !== null;
    }

    public function __unset(string $key): void
    {
        unset($this->attributes[$key], $this->relations[$key]);
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->__isset((string)$offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->getAttribute((string)$offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->setAttribute((string)$offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->__unset((string)$offset);
    }

    public function __call(string $method, array $parameters): mixed
    {
        return $this->newQuery()->$method(...$parameters);
    }

    public static function __callStatic(string $method, array $parameters): mixed
    {
        return (new ModelQueryProxy(new static()))->$method(...$parameters);
    }
}

/**
 * Helper to get all traits used by a class including parent classes.
 */
function class_uses_recursive(object|string $class): array
{
    $results = [];
    $traits = class_uses($class);
    if ($traits !== false) {
        $results = array_merge($results, $traits);
    }

    $parent = get_parent_class($class);
    if ($parent !== false) {
        $results = array_merge($results, class_uses_recursive($parent));
    }

    return array_unique($results);
}
