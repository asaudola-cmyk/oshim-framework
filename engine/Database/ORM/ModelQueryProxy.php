<?php
declare(strict_types=1);

namespace Oshim\Database\ORM;

use Oshim\Database\Query\QueryBuilder;
use Oshim\Database\ORM\Relations\Relation;
use Oshim\Database\Exceptions\ModelNotFoundException;
use Closure;

/**
 * Query Proxy for Eager Loading resolution, SoftDeletes scoping, and fluent ORM querying.
 */
class ModelQueryProxy
{
    protected Model $model;
    protected QueryBuilder $query;
    /** @var array<string, Closure|null> */
    protected array $eagerLoads = [];

    public function __construct(Model $model, ?QueryBuilder $query = null)
    {
        $this->model = $model;
        $this->query = $query ?? $model->newQuery();
    }

    public function with(string|array ...$relations): static
    {
        foreach ($relations as $rel) {
            if (is_array($rel)) {
                foreach ($rel as $key => $value) {
                    if (is_string($key)) {
                        $this->eagerLoads[$key] = $value instanceof Closure ? $value : null;
                    } elseif (is_string($value)) {
                        $this->eagerLoads[$value] = null;
                    }
                }
            } elseif (is_string($rel)) {
                $this->eagerLoads[$rel] = null;
            }
        }
        return $this;
    }

    public function withTrashed(?string $column = null): static
    {
        $col = $column ?? ($this->model->deletedAtColumn ?? 'deleted_at');
        $this->query->withTrashed($col);
        return $this;
    }

    public function onlyTrashed(?string $column = null): static
    {
        $col = $column ?? ($this->model->deletedAtColumn ?? 'deleted_at');
        $this->query->onlyTrashed($col);
        return $this;
    }

    public function get(array $columns = ['*']): Collection
    {
        $records = $this->query->get($columns);
        $models = [];

        foreach ($records as $record) {
            $models[] = $this->model->newInstance($record, true);
        }

        $collection = new Collection($models);

        if ($collection->isEmpty()) {
            return $collection;
        }

        foreach ($this->eagerLoads as $relationName => $constraint) {
            if (is_int($relationName)) {
                $relationName = (string)$constraint;
                $constraint = null;
            }

            if (method_exists($this->model, $relationName)) {
                $relation = $this->model->$relationName();
                if ($relation instanceof Relation) {
                    $relation->addEagerConstraints($collection->all());
                    if ($constraint instanceof Closure) {
                        $constraint($relation->getQuery());
                    }
                    $relationResults = $relation->getEager();
                    $relation->match($collection->all(), $relationResults, $relationName);
                }
            }
        }

        return $collection;
    }

    public function first(array $columns = ['*']): ?Model
    {
        $results = $this->get($columns);
        return $results->first();
    }

    public function find(mixed $id, array $columns = ['*']): ?Model
    {
        return $this->where($this->model->getKeyName(), '=', $id)->first($columns);
    }

    public function findOrFail(mixed $id, array $columns = ['*']): Model
    {
        $result = $this->find($id, $columns);
        if ($result === null) {
            throw new ModelNotFoundException(get_class($this->model), [(string)$id]);
        }
        return $result;
    }

    public function getModel(): Model
    {
        return $this->model;
    }

    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }

    public function toSql(): string
    {
        return $this->query->toSql();
    }

    public function getBindings(): array
    {
        return $this->query->getBindings();
    }

    public function count(string $columns = '*'): int
    {
        return $this->query->count($columns);
    }

    public function paginate(int $perPage = 15, int $page = 1, array $columns = ['*']): \Oshim\Database\Pagination\LengthAwarePaginator
    {
        $total = $this->query->count();
        $offset = ($page - 1) * $perPage;
        
        $clone = clone $this->query;
        $records = $clone->limit($perPage)->offset($offset)->get($columns);
        
        $models = [];
        foreach ($records as $record) {
            $models[] = $this->model->newInstance($record, true);
        }

        return new \Oshim\Database\Pagination\LengthAwarePaginator($models, $total, $perPage, $page);
    }

    public function __call(string $method, array $parameters): mixed
    {
        // 1. Check if model has a scope method scopeMethodName
        $scopeMethod = 'scope' . ucfirst($method);
        if (method_exists($this->model, $scopeMethod)) {
            $result = $this->model->$scopeMethod($this, ...$parameters);
            return $result ?? $this;
        }

        $result = $this->query->$method(...$parameters);
        if ($result === $this->query) {
            return $this;
        }
        return $result;
    }
}
