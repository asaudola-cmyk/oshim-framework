<?php
declare(strict_types=1);

namespace Oshim\State;

use RuntimeException;

/**
 * ⚡ Sovereign Lock-Free Shared Living Memory (Redis & Memcached Killer)
 * 
 * WHY: Replaces network TCP in-memory databases with direct POSIX shared memory pages (/dev/shm).
 * Provides atomic 64-bit compare-and-swap (CAS) and fetch-and-add operations directly
 * on CPU cache lines with zero serialization overhead and zero TCP roundtrips.
 */
final class LivingState
{
    private const MAGIC = "OSHIM_SHM\x01";
    private const HEADER_SIZE = 64;
    private const COUNTER_SLOTS = 128; // 128 atomic 64-bit counters at fixed offsets

    private string $name;
    private int $size;
    private int $handle = -1;

    public function __construct(string $name = 'oshim_living_state', int $size = 4194304)
    {
        if (!function_exists('oshim_shm_create')) {
            throw new RuntimeException('OSHIM Sovereign Engine runtime required for shared living memory');
        }

        $this->name = $name;
        $this->size = $size;
        $this->connect();
    }

    private function connect(): void
    {
        $handle = oshim_shm_open($this->name);
        if ($handle === false || $handle < 0) {
            $handle = oshim_shm_create($this->name, $this->size);
            if ($handle === false || $handle < 0) {
                throw new RuntimeException("Failed to allocate shared living memory: {$this->name}");
            }
        }
        $this->handle = (int)$handle;
    }

    /**
     * Atomically increments a 64-bit counter at the specified slot with sequential consistency.
     */
    public function atomicIncrement(int $slot = 0, int $delta = 1): int
    {
        if ($slot < 0 || $slot >= self::COUNTER_SLOTS) {
            throw new RuntimeException("Counter slot out of range (0-" . (self::COUNTER_SLOTS - 1) . ")");
        }
        $offset = self::HEADER_SIZE + ($slot * 8);
        return oshim_atomic_add64($this->handle, $offset, $delta);
    }

    /**
     * Atomically decrements a 64-bit counter.
     */
    public function atomicDecrement(int $slot = 0, int $delta = 1): int
    {
        return $this->atomicIncrement($slot, -$delta);
    }

    /**
     * Atomically compares and swaps (CAS) a 64-bit counter value.
     */
    public function atomicCas(int $slot, int $expected, int $desired): bool
    {
        if ($slot < 0 || $slot >= self::COUNTER_SLOTS) {
            throw new RuntimeException("Counter slot out of range");
        }
        $offset = self::HEADER_SIZE + ($slot * 8);
        return oshim_atomic_cas64($this->handle, $offset, $expected, $desired);
    }

    /**
     * Atomically reads a 64-bit counter value with acquire memory barrier.
     */
    public function atomicGet(int $slot = 0): int
    {
        if ($slot < 0 || $slot >= self::COUNTER_SLOTS) {
            throw new RuntimeException("Counter slot out of range");
        }
        $offset = self::HEADER_SIZE + ($slot * 8);
        return oshim_atomic_get64($this->handle, $offset);
    }

    /**
     * Writes raw bytes directly into the shared memory segment.
     */
    public function write(int $offset, string $data): int
    {
        return oshim_mmap_file_write($this->handle, $offset, $data);
    }

    /**
     * Reads raw bytes directly from the shared memory segment.
     */
    public function read(int $offset, int $length): string
    {
        return oshim_mmap_file_read($this->handle, $offset, $length);
    }

    public function close(): void
    {
        if ($this->handle >= 0) {
            oshim_shm_close($this->handle);
            $this->handle = -1;
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
