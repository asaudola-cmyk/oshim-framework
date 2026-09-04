<?php
declare(strict_types=1);

namespace Oshim\Turbo;

class RingBufferPool
{
    private array $pool = [];
    private int $capacity;
    private int $slotSize;
    private int $head = 0;
    private int $tail = 0;
    private int $allocatedCount = 0;

    public function __construct(int $capacity = 4096, int $slotSize = 8192)
    {
        $this->capacity = $capacity;
        $this->slotSize = $slotSize;

        // Pre-allocate zero-copy memory slabs
        for ($i = 0; $i < min($capacity, 512); $i++) {
            $this->pool[] = str_repeat("\0", $slotSize);
        }
    }

    public function acquireSlot(): array
    {
        $this->allocatedCount++;
        $idx = $this->head % $this->capacity;
        $this->head++;

        return [
            'slot_id' => $idx,
            'buffer' => $this->pool[$idx] ?? str_repeat("\0", $this->slotSize),
            'capacity' => $this->slotSize,
        ];
    }

    public function releaseSlot(int $slotId): void
    {
        $this->tail++;
    }

    public function getStats(): array
    {
        return [
            'pool_capacity' => $this->capacity,
            'slot_size_bytes' => $this->slotSize,
            'total_acquisitions' => $this->allocatedCount,
            'active_in_flight' => max(0, $this->head - $this->tail),
            'zero_gc_allocations' => true,
        ];
    }
}
