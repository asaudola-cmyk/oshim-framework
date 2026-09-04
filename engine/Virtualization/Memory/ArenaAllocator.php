<?php
declare(strict_types=1);

namespace Oshim\Virtualization\Memory;

use FFI;
use RuntimeException;

/**
 * 👑 Sovereign Arena Allocator (0ms GC Alternative)
 * 
 * ADVANCED IMPLEMENTATION: Not just pointer bumping, but actual raw memory 
 * Read/Write operations bypassing PHP's Zend Engine variables.
 */
class ArenaAllocator
{
    protected ?FFI $ffi = null;
    protected ?FFI\CData $memoryBlock = null;
    protected int $capacity = 0;
    protected int $offset = 0;

    public function __construct(int $capacityBytes = 10485760) // 10MB default
    {
        if (!extension_loaded('ffi')) {
            throw new RuntimeException("FFI extension is required for Arena Allocator.");
        }

        try {
            $os = PHP_OS_FAMILY;
            $libc = match ($os) {
                'Linux' => 'libc.so.6',
                'Darwin' => 'libc.dylib',
                default => 'libc.so'
            };

            $this->ffi = FFI::cdef("
                void *malloc(size_t size);
                void free(void *ptr);
                void *memcpy(void *dest, const void *src, size_t n);
            ", $libc);

            $this->capacity = $capacityBytes;
            $this->offset = 0;

            $this->memoryBlock = $this->ffi->malloc($this->capacity);

            if ($this->memoryBlock === null) {
                throw new RuntimeException("Arena Allocator: OS denied memory allocation (OOM).");
            }
        } catch (\Throwable $e) {
            throw new RuntimeException("Arena Allocator Init Failed: " . $e->getMessage());
        }
    }

    /**
     * Write an Integer (64-bit) directly to raw OS Memory.
     * Returns the memory offset for later retrieval.
     */
    public function writeInt(int $value): int
    {
        $size = 8; // 64-bit int
        $currentOffset = $this->alignAndCheckCapacity($size);

        // Cast raw pointer to int64 pointer and write
        $ptr = $this->ffi->cast("int64_t *", $this->ffi->cast("char *", $this->memoryBlock) + $currentOffset);
        $ptr[0] = $value;

        $this->offset += $size;
        return $currentOffset;
    }

    /**
     * Read an Integer (64-bit) from raw OS Memory.
     */
    public function readInt(int $offset): int
    {
        if ($offset < 0 || $offset + 8 > $this->capacity) {
            throw new RuntimeException("Memory out of bounds read.");
        }
        $ptr = $this->ffi->cast("int64_t *", $this->ffi->cast("char *", $this->memoryBlock) + $offset);
        return $ptr[0];
    }

    /**
     * Write a String directly to raw OS Memory.
     * Returns the memory offset.
     */
    public function writeString(string $value): int
    {
        $len = strlen($value);
        $currentOffset = $this->alignAndCheckCapacity($len);

        $ptr = $this->ffi->cast("char *", $this->memoryBlock) + $currentOffset;
        FFI::memcpy($ptr, $value, $len);

        $this->offset += $len;
        return $currentOffset;
    }

    /**
     * Read a String from raw OS Memory.
     */
    public function readString(int $offset, int $length): string
    {
        if ($offset < 0 || $offset + $length > $this->capacity) {
            throw new RuntimeException("Memory out of bounds read.");
        }
        $ptr = $this->ffi->cast("char *", $this->memoryBlock) + $offset;
        return FFI::string($ptr, $length);
    }

    protected function alignAndCheckCapacity(int $size): int
    {
        // 8-byte alignment
        $alignedOffset = ($this->offset + 7) & ~7;
        if ($alignedOffset + $size > $this->capacity) {
            throw new RuntimeException("Arena Allocator OOM: Capacity exceeded.");
        }
        return $alignedOffset;
    }

    /**
     * O(1) Deallocation (0ms Pause)
     */
    public function reset(): void
    {
        $this->offset = 0;
    }

    public function getUsedBytes(): int
    {
        return $this->offset;
    }

    public function destroy(): void
    {
        if ($this->memoryBlock !== null && $this->ffi !== null) {
            $this->ffi->free($this->memoryBlock);
            $this->memoryBlock = null;
        }
    }

    public function __destruct()
    {
        $this->destroy();
    }
}
