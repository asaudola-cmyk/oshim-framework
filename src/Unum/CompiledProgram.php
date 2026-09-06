<?php

declare(strict_types=1);

namespace Unum;

use FFI\CData;

/**
 * 👑 Compiled Bare-Metal Machine Program
 * 
 * WHY: Encapsulates an executable memory page containing native x86_64 machine code.
 * Implements __invoke for zero-overhead direct function calls and automatically manages
 * virtual memory lifecycle (calls munmap on destruction to prevent memory leaks).
 */
final class CompiledProgram
{
    private HardwareExecutor $executor;
    private CData $page;
    private int $pageSize;
    private int $emittedBytes;
    private float $compilationTimeUs;

    public function __construct(
        HardwareExecutor $executor,
        CData $page,
        int $pageSize,
        int $emittedBytes,
        float $compilationTimeUs
    ) {
        $this->executor = $executor;
        $this->page = $page;
        $this->pageSize = $pageSize;
        $this->emittedBytes = $emittedBytes;
        $this->compilationTimeUs = $compilationTimeUs;
    }

    /**
     * Directly executes the compiled machine code via CPU hardware registers.
     */
    public function __invoke(int $arg1 = 0, int $arg2 = 0, int $arg3 = 0): int
    {
        return $this->executor->execute($this->page, $arg1, $arg2, $arg3);
    }

    public function getEmittedBytes(): int
    {
        return $this->emittedBytes;
    }

    public function getCompilationTimeUs(): float
    {
        return $this->compilationTimeUs;
    }

    public function __destruct()
    {
        $this->executor->freePage($this->page, $this->pageSize);
    }
}
