<?php
declare(strict_types=1);

namespace Oshim\Tests\Harness;

/**
 * Encapsulates the execution result and telemetry of a single test method.
 */
class TestResult
{
    private string $className;
    private string $methodName;
    private string $status = 'pending';
    private ?string $message = null;
    private ?string $trace = null;
    private ?string $file = null;
    private ?int $line = null;
    private ?string $diff = null;
    private float $duration = 0.0;
    private int $assertions = 0;
    private int $memoryBytes = 0;

    public function __construct(string $className, string $methodName)
    {
        $this->className = $className;
        $this->methodName = $methodName;
    }

    public function markPassed(): void
    {
        $this->status = 'passed';
    }

    public function markFailed(string $msg, string $trace = '', ?string $file = null, ?int $line = null, ?string $diff = null): void
    {
        $this->status = 'failed';
        $this->message = $msg;
        $this->trace = $trace;
        $this->file = $file;
        $this->line = $line;
        $this->diff = $diff;
    }

    public function markError(string $msg, string $trace = '', ?string $file = null, ?int $line = null): void
    {
        $this->status = 'error';
        $this->message = $msg;
        $this->trace = $trace;
        $this->file = $file;
        $this->line = $line;
    }

    public function markSkipped(string $msg = ''): void
    {
        $this->status = 'skipped';
        $this->message = $msg;
    }

    public function setMetrics(float $duration, int $assertions, int $memoryBytes = 0): void
    {
        $this->duration = $duration;
        $this->assertions = $assertions;
        $this->memoryBytes = $memoryBytes;
    }

    public function isPassed(): bool
    {
        return $this->status === 'passed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isError(): bool
    {
        return $this->status === 'error';
    }

    public function isSkipped(): bool
    {
        return $this->status === 'skipped';
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function getMethodName(): string
    {
        return $this->methodName;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getTrace(): ?string
    {
        return $this->trace;
    }

    public function getFile(): ?string
    {
        return $this->file;
    }

    public function getLine(): ?int
    {
        return $this->line;
    }

    public function getDiff(): ?string
    {
        return $this->diff;
    }

    public function getDuration(): float
    {
        return $this->duration;
    }

    public function getAssertions(): int
    {
        return $this->assertions;
    }

    public function getMemoryBytes(): int
    {
        return $this->memoryBytes;
    }
}
