<?php
declare(strict_types=1);

namespace Oshim\Database\ORM\Relations;

use Oshim\Database\ORM\Model;
use Oshim\Database\ORM\Collection;
use Oshim\Database\Query\QueryBuilder;

abstract class Relation
{
    public function __construct(
        protected QueryBuilder $query,
        protected Model $parent,
        protected Model $related,
        protected string $foreignKey,
        protected string $localKey
    ) {
        $this->addConstraints();
    }

    abstract public function addConstraints(): void;
    abstract public function getResults(): mixed;
    abstract public function addEagerConstraints(array $models): void;
    abstract public function match(array $models, Collection $results, string $relation): array;

    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }

    public function getParent(): Model
    {
        return $this->parent;
    }

    public function getRelated(): Model
    {
        return $this->related;
    }

    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }

    public function getLocalKeyName(): string
    {
        return $this->localKey;
    }

    public function get(array $columns = ['*']): Collection
    {
        $records = $this->query->get($columns);
        $models = [];
        foreach ($records as $record) {
            $models[] = $this->related->newInstance($record, true);
        }
        return new Collection($models);
    }

    public function getEager(): Collection
    {
        return $this->get();
    }

    public function __call(string $method, array $parameters): mixed
    {
        $result = $this->query->$method(...$parameters);
        if ($result === $this->query) {
            return $this;
        }
        return $result;
    }
}
