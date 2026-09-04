<?php
declare(strict_types=1);

namespace Oshim\Database\ORM;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use ArrayIterator;
use JsonSerializable;
use Traversable;

/**
 * High-performance Model Collection with functional map, filter, pluck, load utilities.
 */
class Collection implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /** @var list<mixed> */
    protected array $items = [];

    public function __construct(array $items = [])
    {
        $this->items = array_values($items);
    }

    public static function make(array $items = []): static
    {
        return new static($items);
    }

    public function all(): array
    {
        return $this->items;
    }

    public function map(callable $callback): static
    {
        $keys = array_keys($this->items);
        $items = array_map($callback, $this->items, $keys);
        return new static($items);
    }

    public function filter(?callable $callback = null): static
    {
        if ($callback === null) {
            return new static(array_filter($this->items));
        }
        return new static(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));
    }

    public function pluck(string $value, ?string $key = null): static
    {
        $results = [];

        foreach ($this->items as $item) {
            $itemValue = is_object($item) ? ($item->$value ?? null) : ($item[$value] ?? null);

            if ($key !== null) {
                $itemKey = is_object($item) ? ($item->$key ?? null) : ($item[$key] ?? null);
                if ($itemKey !== null) {
                    $results[$itemKey] = $itemValue;
                    continue;
                }
            }

            $results[] = $itemValue;
        }

        return new static($results);
    }

    public function keyBy(string|callable $keyBy): static
    {
        $results = [];

        foreach ($this->items as $item) {
            $key = is_callable($keyBy)
                ? $keyBy($item)
                : (is_object($item) ? ($item->$keyBy ?? null) : ($item[$keyBy] ?? null));

            if ($key !== null) {
                $results[$key] = $item;
            }
        }

        return new static($results);
    }

    public function groupBy(string|callable $groupBy): static
    {
        $results = [];

        foreach ($this->items as $item) {
            $key = is_callable($groupBy)
                ? $groupBy($item)
                : (is_object($item) ? ($item->$groupBy ?? null) : ($item[$groupBy] ?? null));

            if ($key !== null) {
                $results[$key][] = $item;
            }
        }

        $mapped = [];
        foreach ($results as $k => $group) {
            $mapped[$k] = new static($group);
        }

        return new static($mapped);
    }

    public function first(?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return !empty($this->items) ? $this->items[0] : $default;
        }

        foreach ($this->items as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return $default;
    }

    public function last(?callable $callback = null, mixed $default = null): mixed
    {
        return (new static(array_reverse($this->items)))->first($callback, $default);
    }

    public function contains(mixed $key, mixed $operator = null, mixed $value = null): bool
    {
        if (func_num_args() === 1) {
            if (is_callable($key)) {
                return $this->first($key) !== null;
            }
            return in_array($key, $this->items, true);
        }

        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->first(function ($item) use ($key, $operator, $value) {
            $retrieved = is_object($item) ? ($item->$key ?? null) : ($item[$key] ?? null);
            return match ($operator) {
                '='     => $retrieved == $value,
                '==='   => $retrieved === $value,
                '!='    => $retrieved != $value,
                '!=='   => $retrieved !== $value,
                '>'     => $retrieved > $value,
                '>='    => $retrieved >= $value,
                '<'     => $retrieved < $value,
                '<='    => $retrieved <= $value,
                default => false,
            };
        }) !== null;
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function isNotEmpty(): bool
    {
        return !empty($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function toArray(): array
    {
        return array_map(function ($item) {
            if (is_object($item) && method_exists($item, 'toArray')) {
                return $item->toArray();
            }
            return (array)$item;
        }, $this->items);
    }

    public function toJson(int $options = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE): string
    {
        return (string)json_encode($this->toArray(), $options);
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }
}
