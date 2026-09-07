<?php

declare(strict_types=1);

namespace Unum\Dsl;

require_once __DIR__ . '/Ast.php';

use RuntimeException;
use Unum\UniversalNumber;

/**
 * 👑 UNUM Natural Expression & Algorithmic DSL JIT Compiler
 * 
 * WHY: Traditional interpreters execute bytecode through expensive loops and stack frames.
 * DslCompiler parses natural mathematical expressions and algorithmic scripts, performs register
 * allocation across x86_64 silicon registers, and generates pure 64-bit Universal Numbers
 * ready for immediate sub-microsecond bare-metal machine code emission.
 */
final class DslCompiler
{
    /** @var list<UniversalNumber> */
    private array $instructions = [];

    /** @var array<string, int> Map variable name to CPU register ID */
    private array $variables = [];

    /** @var array<string, int> Map parameter name to input register (RDI, RSI, RDX) */
    private array $paramMap = [];

    /** @var array<int, bool> Set of currently allocated registers */
    private array $allocatedRegs = [];

    /** Available register pool for local variables and scratchpad temporaries */
    private const REGISTER_POOL = [
        UniversalNumber::REG_RBX,
        UniversalNumber::REG_R8,
        UniversalNumber::REG_R9,
        UniversalNumber::REG_R10,
        UniversalNumber::REG_R11,
        UniversalNumber::REG_R12,
        UniversalNumber::REG_R13,
        UniversalNumber::REG_R14,
        UniversalNumber::REG_R15,
    ];

    /** Dedicated loop counter pool (RCX, then callee-saved) */
    private const COUNTER_POOL = [
        UniversalNumber::REG_RCX,
        UniversalNumber::REG_R14,
        UniversalNumber::REG_R15,
    ];

    /**
     * Compiles a single mathematical expression into an array of Universal Numbers.
     * 
     * @param string $expression e.g. "3 * x^2 + 4 * x + 10"
     * @param list<string> $parameters e.g. ['x']
     * @return list<UniversalNumber>
     */
    public function compileExpression(string $expression, array $parameters = []): array
    {
        $this->resetState();
        $this->bindParameters($parameters);

        $parser = Parser::fromString($expression);
        $ast = $parser->parseExpressionOnly();

        /* Evaluate expression directly into return register RAX */
        $this->compileExpr($ast, UniversalNumber::REG_RAX);

        /* Emit return */
        $this->instructions[] = UniversalNumber::pack(
            UniversalNumber::OP_RET,
            UniversalNumber::TYPE_RAW_INT64,
            UniversalNumber::REG_RAX
        );

        return $this->instructions;
    }

    /**
     * Compiles a full algorithmic DSL code script into an array of Universal Numbers.
     * 
     * @param string $code
     * @param list<string> $parameters
     * @return list<UniversalNumber>
     */
    public function compileCode(string $code, array $parameters = []): array
    {
        $this->resetState();
        $this->bindParameters($parameters);

        $parser = Parser::fromString($code);
        $program = $parser->parseProgram();

        $lastResultReg = UniversalNumber::REG_RAX;
        foreach ($program->statements as $statement) {
            $lastResultReg = $this->compileStatement($statement);
        }

        /* If last instruction is not RET, move last result to RAX and emit RET */
        $lastOp = null;
        if (!empty($this->instructions)) {
            $lastUnum = end($this->instructions);
            $lastOp = ($lastUnum->toInt() >> UniversalNumber::SHIFT_OPCODE) & UniversalNumber::MASK_BYTE;
        }

        if ($lastOp !== UniversalNumber::OP_RET) {
            if ($lastResultReg !== UniversalNumber::REG_RAX) {
                $this->instructions[] = UniversalNumber::pack(
                    UniversalNumber::OP_MOV_REG,
                    UniversalNumber::TYPE_RAW_INT64,
                    UniversalNumber::REG_RAX,
                    $lastResultReg
                );
            }
            $this->instructions[] = UniversalNumber::pack(
                UniversalNumber::OP_RET,
                UniversalNumber::TYPE_RAW_INT64,
                UniversalNumber::REG_RAX
            );
        }

        return $this->instructions;
    }

    private function resetState(): void
    {
        $this->instructions = [];
        $this->variables = [];
        $this->paramMap = [];
        $this->allocatedRegs = [];
    }

