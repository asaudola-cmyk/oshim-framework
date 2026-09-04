<?php
declare(strict_types=1);

namespace Oshim\Wasm\Exceptions;

/**
 * Exception thrown when linear memory access exceeds allocated bounds.
 */
class WasmMemoryOutOfBoundsException extends WasmTrapException
{
    private int $offset;
    private int $size;
    private int $memoryLimit;

    public function __construct(int $offset = 0, int $size = 0, int $memoryLimit = 0, ?\Throwable $previous = null)
    {
        $this->offset = $offset;
        $this->size = $size;
        $this->memoryLimit = $memoryLimit;

        $msg = sprintf(
            'Out of bounds memory access: offset %d (0x%X), size %d bytes exceeds memory boundary %d bytes',
            $offset,
            $offset,
            $size,
            $memoryLimit
        );
        parent::__construct($msg, 'out_of_bounds_memory', 0, $previous);
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getMemoryLimit(): int
    {
        return $this->memoryLimit;
    }
}
