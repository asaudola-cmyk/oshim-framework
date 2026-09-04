<?php
declare(strict_types=1);

namespace Oshim\Virtualization;

/**
 * Result of command execution inside a container.
 */
final class ExecResult
{
    public function __construct(
        public readonly int $exitCode,
        public readonly string $stdout = '',
        public readonly string $stderr = '',
        public readonly float $durationMs = 0.0
    ) {}

    public function isSuccessful(): bool
    {
        return $this->exitCode === 0;
    }

    public function getExitCode(): int
    {
        return $this->exitCode;
    }

    public function getStdout(): string
    {
        return $this->stdout;
    }

    public function getStderr(): string
    {
        return $this->stderr;
    }

    public function getDurationMs(): float
    {
        return $this->durationMs;
    }

    /**
     * @return array{exit_code: int, stdout: string, stderr: string, duration_ms: float, success: bool}
     */
    public function toArray(): array
    {
        return [
            'exit_code'   => $this->exitCode,
            'stdout'      => $this->stdout,
            'stderr'      => $this->stderr,
            'duration_ms' => $this->durationMs,
            'success'     => $this->isSuccessful(),
        ];
    }
}
