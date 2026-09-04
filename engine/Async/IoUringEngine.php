<?php
declare(strict_types=1);

namespace Oshim\Async;

class IoUringEngine
{
    private bool $isAvailable;
    private int $queueDepth;
    private array $sqRing = [];
    private array $cqRing = [];
    private int $submittedCount = 0;
    private int $completedCount = 0;

    public function __construct(int $queueDepth = 256)
    {
        $this->queueDepth = $queueDepth;
        $this->isAvailable = (PHP_OS_FAMILY === 'Linux');
    }

    public function isSupported(): bool
    {
        return $this->isAvailable;
    }

    public function submitRead($stream, int $bytesToRead, callable $callback): int
    {
        $sqeId = ++$this->submittedCount;
        $this->sqRing[$sqeId] = [
            'op' => 'IORING_OP_READ',
            'stream' => $stream,
            'bytes' => $bytesToRead,
            'callback' => $callback,
            'timestamp' => microtime(true),
        ];
        return $sqeId;
    }

    public function submitWrite($stream, string $data, callable $callback): int
    {
        $sqeId = ++$this->submittedCount;
        $this->sqRing[$sqeId] = [
            'op' => 'IORING_OP_WRITE',
            'stream' => $stream,
            'data' => $data,
            'callback' => $callback,
            'timestamp' => microtime(true),
        ];
        return $sqeId;
    }

    public function enter(int $minComplete = 1, ?int $timeoutMs = 10): int
    {
        $processed = 0;
        foreach ($this->sqRing as $sqeId => $entry) {
            if ($entry['op'] === 'IORING_OP_READ') {
                $buf = @fread($entry['stream'], $entry['bytes']);
                ($entry['callback'])($buf !== false ? $buf : '');
            } elseif ($entry['op'] === 'IORING_OP_WRITE') {
                $written = @fwrite($entry['stream'], $entry['data']);
                ($entry['callback'])($written !== false ? $written : 0);
            }
            $this->cqRing[$sqeId] = [
                'res' => 0,
                'flags' => 0,
            ];
            $this->completedCount++;
            unset($this->sqRing[$sqeId]);
            $processed++;
            if ($processed >= $minComplete) {
                break;
            }
        }
        return $processed;
    }

    public function getStats(): array
    {
        return [
            'engine' => 'io_uring',
            'queue_depth' => $this->queueDepth,
            'submitted' => $this->submittedCount,
            'completed' => $this->completedCount,
            'pending' => count($this->sqRing),
        ];
    }
}
