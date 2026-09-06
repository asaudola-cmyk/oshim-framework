<?php

declare(strict_types=1);

namespace Unum;

use RuntimeException;

/**
 * 👑 UNUM High-Level Sovereign Compiler
 * 
 * WHY: Translates high-level computational constructs and algorithms into streams
 * of 64-bit Universal Numbers, optimizes them using Landauer entropy principles,
 * and compiles them to bare-metal x86_64 machine code in microseconds.
 */
final class Compiler
{
    private HardwareExecutor $executor;

    public function __construct(?HardwareExecutor $executor = null)
    {
        $this->executor = $executor ?? new HardwareExecutor();
    }

    /**
     * Compiles an array of Universal Numbers into an executable machine code program.
     * 
     * @param UniversalNumber[] $numbers
     */
    public function compile(array $numbers, bool $optimizeEntropy = false): CompiledProgram
    {
        if (empty($numbers)) {
            throw new RuntimeException("Cannot compile an empty instruction stream.");
        }

        if ($optimizeEntropy) {
            $numbers = PhysicsMathEngine::optimizeInstructionEntropy($numbers);
        }

        $t0 = hrtime(true);
        $result = $this->executor->compile($numbers);
        $t1 = hrtime(true);

        $compileTimeUs = ($t1 - $t0) / 1000.0;

        return new CompiledProgram(
            $this->executor,
            $result['page'],
            $result['size'],
            $result['emitted_bytes'],
            $compileTimeUs
        );
    }

    /**
     * Builds a bare-metal linear equation evaluator: f(x) = a * x + b
     * 
     * Instructions emitted:
     * 1. mov rax, rdi      (rax = x, input argument 1)
     * 2. mov rdx, a        (rdx = multiplier a)
     * 3. imul rax, rdx     (rax = a * x)
     * 4. add rax, b        (rax = (a * x) + b)
     * 5. ret
     */
    public function buildLinearEquation(int $a, int $b): CompiledProgram
    {
        $prog = [
            UniversalNumber::pack(UniversalNumber::OP_MOV_REG, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_RAX, UniversalNumber::REG_RDI),
            UniversalNumber::pack(UniversalNumber::OP_MOV_IMM, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_RDX, 0, 0, $a),
            UniversalNumber::pack(UniversalNumber::OP_MUL_REG, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_RAX, UniversalNumber::REG_RDX),
            UniversalNumber::pack(UniversalNumber::OP_ADD_IMM, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_RAX, 0, 0, $b),
            UniversalNumber::pack(UniversalNumber::OP_RET, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_RAX),
        ];

        return $this->compile($prog);
    }

    /**
     * Builds a bare-metal hardware loop accumulator:
     * Sums 1 to N using CPU decrement and conditional branch instructions (dec rcx; jnz).
     * Runs in hardware registers without any interpreter or bytecode overhead.
     * 
     * Instructions emitted:
     * 1. mov rcx, rdi      (rcx = N, loop counter from argument 1)
     * 2. xor rax, rax      (rax = 0, accumulator)
     * 3. add rax, rcx      (loop body: accumulator += counter)
     * 4. loop_dec rcx      (dec rcx; jnz to instruction 3)
     * 5. ret
     */
    public function buildHardwareLoopSum(): CompiledProgram
    {
        $prog = [
            UniversalNumber::pack(UniversalNumber::OP_MOV_REG, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_RCX, UniversalNumber::REG_RDI),
            UniversalNumber::pack(UniversalNumber::OP_XOR_REG, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_RAX, UniversalNumber::REG_RAX),
            UniversalNumber::pack(UniversalNumber::OP_LOOP_START, UniversalNumber::TYPE_RAW_INT64),
            UniversalNumber::pack(UniversalNumber::OP_ADD_REG, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_RAX, UniversalNumber::REG_RCX),
            UniversalNumber::pack(UniversalNumber::OP_LOOP_DEC, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_RCX),
            UniversalNumber::pack(UniversalNumber::OP_RET, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_RAX),
        ];

        return $this->compile($prog);
    }

