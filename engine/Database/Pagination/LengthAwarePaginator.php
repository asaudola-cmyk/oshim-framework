<?php
declare(strict_types=1);

namespace Oshim\Database\Pagination;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use ArrayIterator;
use JsonSerializable;
use Traversable;

class LengthAwarePaginator implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    private array $items;
    private int $total;
    private int $perPage;
    private int $currentPage;
    private int $lastPage;

    public function __construct(array $items, int $total, int $perPage = 15, int $currentPage = 1)
    {
        $this->items = array_values($items);
        $this->total = $total;
        $this->perPage = max(1, $perPage);
        $this->currentPage = max(1, $currentPage);
        $this->lastPage = max(1, (int)ceil($total / $this->perPage));
    }

    public function items(): array { return $this->items; }
    public function total(): int { return $this->total; }
    public function perPage(): int { return $this->perPage; }
    public function currentPage(): int { return $this->currentPage; }
    public function lastPage(): int { return $this->lastPage; }
    public function hasMorePages(): bool { return $this->currentPage < $this->lastPage; }

    public function toArray(): array
    {
        return [
            'current_page' => $this->currentPage,
            'data' => $this->items,
            'last_page' => $this->lastPage,
            'per_page' => $this->perPage,
            'total' => $this->total,
            'has_more' => $this->hasMorePages(),
        ];
    }

    public function jsonSerialize(): array { return $this->toArray(); }
    public function count(): int { return count($this->items); }
    public function getIterator(): Traversable { return new ArrayIterator($this->items); }

    public function offsetExists(mixed $offset): bool { return isset($this->items[$offset]); }
    public function offsetGet(mixed $offset): mixed { return $this->items[$offset] ?? null; }
    public function offsetSet(mixed $offset, mixed $value): void { $this->items[$offset] = $value; }
    public function offsetUnset(mixed $offset): void { unset($this->items[$offset]); }
}
