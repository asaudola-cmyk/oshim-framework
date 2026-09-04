<?php
declare(strict_types=1);

namespace Oshim\Database\Query;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use ArrayIterator;
use JsonSerializable;
use Traversable;

/**
 * Length-Aware Pagination Result Container.
 */
class Paginator implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /** @var list<mixed> */
    protected array $items;
    protected int $total;
    protected int $perPage;
    protected int $currentPage;
    protected int $lastPage;

    public function __construct(array $items, int $total, int $perPage = 15, int $currentPage = 1)
    {
        $this->items = array_values($items);
        $this->total = max(0, $total);
        $this->perPage = max(1, $perPage);
        $this->currentPage = max(1, $currentPage);
        $this->lastPage = (int)max(1, ceil($this->total / $this->perPage));
    }

    public function items(): array
    {
        return $this->items;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function lastPage(): int
    {
        return $this->lastPage;
    }

    public function hasPages(): bool
    {
        return $this->lastPage > 1;
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    public function onFirstPage(): bool
    {
        return $this->currentPage <= 1;
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
        $itemsArray = [];
        foreach ($this->items as $item) {
            if (is_object($item) && method_exists($item, 'toArray')) {
                $itemsArray[] = $item->toArray();
            } else {
                $itemsArray[] = (array)$item;
            }
        }

        return [
            'current_page' => $this->currentPage,
            'data'         => $itemsArray,
            'last_page'    => $this->lastPage,
            'per_page'     => $this->perPage,
            'total'        => $this->total,
        ];
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
