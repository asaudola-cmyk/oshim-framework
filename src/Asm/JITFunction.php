<?php
declare(strict_types=1);

namespace Oshim\Asm;

use RuntimeException;

/**
 * ⚡ Sovereign JIT Function Invoker
 * 
 * WHY: Invokes compiled x86_64 machine code directly in CPU registers via
 * the sovereign oshim_exec_asm() C kernel intrinsic.
 */
final class JITFunction
{
    private string $machineCode;

    public function __construct(string $machineCode)
    {
        if ($machineCode === '') {
            throw new RuntimeException('Cannot instantiate JITFunction with empty machine instructions');
        }
        $this->machineCode = $machineCode;
    }

    /**
     * Executes the machine code function with up to two 64-bit integer arguments.
     * Maps directly to CPU registers: %rdi (arg1) and %rsi (arg2), returning %rax.
     */
    public function __invoke(int $arg1 = 0, int $arg2 = 0): int
    {
        if (!function_exists('oshim_exec_asm')) {
            throw new RuntimeException('OSHIM Sovereign Engine runtime not detected. oshim_exec_asm() unavailable.');
        }

        return oshim_exec_asm($this->machineCode, $arg1, $arg2);
    }

    /**
     * Returns the hex dump of the compiled machine code.
     */
    public function getHexDump(): string
    {
        return bin2hex($this->machineCode);
    }

    /**
     * Returns instruction length in bytes.
     */
    public function getByteLength(): int
    {
        return strlen($this->machineCode);
    }
}
