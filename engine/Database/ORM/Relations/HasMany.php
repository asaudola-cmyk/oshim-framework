<?php
declare(strict_types=1);

namespace Oshim\Database\ORM\Relations;

use Oshim\Database\ORM\Model;
use Oshim\Database\ORM\Collection;

class HasMany extends Relation
{
    public function addConstraints(): void
    {
        if ($this->parent->exists) {
            $this->query->where($this->foreignKey, '=', $this->parent->getAttribute($this->localKey));
        }
    }

    public function getResults(): Collection
    {
        return $this->get();
    }

    public function addEagerConstraints(array $models): void
    {
        $keys = [];
        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            if ($key !== null) {
                $keys[] = $key;
            }
        }
        $keys = array_values(array_unique($keys));
        if (empty($keys)) {
            $this->query->whereRaw('0 = 1');
            return;
        }
        $this->query->whereIn($this->foreignKey, $keys);
    }

    public function match(array $models, Collection $results, string $relation): array
    {
        $dictionary = [];
        foreach ($results as $result) {
            $foreign = $result->getAttribute($this->foreignKey);
            $dictionary[$foreign][] = $result;
        }

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            $model->setRelation($relation, new Collection($dictionary[$key] ?? []));
        }

        return $models;
    }
}
