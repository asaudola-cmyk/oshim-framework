<?php
declare(strict_types=1);

namespace Oshim\Asm;

/**
 * 👑 Sovereign x86_64 Machine Code Assembler Engine
 * 
 * WHY: Enables high-performance algorithms, cryptographic routines, and tight loops
 * to compile directly into raw CPU instructions in memory, bypassing Zend bytecode
 * interpretation completely. Gives PHP bare-metal execution speed on par with C, Rust, and Zig.
 */
final class X86Assembler
{
    private string $buffer = '';

    /**
     * Creates a new fluent Assembler instance.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Emits raw byte sequence.
     */
    public function emit(string $bytes): self
    {
        $this->buffer .= $bytes;
        return $this;
    }

    /**
     * XOR %rax, %rax (Clear accumulator register / return value = 0)
     */
    public function xorRaxRax(): self
    {
        $this->buffer .= "\x48\x31\xc0";
        return $this;
    }

    /**
     * ADD %rsi, %rdi (rdi = rdi + rsi)
     */
    public function addRdiRsi(): self
    {
        $this->buffer .= "\x48\x01\xf7";
        return $this;
    }

    /**
     * SUB %rsi, %rdi (rdi = rdi - rsi)
     */
    public function subRdiRsi(): self
    {
        $this->buffer .= "\x48\x29\xf7";
        return $this;
    }

    /**
     * IMUL %rsi, %rdi (rdi = rdi * rsi)
     */
    public function imulRdiRsi(): self
    {
        $this->buffer .= "\x48\x0f\xaf\xfe";
        return $this;
    }

    /**
     * MOV %rdi, %rax (Copy rdi to return register rax)
     */
    public function movRaxRdi(): self
    {
        $this->buffer .= "\x48\x89\xf8";
        return $this;
    }

    /**
     * RET (Return from function)
     */
    public function ret(): self
    {
        $this->buffer .= "\xc3";
        return $this;
    }

    /**
     * Emits a pre-compiled high-speed arithmetic adder:
     * add %rsi, %rdi; mov %rdi, %rax; ret
     */
    public static function fastAdd(): string
    {
        return "\x48\x01\xf7\x48\x89\xf8\xc3";
    }

    /**
     * Emits a pre-compiled high-speed arithmetic multiplier:
     * imul %rsi, %rdi; mov %rdi, %rax; ret
     */
    public static function fastMultiply(): string
    {
        return "\x48\x0f\xaf\xfe\x48\x89\xf8\xc3";
    }

    /**
     * Emits a tight CPU loop calculating sum of 1 to N:
     * xor %rax, %rax; test %rdi, %rdi; jle done; loop: add %rdi, %rax; dec %rdi; jnz loop; done: ret
     */
    public static function fastSumLoop(): string
    {
        return "\x48\x31\xc0\x48\x85\xff\x7e\x08\x48\x01\xf8\x48\xff\xcf\x75\xf8\xc3";
    }

    /**
     * Compiles the buffer into a callable JITFunction.
     */
    public function compile(): JITFunction
    {
        return new JITFunction($this->buffer);
    }

    /**
     * Returns the raw machine code binary string.
     */
    public function getBytes(): string
    {
        return $this->buffer;
    }
}