    /**
     * Binds input parameters to System V AMD64 ABI calling convention registers.
     * arg1 -> RDI, arg2 -> RSI, arg3 -> RDX.
     * 
     * @param list<string> $parameters
     */
    private function bindParameters(array $parameters): void
    {
        $abiArgs = [
            UniversalNumber::REG_RDI,
            UniversalNumber::REG_RSI,
            UniversalNumber::REG_RDX,
        ];

        foreach ($parameters as $index => $paramName) {
            if ($index >= count($abiArgs)) {
                throw new RuntimeException("Maximum 3 hardware parameters supported by unum_execute (got parameter '{$paramName}' at index {$index}).");
            }
            $reg = $abiArgs[$index];
            $this->paramMap[$paramName] = $reg;
            $this->allocatedRegs[$reg] = true;
        }
    }

    private function allocRegister(): int
    {
        foreach (self::REGISTER_POOL as $reg) {
            if (!isset($this->allocatedRegs[$reg])) {
                $this->allocatedRegs[$reg] = true;
                return $reg;
            }
        }
        throw new RuntimeException("Silicon register spill: out of hardware CPU registers.");
    }

    private function freeRegister(int $reg): void
    {
        unset($this->allocatedRegs[$reg]);
    }

    private function allocCounterRegister(): int
    {
        foreach (self::COUNTER_POOL as $reg) {
            if (!isset($this->allocatedRegs[$reg])) {
                $this->allocatedRegs[$reg] = true;
                return $reg;
            }
        }
        return $this->allocRegister();
    }

    /**
     * Compiles a statement and returns the register containing the statement result.
     */
    private function compileStatement(AstNode $stmt): int
    {
        if ($stmt instanceof ReturnNode) {
            $this->compileExpr($stmt->expr, UniversalNumber::REG_RAX);
            $this->instructions[] = UniversalNumber::pack(
                UniversalNumber::OP_RET,
                UniversalNumber::TYPE_RAW_INT64,
                UniversalNumber::REG_RAX
            );
            return UniversalNumber::REG_RAX;
        }

        if ($stmt instanceof AssignNode) {
            $varName = $stmt->varName;
            if (!isset($this->variables[$varName])) {
                $reg = $this->allocRegister();
                $this->variables[$varName] = $reg;
            } else {
                $reg = $this->variables[$varName];
            }

            $this->compileExpr($stmt->expr, $reg);
            return $reg;
        }

        if ($stmt instanceof LoopNode) {
            $counterReg = $this->allocCounterRegister();
            /* Load loop count into counter register */
            $this->compileExpr($stmt->countExpr, $counterReg);

            /* Mark loop start offset in machine code */
            $this->instructions[] = UniversalNumber::pack(
                UniversalNumber::OP_LOOP_START,
                UniversalNumber::TYPE_RAW_INT64
            );

            /* Compile each statement in loop body */
            $lastReg = UniversalNumber::REG_RAX;
            foreach ($stmt->body as $bodyStmt) {
                $lastReg = $this->compileStatement($bodyStmt);
            }

            /* Decrement loop counter and branch backwards if non-zero */
            $this->instructions[] = UniversalNumber::pack(
                UniversalNumber::OP_LOOP_DEC,
                UniversalNumber::TYPE_RAW_INT64,
                $counterReg
            );

            $this->freeRegister($counterReg);
            return $lastReg;
        }

        /* Standalone expression statement */
        $temp = $this->allocRegister();
        $this->compileExpr($stmt, $temp);
        $this->freeRegister($temp);
        return $temp;
    }

    /**
     * Compiles an expression AST node directly into targetReg.
     */
    private function compileExpr(AstNode $node, int $targetReg): void
    {
        if ($node instanceof NumberNode) {
            $this->instructions[] = UniversalNumber::pack(
                UniversalNumber::OP_MOV_IMM,
                UniversalNumber::TYPE_RAW_INT64,
                $targetReg,
                0,
                0,
                $node->value
            );
            return;
        }

        if ($node instanceof VariableNode) {
            $name = $node->name;
            if (isset($this->paramMap[$name])) {
                $srcReg = $this->paramMap[$name];
            } elseif (isset($this->variables[$name])) {
                $srcReg = $this->variables[$name];
            } else {
                throw new RuntimeException("Undefined identifier '{$name}' in expression.");
            }

            if ($targetReg !== $srcReg) {
                $this->instructions[] = UniversalNumber::pack(
                    UniversalNumber::OP_MOV_REG,
                    UniversalNumber::TYPE_RAW_INT64,
                    $targetReg,
                    $srcReg
                );
            }
            return;
        }

        if ($node instanceof BinaryOpNode) {
            $this->compileBinaryOp($node, $targetReg);
            return;
        }

        throw new RuntimeException("Unsupported AST node type: " . get_class($node));
    }

