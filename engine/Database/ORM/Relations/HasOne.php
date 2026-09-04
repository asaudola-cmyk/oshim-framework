<?php
declare(strict_types=1);

namespace Oshim\Database\ORM\Relations;

use Oshim\Database\ORM\Model;
use Oshim\Database\ORM\Collection;

class HasOne extends Relation
{
    public function addConstraints(): void
    {
        if ($this->parent->exists) {
            $this->query->where($this->foreignKey, '=', $this->parent->getAttribute($this->localKey));
        }
    }

    public function getResults(): ?Model
    {
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
            $dictionary[$result->getAttribute($this->foreignKey)] = $result;
        }

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            if (isset($dictionary[$key])) {
                $model->setRelation($relation, $dictionary[$key]);
            } else {
                $model->setRelation($relation, null);
            }
        }

        return $models;
    }
}
