<?php
declare(strict_types=1);

namespace Oshim\Database\ORM\Relations;

use Oshim\Database\ORM\Model;
use Oshim\Database\ORM\Collection;
use Oshim\Database\Query\QueryBuilder;

class BelongsTo extends Relation
{
    public function __construct(
        QueryBuilder $query,
        Model $parent,
        Model $related,
        string $foreignKey,
        string $ownerKey
    ) {
        parent::__construct($query, $parent, $related, $foreignKey, $ownerKey);
    }

    public function addConstraints(): void
    {
        if ($this->parent->exists || $this->parent->getAttribute($this->foreignKey) !== null) {
            $this->query->where($this->localKey, '=', $this->parent->getAttribute($this->foreignKey));
        }
    }

    public function getResults(): ?Model
    {
        $foreign = $this->parent->getAttribute($this->foreignKey);
        if ($foreign === null) {
            return null;
        }

        $data = $this->query->first();
        if ($data === null) {
            return null;
        }

        return $this->related->newInstance($data, true);
    }

    public function addEagerConstraints(array $models): void
    {
        $keys = [];
        foreach ($models as $model) {
            $key = $model->getAttribute($this->foreignKey);
            if ($key !== null) {
                $keys[] = $key;
            }
        }
        $keys = array_values(array_unique($keys));
        if (empty($keys)) {
            $this->query->whereRaw('0 = 1');
            return;
        }
        $this->query->whereIn($this->localKey, $keys);
    }

    public function match(array $models, Collection $results, string $relation): array
    {
        $dictionary = [];
        foreach ($results as $result) {
            $dictionary[$result->getAttribute($this->localKey)] = $result;
        }

        foreach ($models as $model) {
            $foreign = $model->getAttribute($this->foreignKey);
            $model->setRelation($relation, $dictionary[$foreign] ?? null);
        }

        return $models;
    }
}