    /**
     * Compiles binary operators (+, -, *, ^) with register reuse optimization.
     */
    private function compileBinaryOp(BinaryOpNode $node, int $targetReg): void
    {
        $op = $node->op;

        /* Exponentiation: base ^ exp */
        if ($op === '^') {
            if ($node->right instanceof NumberNode) {
                $exp = $node->right->value;
                if ($exp === 0) {
                    $this->instructions[] = UniversalNumber::pack(
                        UniversalNumber::OP_MOV_IMM,
                        UniversalNumber::TYPE_RAW_INT64,
                        $targetReg,
                        0,
                        0,
                        1
                    );
                    return;
                }
                if ($exp === 1) {
                    $this->compileExpr($node->left, $targetReg);
                    return;
                }
                if ($exp > 1) {
                    $this->compileExpr($node->left, $targetReg);
                    $baseCopy = $this->allocRegister();
                    $this->instructions[] = UniversalNumber::pack(
                        UniversalNumber::OP_MOV_REG,
                        UniversalNumber::TYPE_RAW_INT64,
                        $baseCopy,
                        $targetReg
                    );

                    for ($k = 1; $k < $exp; $k++) {
                        $this->instructions[] = UniversalNumber::pack(
                            UniversalNumber::OP_MUL_REG,
                            UniversalNumber::TYPE_RAW_INT64,
                            $targetReg,
                            $baseCopy
                        );
                    }
                    $this->freeRegister($baseCopy);
                    return;
                }
            }
            throw new RuntimeException("Exponentiation currently requires non-negative integer power.");
        }

        /* Addition */
        if ($op === '+') {
            if ($node->right instanceof NumberNode) {
                $this->compileExpr($node->left, $targetReg);
                $this->instructions[] = UniversalNumber::pack(
                    UniversalNumber::OP_ADD_IMM,
                    UniversalNumber::TYPE_RAW_INT64,
                    $targetReg,
                    0,
                    0,
                    $node->right->value
                );
                return;
            }

            $this->compileExpr($node->left, $targetReg);
            $scratch = $this->allocRegister();
            $this->compileExpr($node->right, $scratch);
            $this->instructions[] = UniversalNumber::pack(
                UniversalNumber::OP_ADD_REG,
                UniversalNumber::TYPE_RAW_INT64,
                $targetReg,
                $scratch
            );
            $this->freeRegister($scratch);
            return;
        }

        /* Subtraction */
        if ($op === '-') {
            if ($node->right instanceof NumberNode) {
                $this->compileExpr($node->left, $targetReg);
                $this->instructions[] = UniversalNumber::pack(
                    UniversalNumber::OP_SUB_IMM,
                    UniversalNumber::TYPE_RAW_INT64,
                    $targetReg,
                    0,
                    0,
                    $node->right->value
                );
                return;
            }

            $this->compileExpr($node->left, $targetReg);
            $scratch = $this->allocRegister();
            $this->compileExpr($node->right, $scratch);
            $this->instructions[] = UniversalNumber::pack(
                UniversalNumber::OP_SUB_REG,
                UniversalNumber::TYPE_RAW_INT64,
                $targetReg,
                $scratch
            );
            $this->freeRegister($scratch);
            return;
        }

        /* Multiplication */
        if ($op === '*') {
            if ($node->right instanceof NumberNode) {
                $this->compileExpr($node->left, $targetReg);
                $scratch = $this->allocRegister();
                $this->instructions[] = UniversalNumber::pack(
                    UniversalNumber::OP_MOV_IMM,
                    UniversalNumber::TYPE_RAW_INT64,
                    $scratch,
                    0,
                    0,
                    $node->right->value
                );
                $this->instructions[] = UniversalNumber::pack(
                    UniversalNumber::OP_MUL_REG,
                    UniversalNumber::TYPE_RAW_INT64,
                    $targetReg,
                    $scratch
                );
                $this->freeRegister($scratch);
                return;
            }

            $this->compileExpr($node->left, $targetReg);
            $scratch = $this->allocRegister();
            $this->compileExpr($node->right, $scratch);
            $this->instructions[] = UniversalNumber::pack(
                UniversalNumber::OP_MUL_REG,
                UniversalNumber::TYPE_RAW_INT64,
                $targetReg,
                $scratch
            );
            $this->freeRegister($scratch);
            return;
        }

        throw new RuntimeException("Unsupported binary operator '{$op}'.");
    }
}
