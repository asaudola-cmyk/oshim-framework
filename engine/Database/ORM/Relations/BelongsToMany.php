<?php
declare(strict_types=1);

namespace Oshim\Database\ORM\Relations;

use Oshim\Database\ORM\Model;
use Oshim\Database\ORM\Collection;
use Oshim\Database\Query\QueryBuilder;

class BelongsToMany extends Relation
{
    protected string $table;
    protected string $foreignPivotKey;
    protected string $relatedPivotKey;
    protected string $parentKey;
    protected string $relatedKey;
    /** @var list<string> */
    protected array $pivotColumns = [];

    public function __construct(
        QueryBuilder $query,
        Model $parent,
        Model $related,
        string $table,
        string $foreignPivotKey,
        string $relatedPivotKey,
        string $parentKey = 'id',
        string $relatedKey = 'id'
    ) {
        $this->table = $table;
        $this->foreignPivotKey = $foreignPivotKey;
        $this->relatedPivotKey = $relatedPivotKey;
        $this->parentKey = $parentKey;
        $this->relatedKey = $relatedKey;

        parent::__construct($query, $parent, $related, $foreignPivotKey, $parentKey);
    }

    public function withPivot(string|array ...$columns): static
    {
        foreach ($columns as $column) {
            if (is_array($column)) {
                $this->pivotColumns = array_merge($this->pivotColumns, $column);
            } else {
                $this->pivotColumns[] = $column;
            }
        }
        $this->pivotColumns = array_values(array_unique($this->pivotColumns));
        return $this;
    }

    public function addConstraints(): void
    {
        $relatedTable = $this->related->getTable();
        $this->query->join($this->table, "{$this->table}.{$this->relatedPivotKey}", '=', "{$relatedTable}.{$this->relatedKey}");

        if ($this->parent->exists) {
            $this->query->where("{$this->table}.{$this->foreignPivotKey}", '=', $this->parent->getAttribute($this->parentKey));
        }

        $selects = ["{$relatedTable}.*"];
        $selects[] = "{$this->table}.{$this->foreignPivotKey} as pivot_{$this->foreignPivotKey}";
        $selects[] = "{$this->table}.{$this->relatedPivotKey} as pivot_{$this->relatedPivotKey}";

        foreach ($this->pivotColumns as $pCol) {
            $selects[] = "{$this->table}.{$pCol} as pivot_{$pCol}";
        }

        $this->query->select($selects);
    }

    public function getResults(): Collection
    {
        $records = $this->query->get();
        $models = [];

        foreach ($records as $record) {
            $pivotData = [];
            $modelData = [];

            foreach ($record as $key => $value) {
                if (str_starts_with($key, 'pivot_')) {
                    $pivotData[substr($key, 6)] = $value;
                } else {
                    $modelData[$key] = $value;
                }
            }

            $model = $this->related->newInstance($modelData, true);
            $model->setRelation('pivot', (object)$pivotData);
            $models[] = $model;
        }

        return new Collection($models);
    }

    public function addEagerConstraints(array $models): void
    {
        $keys = [];
        foreach ($models as $model) {
            $key = $model->getAttribute($this->parentKey);
            if ($key !== null) {
                $keys[] = $key;
            }
        }
        $this->query->whereIn("{$this->table}.{$this->foreignPivotKey}", array_unique($keys));
    }

    public function match(array $models, Collection $results, string $relation): array
    {
        $dictionary = [];
        foreach ($results as $result) {
            $pivot = $result->getRelation('pivot');
            $foreign = $pivot->{$this->foreignPivotKey} ?? null;
            if ($foreign !== null) {
                $dictionary[$foreign][] = $result;
            }
        }

        foreach ($models as $model) {
            $key = $model->getAttribute($this->parentKey);
            $model->setRelation($relation, new Collection($dictionary[$key] ?? []));
        }

        return $models;
    }

    public function attach(int|string|array $id, array $attributes = []): void
    {
        $parentId = $this->parent->getAttribute($this->parentKey);
        $ids = is_array($id) ? $id : [$id => $attributes];

        foreach ($ids as $key => $val) {
            $relatedId = is_numeric($key) && is_int($key) && !is_array($val) ? $val : $key;
            $extra = is_array($val) ? $val : $attributes;

            $record = array_merge([
                $this->foreignPivotKey => $parentId,
                $this->relatedPivotKey => $relatedId,
            ], $extra);

            $this->parent->getConnection()->table($this->table)->insert($record);
        }
    }

    public function detach(int|string|array $ids = []): int
    {
        $parentId = $this->parent->getAttribute($this->parentKey);
        $query = $this->parent->getConnection()->table($this->table)
            ->where($this->foreignPivotKey, '=', $parentId);

        if (!empty($ids)) {
            $query->whereIn($this->relatedPivotKey, (array)$ids);
        }

        return $query->delete();
    }

    public function sync(array $ids, bool $detaching = true): array
    {
        $current = $this->parent->getConnection()->table($this->table)
            ->where($this->foreignPivotKey, '=', $this->parent->getAttribute($this->parentKey))
            ->pluck($this->relatedPivotKey);

        $records = [];
        foreach ($ids as $key => $value) {
            if (is_array($value)) {
                $records[$key] = $value;
            } else {
                $records[$value] = [];
            }
        }

        $detach = array_diff($current, array_keys($records));
        if ($detaching && !empty($detach)) {
            $this->detach(array_values($detach));
        }

        $attach = array_diff(array_keys($records), $current);
        foreach ($attach as $id) {
            $this->attach($id, $records[$id]);
        }

        return [
            'attached' => array_values($attach),
            'detached' => array_values($detach),
            'updated'  => [],
        ];
    }

    public function toggle(array $ids): array
    {
        $current = $this->parent->getConnection()->table($this->table)
            ->where($this->foreignPivotKey, '=', $this->parent->getAttribute($this->parentKey))
            ->pluck($this->relatedPivotKey);

        $detach = array_intersect($ids, $current);
        $attach = array_diff($ids, $current);

        if (!empty($detach)) {
            $this->detach(array_values($detach));
        }

        if (!empty($attach)) {
            foreach ($attach as $id) {
                $this->attach($id);
            }
        }

        return [
            'attached' => array_values($attach),
            'detached' => array_values($detach),
        ];
    }
}
