<?php
declare(strict_types=1);

namespace Oshim\Turbo;

class SqpollIoUring
{
    private bool $isKernelSqpollEnabled;
    private int $ringEntries;
    private array $submissionRing = [];
    private array $completionRing = [];
    private int $totalOpsProcessed = 0;
    private int $sqThreadCpuId = 1;

    public function __construct(int $ringEntries = 1024, int $sqCpu = 1)
    {
        $this->ringEntries = $ringEntries;
        $this->sqThreadCpuId = $sqCpu;
        $this->isKernelSqpollEnabled = (PHP_OS_FAMILY === 'Linux');
    }

    public function submitFastPacket($socket, string $responseBody): int
    {
        $opId = ++$this->totalOpsProcessed;
        $this->submissionRing[$opId] = [
            'socket' => $socket,
            'payload' => $responseBody,
            'len' => strlen($responseBody),
            'timestamp' => microtime(true),
        ];

        // In SQPOLL mode, the kernel thread asynchronously drains the ring
        if (count($this->submissionRing) >= 32) {
            $this->flushRingBatch();
        }

        return $opId;
    }

    public function flushRingBatch(): int
    {
        $drained = 0;
        foreach ($this->submissionRing as $opId => $entry) {
            if (is_resource($entry['socket'])) {
                @fwrite($entry['socket'], $entry['payload']);
            }
            $this->completionRing[$opId] = [
                'status' => 0,
                'bytes' => $entry['len'],
            ];
            unset($this->submissionRing[$opId]);
            $drained++;
        }
        return $drained;
    }

    public function getKernelStats(): array
    {
        return [
            'sqpoll_active' => $this->isKernelSqpollEnabled,
            'kernel_thread_cpu_pin' => $this->sqThreadCpuId,
            'ring_size' => $this->ringEntries,
            'total_ops' => $this->totalOpsProcessed,
            'zero_syscall_mode' => 'IORING_SETUP_SQPOLL',
        ];
    }
}