    /**
     * Builds a quadratic polynomial evaluator: f(x) = c2 * x^2 + c1 * x + c0
     */
    public function buildPolynomial(int $c2, int $c1, int $c0): CompiledProgram
    {
        $prog = [
            /* rsi = x (save copy) */
            UniversalNumber::pack(UniversalNumber::OP_MOV_REG, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_RSI, UniversalNumber::REG_RDI),
            /* rax = x * x */
            UniversalNumber::pack(UniversalNumber::OP_MOV_REG, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_RAX, UniversalNumber::REG_RSI),
            UniversalNumber::pack(UniversalNumber::OP_MUL_REG, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_RAX, UniversalNumber::REG_RSI),
            /* rax = c2 * x^2 */
            UniversalNumber::pack(UniversalNumber::OP_MOV_IMM, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_RDX, 0, 0, $c2),
            UniversalNumber::pack(UniversalNumber::OP_MUL_REG, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_RAX, UniversalNumber::REG_RDX),
            /* rbx = c1 * x */
            UniversalNumber::pack(UniversalNumber::OP_MOV_IMM, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_RBX, 0, 0, $c1),
            UniversalNumber::pack(UniversalNumber::OP_MUL_REG, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_RBX, UniversalNumber::REG_RSI),
            /* rax = (c2 * x^2) + (c1 * x) */
            UniversalNumber::pack(UniversalNumber::OP_ADD_REG, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_RAX, UniversalNumber::REG_RBX),
            /* rax += c0 */
            UniversalNumber::pack(UniversalNumber::OP_ADD_IMM, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_RAX, 0, 0, $c0),
            UniversalNumber::pack(UniversalNumber::OP_RET, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_RAX),
        ];

        return $this->compile($prog);
    }

    /**
     * Builds a bare-metal hardware loop evaluating and summing a quadratic polynomial
     * for x = 1 to N: Sum = sum_{x=1}^N (c2 * x^2 + c1 * x + c0)
     * All registers are kept in hardware silicon with zero userland marshalling.
     */
    public function buildPolynomialSumLoop(int $c2, int $c1, int $c0): CompiledProgram
    {
        $prog = [
            /* Initialize r12 = N (counter from rdi) */
            UniversalNumber::pack(UniversalNumber::OP_MOV_REG, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_R12, UniversalNumber::REG_RDI),
            /* Initialize r13 = 0 (total accumulator) */
            UniversalNumber::pack(UniversalNumber::OP_XOR_REG, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_R13, UniversalNumber::REG_R13),
            /* Load constants into registers r14 = c2, r15 = c1 */
            UniversalNumber::pack(UniversalNumber::OP_MOV_IMM, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_R14, 0, 0, $c2),
            UniversalNumber::pack(UniversalNumber::OP_MOV_IMM, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_R15, 0, 0, $c1),
            
            /* Demarcate loop start */
            UniversalNumber::pack(UniversalNumber::OP_LOOP_START, UniversalNumber::TYPE_RAW_INT64),
            
            /* Compute c2 * x^2 into rax */
            UniversalNumber::pack(UniversalNumber::OP_MOV_REG, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_RAX, UniversalNumber::REG_R12),
            UniversalNumber::pack(UniversalNumber::OP_MUL_REG, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_RAX, UniversalNumber::REG_R12),
            UniversalNumber::pack(UniversalNumber::OP_MUL_REG, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_RAX, UniversalNumber::REG_R14),
            
            /* Compute c1 * x into rdx */
            UniversalNumber::pack(UniversalNumber::OP_MOV_REG, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_RDX, UniversalNumber::REG_R12),
            UniversalNumber::pack(UniversalNumber::OP_MUL_REG, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_RDX, UniversalNumber::REG_R15),
            
            /* Accumulate polynomial terms: rax = (c2*x^2) + (c1*x) + c0 */
            UniversalNumber::pack(UniversalNumber::OP_ADD_REG, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_RAX, UniversalNumber::REG_RDX),
            UniversalNumber::pack(UniversalNumber::OP_ADD_IMM, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_RAX, 0, 0, $c0),
            
            /* Add to total accumulator r13 */
            UniversalNumber::pack(UniversalNumber::OP_ADD_REG, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_R13, UniversalNumber::REG_RAX),
            
            /* Decrement loop counter r12 and repeat if r12 > 0 */
            UniversalNumber::pack(UniversalNumber::OP_LOOP_DEC, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_R12),
            
            /* Return total in rax */
            UniversalNumber::pack(UniversalNumber::OP_MOV_REG, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_RAX, UniversalNumber::REG_R13),
            UniversalNumber::pack(UniversalNumber::OP_RET, UniversalNumber::TYPE_RAW_INT64, UniversalNumber::REG_RAX),
        ];

        return $this->compile($prog);
    }
}
