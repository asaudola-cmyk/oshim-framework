<?php
declare(strict_types=1);

namespace Oshim\Wasm;

use Oshim\Wasm\Exceptions\WasmTrapException;

/**
 * WebAssembly 1.0 Table Instance.
 * Stores element references (primarily funcref) for indirect calls.
 */
class WasmTable
{
    private int $elemType;
    /** @var list<int|null> */
    private array $elements = [];
    private ?int $maxSize;

    public function __construct(int $elemType = WasmModule::TYPE_FUNCREF, int $initialSize = 0, ?int $maxSize = null)
    {
        $this->elemType = $elemType;
        $this->maxSize = $maxSize;
        $this->elements = array_fill(0, max(0, $initialSize), null);
    }

    /**
     * Get table element at specified index.
     */
    public function get(int $index): ?int
    {
        if ($index < 0 || $index >= count($this->elements)) {
            throw new WasmTrapException("Table index {$index} out of bounds (table size: " . count($this->elements) . ")", 'out_of_bounds_table_access');
        }
        return $this->elements[$index];
    }

    /**
     * Set table element at specified index.
     */
    public function set(int $index, ?int $funcIndex): void
    {
        if ($index < 0 || $index >= count($this->elements)) {
            throw new WasmTrapException("Table index {$index} out of bounds (table size: " . count($this->elements) . ")", 'out_of_bounds_table_access');
        }
        $this->elements[$index] = $funcIndex;
    }

    /**
     * Grow table by delta elements.
     * Returns old size on success, or -1 if exceeding max size.
     */
    public function grow(int $delta, ?int $initValue = null): int
    {
        if ($delta < 0) {
            return -1;
        }
        $oldSize = count($this->elements);
        $newSize = $oldSize + $delta;

        if ($this->maxSize !== null && $newSize > $this->maxSize) {
            return -1;
        }

        for ($i = 0; $i < $delta; $i++) {
            $this->elements[] = $initValue;
        }

        return $oldSize;
    }

    /**
     * Get current table element count.
     */
    public function size(): int
    {
        return count($this->elements);
    }

    /**
     * Get element type.
     */
    public function getType(): int
    {
        return $this->elemType;
    }

    /**
     * Get all elements array.
     *
     * @return list<int|null>
     */
    public function getElements(): array
    {
        return $this->elements;
    }
}
