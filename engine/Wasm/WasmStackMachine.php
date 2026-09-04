<?php
declare(strict_types=1);

namespace Oshim\Wasm;

use Oshim\Wasm\Exceptions\WasmException;
use Oshim\Wasm\Exceptions\WasmTrapException;
use Oshim\Wasm\Exceptions\WasmMemoryOutOfBoundsException;
use Oshim\Wasm\Exceptions\WasmFuelExhaustedException;
use Oshim\Wasm\Exceptions\WasmStackOverflowException;

/**
 * WebAssembly 1.0 (MVP) Stack Machine Interpreter.
 * Executes bytecode instructions with fuel metering, call stack limits, and bounds enforcement.
 */
class WasmStackMachine
{
    private WasmInstance $instance;
    private int $fuelLimit = 0; // 0 = unlimited
    private int $instructionsExecuted = 0;
    private int $maxCallDepth = 1024;
    private int $callDepth = 0;
    private float $timeoutSeconds = 0.0; // 0.0 = unlimited
    private float $startTime = 0.0;

    /** @var array<int, array<int, array{type: string, elsePc: ?int, endPc: int, resultCount: int}>> Cached jump maps */
    private array $jumpTables = [];

    public function __construct(
        WasmInstance $instance,
        int $fuelLimit = 0,
        int $maxCallDepth = 1024,
        float $timeoutSeconds = 0.0
    ) {
        $this->instance = $instance;
        $this->fuelLimit = $fuelLimit;
        $this->maxCallDepth = $maxCallDepth;
        $this->timeoutSeconds = $timeoutSeconds;
    }

    /**
     * Get total instructions executed during run.
     */
    public function getInstructionsExecuted(): int
    {
        return $this->instructionsExecuted;
    }

    /**
     * Reset execution stats.
     */
    public function resetStats(): void
    {
        $this->instructionsExecuted = 0;
        $this->callDepth = 0;
        $this->startTime = microtime(true);
    }

    /**
     * Invoke a function by its global function index.
     *
     * @param int $funcIndex Global function index
     * @param list<int|float> $args Arguments matching function parameters
     * @return mixed Single return value, array of values, or null if void
     */
    public function invoke(int $funcIndex, array $args = []): mixed
    {
        if ($this->callDepth === 0) {
            $this->startTime = microtime(true);
        }

        $this->callDepth++;
        if ($this->callDepth > $this->maxCallDepth) {
            $depth = $this->callDepth;
            $this->callDepth--;
            throw new WasmStackOverflowException($depth, $this->maxCallDepth);
        }

        try {
            $module = $this->instance->getModule();

            // 1. Check if it's an imported host function
            if ($module->isImportedFunction($funcIndex)) {
                $hostCallable = $this->instance->getImportedFunctionCallable($funcIndex);
                if ($hostCallable === null) {
                    $imp = $module->getImportedFunction($funcIndex);
                    $name = $imp ? "{$imp['module']}.{$imp['name']}" : "func #{$funcIndex}";
                    throw new WasmTrapException("Unresolved host function import: {$name}", 'unresolved_import');
                }
                return $hostCallable(...$args);
            }

            // 2. Internal WebAssembly Function
            $codeDef = $module->getInternalFunctionCode($funcIndex);
            if ($codeDef === null) {
                throw new WasmTrapException("Function body not found for function index {$funcIndex}", 'invalid_function_index');
            }

            $funcType = $module->getFunctionType($funcIndex);
            $expectedParamCount = count($funcType['params'] ?? []);
            if (count($args) !== $expectedParamCount) {
                throw new WasmTrapException(
                    sprintf('Argument count mismatch for function %d: expected %d, got %d', $funcIndex, $expectedParamCount, count($args)),
                    'argument_mismatch'
                );
            }

            // Build locals: parameters + function-defined locals initialized to 0
            $locals = [];
            foreach ($args as $idx => $arg) {
                $pType = $funcType['params'][$idx] ?? WasmModule::TYPE_I32;
                $locals[] = $this->normalizeValue($arg, $pType);
            }
            foreach ($codeDef['locals'] as $localType) {
                $locals[] = match ($localType) {
                    WasmModule::TYPE_F32, WasmModule::TYPE_F64 => 0.0,
                    default => 0,
                };
            }

            $result = $this->executeBytecode($funcIndex, $codeDef['code'], $locals, $funcType['results'] ?? []);
            return $result;
        } finally {
            $this->callDepth--;
        }
    }

    /**
     * Evaluate constant init expression (e.g. for globals, data, elements).
     */
    public function evaluateInitExpr(string $exprBytes): int|float
    {
        $stack = [];
        $pc = 0;
        $len = strlen($exprBytes);

        while ($pc < $len) {
            $opcode = ord($exprBytes[$pc++]);
            if ($opcode === 0x0B) { // end
                break;
            }

            switch ($opcode) {
                case 0x41: // i32.const
                    $val = $this->readVarInt32($exprBytes, $pc);
                    $stack[] = $this->normalizeI32($val);
                    break;
                case 0x42: // i64.const
                    $val = $this->readVarInt64($exprBytes, $pc);
                    $stack[] = (int) $val;
                    break;
                case 0x43: // f32.const
                    $bytes = substr($exprBytes, $pc, 4);
                    $pc += 4;
                    $stack[] = (float) unpack('g', $bytes)[1];
                    break;
                case 0x44: // f64.const
                    $bytes = substr($exprBytes, $pc, 8);
                    $pc += 8;
                    $stack[] = (float) unpack('e', $bytes)[1];
                    break;
                case 0x23: // global.get
                    $gIdx = $this->readVarUint32($exprBytes, $pc);
                    $global = $this->instance->getGlobal($gIdx);
                    $stack[] = $global->getValue();
                    break;
                default:
                    throw new WasmTrapException(sprintf('Unsupported opcode in init expression: 0x%02X', $opcode), 'invalid_init_expr');
            }
        }

        return empty($stack) ? 0 : array_pop($stack);
    }

    /**
     * Execute function bytecode.
     *
     * @param int $funcIndex
     * @param string $bytecode
     * @param list<int|float> $locals
     * @param list<int> $resultTypes
     * @return mixed
     */
    private function executeBytecode(int $funcIndex, string $bytecode, array &$locals, array $resultTypes): mixed
    {
        $jumpMap = $this->getJumpMap($funcIndex, $bytecode);
        $len = strlen($bytecode);
        $pc = 0;

        /** @var list<int|float> Operand stack */
        $stack = [];

        /**
         * Control stack frames:
         * array{
         *   type: string ('block'|'loop'|'if'|'func'),
         *   startPc: int,
         *   elsePc: ?int,
         *   endPc: int,
         *   stackHeight: int,
         *   resultCount: int
         * }
         */
        $controlStack = [
            [
                'type'        => 'func',
                'startPc'     => 0,
                'elsePc'      => null,
                'endPc'       => $len,
                'stackHeight' => 0,
                'resultCount' => count($resultTypes),
            ]
        ];

        while ($pc < $len) {
            // Fuel Check
            if ($this->fuelLimit > 0) {
                $this->instructionsExecuted++;
                if ($this->instructionsExecuted > $this->fuelLimit) {
                    throw new WasmFuelExhaustedException($this->fuelLimit, $this->instructionsExecuted);
                }
            }

            // Timeout Check
            if ($this->timeoutSeconds > 0.0 && ($this->instructionsExecuted % 5000 === 0)) {
                if ((microtime(true) - $this->startTime) > $this->timeoutSeconds) {
                    throw new WasmTrapException(
                        sprintf('WebAssembly execution timed out after %.2f seconds', $this->timeoutSeconds),
                        'timeout'
                    );
                }
            }

            $currentOpPc = $pc;
            $opcode = ord($bytecode[$pc++]);

            switch ($opcode) {
                // --- Control Flow ---
                case 0x00: // unreachable
                    throw new WasmTrapException('unreachable instruction executed', 'unreachable');

                case 0x01: // nop
                    break;

                case 0x02: // block
                    $blockType = $this->readBlockType($bytecode, $pc);
                    $meta = $jumpMap[$currentOpPc];
                    $controlStack[] = [
                        'type'        => 'block',
                        'startPc'     => $pc,
                        'elsePc'      => null,
                        'endPc'       => $meta['endPc'],
                        'stackHeight' => count($stack),
                        'resultCount' => $meta['resultCount'],
                    ];
                    break;

                case 0x03: // loop
                    $blockType = $this->readBlockType($bytecode, $pc);
                    $meta = $jumpMap[$currentOpPc];
                    $controlStack[] = [
                        'type'        => 'loop',
                        'startPc'     => $currentOpPc, // loop branch target is the start of the loop
                        'elsePc'      => null,
                        'endPc'       => $meta['endPc'],
                        'stackHeight' => count($stack),
                        'resultCount' => $meta['resultCount'],
                    ];
                    break;

                case 0x04: // if
                    $blockType = $this->readBlockType($bytecode, $pc);
                    $meta = $jumpMap[$currentOpPc];
                    $cond = array_pop($stack);
                    if ($cond !== 0) {
                        // Enter 'then' branch
                        $controlStack[] = [
                            'type'        => 'if',
                            'startPc'     => $pc,
                            'elsePc'      => $meta['elsePc'],
                            'endPc'       => $meta['endPc'],
                            'stackHeight' => count($stack),
                            'resultCount' => $meta['resultCount'],
                        ];
                    } else {
                        // If false, jump to 'else' or 'end'
                        if ($meta['elsePc'] !== null) {
                            $pc = $meta['elsePc'] + 1; // Step past 0x05 (else)
                            $controlStack[] = [
                                'type'        => 'if',
                                'startPc'     => $pc,
                                'elsePc'      => $meta['elsePc'],
                                'endPc'       => $meta['endPc'],
                                'stackHeight' => count($stack),
                                'resultCount' => $meta['resultCount'],
                            ];
                        } else {
                            $pc = $meta['endPc'] + 1; // Step past 0x0B (end)
                        }
                    }
                    break;

                case 0x05: // else
                    // When reaching else from normal then execution, jump past matching end
                    $frame = end($controlStack);
                    $pc = $frame['endPc'] + 1;
                    break;

                case 0x0B: // end
                    $frame = array_pop($controlStack);
                    if ($frame['type'] === 'func') {
                        // Function completed
                        return $this->extractResults($stack, $resultTypes);
                    }
                    break;

                case 0x0C: // br
                    $labelIdx = $this->readVarUint32($bytecode, $pc);
                    $targetFrameIndex = count($controlStack) - 1 - $labelIdx;
                    if ($targetFrameIndex < 0) {
                        throw new WasmTrapException("Invalid branch label index: {$labelIdx}", 'invalid_label');
                    }
                    $targetFrame = $controlStack[$targetFrameIndex];

                    // Unwind control stack down to targetFrame
                    for ($i = count($controlStack) - 1; $i > $targetFrameIndex; $i--) {
                        array_pop($controlStack);
                    }

                    if ($targetFrame['type'] === 'loop') {
                        // Jump back to start of loop
                        $pc = $targetFrame['startPc'];
                    } else {
                        // Jump to end of block/if/func
                        array_pop($controlStack);
                        $pc = $targetFrame['endPc'] + 1;
                        if ($targetFrame['type'] === 'func') {
                            return $this->extractResults($stack, $resultTypes);
                        }
                    }
                    break;

                case 0x0D: // br_if
                    $labelIdx = $this->readVarUint32($bytecode, $pc);
                    $cond = array_pop($stack);
                    if ($cond !== 0) {
                        $targetFrameIndex = count($controlStack) - 1 - $labelIdx;
                        if ($targetFrameIndex < 0) {
                            throw new WasmTrapException("Invalid branch label index: {$labelIdx}", 'invalid_label');
                        }
                        $targetFrame = $controlStack[$targetFrameIndex];

                        for ($i = count($controlStack) - 1; $i > $targetFrameIndex; $i--) {
                            array_pop($controlStack);
                        }

                        if ($targetFrame['type'] === 'loop') {
                            $pc = $targetFrame['startPc'];
                        } else {
                            array_pop($controlStack);
                            $pc = $targetFrame['endPc'] + 1;
                            if ($targetFrame['type'] === 'func') {
                                return $this->extractResults($stack, $resultTypes);
                            }
                        }
                    }
                    break;

                case 0x0E: // br_table
                    $tableCount = $this->readVarUint32($bytecode, $pc);
                    $targets = [];
                    for ($t = 0; $t < $tableCount; $t++) {
                        $targets[] = $this->readVarUint32($bytecode, $pc);
                    }
                    $defaultTarget = $this->readVarUint32($bytecode, $pc);

                    $idx = array_pop($stack);
                    $chosenLabel = ($idx >= 0 && $idx < $tableCount) ? $targets[$idx] : $defaultTarget;

                    $targetFrameIndex = count($controlStack) - 1 - $chosenLabel;
                    if ($targetFrameIndex < 0) {
                        throw new WasmTrapException("Invalid br_table label index: {$chosenLabel}", 'invalid_label');
                    }
                    $targetFrame = $controlStack[$targetFrameIndex];

                    for ($i = count($controlStack) - 1; $i > $targetFrameIndex; $i--) {
                        array_pop($controlStack);
                    }

                    if ($targetFrame['type'] === 'loop') {
                        $pc = $targetFrame['startPc'];
                    } else {
                        array_pop($controlStack);
                        $pc = $targetFrame['endPc'] + 1;
                        if ($targetFrame['type'] === 'func') {
                            return $this->extractResults($stack, $resultTypes);
                        }
                    }
                    break;

                case 0x0F: // return
                    return $this->extractResults($stack, $resultTypes);

                case 0x10: // call
                    $calleeIdx = $this->readVarUint32($bytecode, $pc);
                    $module = $this->instance->getModule();
                    $calleeType = $module->getFunctionType($calleeIdx);
                    if ($calleeType === null) {
                        throw new WasmTrapException("Function type not found for index {$calleeIdx}", 'invalid_call');
                    }

                    $paramCount = count($calleeType['params']);
                    $callArgs = [];
                    for ($p = 0; $p < $paramCount; $p++) {
                        $callArgs[] = array_pop($stack);
                    }
                    $callArgs = array_reverse($callArgs);

                    $callResult = $this->invoke($calleeIdx, $callArgs);
                    if ($callResult !== null) {
                        if (is_array($callResult)) {
                            foreach ($callResult as $resVal) {
                                $stack[] = $resVal;
                            }
                        } else {
                            $stack[] = $callResult;
                        }
                    }
                    break;

                case 0x11: // call_indirect
                    $typeIdx = $this->readVarUint32($bytecode, $pc);
                    $tableIdx = $this->readByte($bytecode, $pc); // Table index (0x00 in MVP)

                    $elemIdx = array_pop($stack);
                    $table = $this->instance->getTable($tableIdx);
                    $targetFuncIdx = $table->get($elemIdx);
                    if ($targetFuncIdx === null) {
                        throw new WasmTrapException("Uninitialized table element at index {$elemIdx}", 'uninitialized_element');
                    }

                    $module = $this->instance->getModule();
                    $expectedType = $module->types[$typeIdx] ?? null;
                    $actualType = $module->getFunctionType($targetFuncIdx);

                    if ($expectedType === null || $actualType === null || $expectedType !== $actualType) {
                        throw new WasmTrapException("Indirect call signature mismatch for table index {$elemIdx}", 'indirect_call_type_mismatch');
                    }

                    $paramCount = count($actualType['params']);
                    $callArgs = [];
                    for ($p = 0; $p < $paramCount; $p++) {
                        $callArgs[] = array_pop($stack);
                    }
                    $callArgs = array_reverse($callArgs);

                    $callResult = $this->invoke($targetFuncIdx, $callArgs);
                    if ($callResult !== null) {
                        if (is_array($callResult)) {
                            foreach ($callResult as $resVal) {
                                $stack[] = $resVal;
                            }
                        } else {
                            $stack[] = $callResult;
                        }
                    }
                    break;

                // --- Parametric Instructions ---
                case 0x1A: // drop
                    array_pop($stack);
                    break;

                case 0x1B: // select
                    $c = array_pop($stack);
                    $val2 = array_pop($stack);
                    $val1 = array_pop($stack);
                    $stack[] = ($c !== 0) ? $val1 : $val2;
                    break;

                // --- Variable Access ---
                case 0x20: // local.get
                    $lIdx = $this->readVarUint32($bytecode, $pc);
                    $stack[] = $locals[$lIdx] ?? 0;
                    break;

                case 0x21: // local.set
                    $lIdx = $this->readVarUint32($bytecode, $pc);
                    $locals[$lIdx] = array_pop($stack);
                    break;

                case 0x22: // local.tee
                    $lIdx = $this->readVarUint32($bytecode, $pc);
                    $val = end($stack);
                    $locals[$lIdx] = $val;
                    break;

                case 0x23: // global.get
                    $gIdx = $this->readVarUint32($bytecode, $pc);
                    $global = $this->instance->getGlobal($gIdx);
                    $stack[] = $global->getValue();
                    break;

                case 0x24: // global.set
                    $gIdx = $this->readVarUint32($bytecode, $pc);
                    $global = $this->instance->getGlobal($gIdx);
                    $global->setValue(array_pop($stack));
                    break;

                // --- Memory Instructions ---
                case 0x28: // i32.load
                    $align = $this->readVarUint32($bytecode, $pc);
                    $offset = $this->readVarUint32($bytecode, $pc);
                    $base = array_pop($stack);
                    $stack[] = $this->instance->getMemory()->loadI32($base + $offset);
                    break;

                case 0x29: // i64.load
                    $align = $this->readVarUint32($bytecode, $pc);
                    $offset = $this->readVarUint32($bytecode, $pc);
                    $base = array_pop($stack);
                    $stack[] = $this->instance->getMemory()->loadI64($base + $offset);
                    break;

                case 0x2A: // f32.load
                    $align = $this->readVarUint32($bytecode, $pc);
                    $offset = $this->readVarUint32($bytecode, $pc);
                    $base = array_pop($stack);
                    $stack[] = $this->instance->getMemory()->loadF32($base + $offset);
                    break;

                case 0x2B: // f64.load
                    $align = $this->readVarUint32($bytecode, $pc);
                    $offset = $this->readVarUint32($bytecode, $pc);
                    $base = array_pop($stack);
                    $stack[] = $this->instance->getMemory()->loadF64($base + $offset);
                    break;

                case 0x2C: // i32.load8_s
                    $align = $this->readVarUint32($bytecode, $pc);
                    $offset = $this->readVarUint32($bytecode, $pc);
                    $base = array_pop($stack);
                    $stack[] = $this->instance->getMemory()->loadI32_8s($base + $offset);
                    break;

                case 0x2D: // i32.load8_u
                    $align = $this->readVarUint32($bytecode, $pc);
                    $offset = $this->readVarUint32($bytecode, $pc);
                    $base = array_pop($stack);
                    $stack[] = $this->instance->getMemory()->loadI32_8u($base + $offset);
                    break;

                case 0x2E: // i32.load16_s
                    $align = $this->readVarUint32($bytecode, $pc);
                    $offset = $this->readVarUint32($bytecode, $pc);
                    $base = array_pop($stack);
                    $stack[] = $this->instance->getMemory()->loadI32_16s($base + $offset);
                    break;

                case 0x2F: // i32.load16_u
                    $align = $this->readVarUint32($bytecode, $pc);
                    $offset = $this->readVarUint32($bytecode, $pc);
                    $base = array_pop($stack);
                    $stack[] = $this->instance->getMemory()->loadI32_16u($base + $offset);
                    break;

                case 0x30: // i64.load8_s
                    $align = $this->readVarUint32($bytecode, $pc);
                    $offset = $this->readVarUint32($bytecode, $pc);
                    $base = array_pop($stack);
                    $stack[] = $this->instance->getMemory()->loadI64_8s($base + $offset);
                    break;

                case 0x31: // i64.load8_u
                    $align = $this->readVarUint32($bytecode, $pc);
                    $offset = $this->readVarUint32($bytecode, $pc);
                    $base = array_pop($stack);
                    $stack[] = $this->instance->getMemory()->loadI64_8u($base + $offset);
                    break;

                case 0x32: // i64.load16_s
                    $align = $this->readVarUint32($bytecode, $pc);
                    $offset = $this->readVarUint32($bytecode, $pc);
                    $base = array_pop($stack);
                    $stack[] = $this->instance->getMemory()->loadI64_16s($base + $offset);
                    break;

                case 0x33: // i64.load16_u
                    $align = $this->readVarUint32($bytecode, $pc);
                    $offset = $this->readVarUint32($bytecode, $pc);
                    $base = array_pop($stack);
                    $stack[] = $this->instance->getMemory()->loadI64_16u($base + $offset);
                    break;

                case 0x34: // i64.load32_s
                    $align = $this->readVarUint32($bytecode, $pc);
                    $offset = $this->readVarUint32($bytecode, $pc);
                    $base = array_pop($stack);
                    $stack[] = $this->instance->getMemory()->loadI64_32s($base + $offset);
                    break;

                case 0x35: // i64.load32_u
                    $align = $this->readVarUint32($bytecode, $pc);
                    $offset = $this->readVarUint32($bytecode, $pc);
                    $base = array_pop($stack);
                    $stack[] = $this->instance->getMemory()->loadI64_32u($base + $offset);
                    break;

                case 0x36: // i32.store
                    $align = $this->readVarUint32($bytecode, $pc);
                    $offset = $this->readVarUint32($bytecode, $pc);
                    $val = array_pop($stack);
                    $base = array_pop($stack);
                    $this->instance->getMemory()->storeI32($base + $offset, $val);
                    break;

                case 0x37: // i64.store
                    $align = $this->readVarUint32($bytecode, $pc);
                    $offset = $this->readVarUint32($bytecode, $pc);
                    $val = array_pop($stack);
                    $base = array_pop($stack);
                    $this->instance->getMemory()->storeI64($base + $offset, $val);
                    break;

                case 0x38: // f32.store
                    $align = $this->readVarUint32($bytecode, $pc);
                    $offset = $this->readVarUint32($bytecode, $pc);
                    $val = (float) array_pop($stack);
                    $base = array_pop($stack);
                    $this->instance->getMemory()->storeF32($base + $offset, $val);
                    break;

                case 0x39: // f64.store
                    $align = $this->readVarUint32($bytecode, $pc);
                    $offset = $this->readVarUint32($bytecode, $pc);
                    $val = (float) array_pop($stack);
                    $base = array_pop($stack);
                    $this->instance->getMemory()->storeF64($base + $offset, $val);
                    break;

                case 0x3A: // i32.store8
                    $align = $this->readVarUint32($bytecode, $pc);
                    $offset = $this->readVarUint32($bytecode, $pc);
                    $val = array_pop($stack);
                    $base = array_pop($stack);
                    $this->instance->getMemory()->storeI32_8($base + $offset, $val);
                    break;

                case 0x3B: // i32.store16
                    $align = $this->readVarUint32($bytecode, $pc);
                    $offset = $this->readVarUint32($bytecode, $pc);
                    $val = array_pop($stack);
                    $base = array_pop($stack);
                    $this->instance->getMemory()->storeI32_16($base + $offset, $val);
                    break;

                case 0x3C: // i64.store8
                    $align = $this->readVarUint32($bytecode, $pc);
                    $offset = $this->readVarUint32($bytecode, $pc);
                    $val = array_pop($stack);
                    $base = array_pop($stack);
                    $this->instance->getMemory()->storeI64_8($base + $offset, $val);
                    break;

                case 0x3D: // i64.store16
                    $align = $this->readVarUint32($bytecode, $pc);
                    $offset = $this->readVarUint32($bytecode, $pc);
                    $val = array_pop($stack);
                    $base = array_pop($stack);
                    $this->instance->getMemory()->storeI64_16($base + $offset, $val);
                    break;

                case 0x3E: // i64.store32
                    $align = $this->readVarUint32($bytecode, $pc);
                    $offset = $this->readVarUint32($bytecode, $pc);
                    $val = array_pop($stack);
                    $base = array_pop($stack);
                    $this->instance->getMemory()->storeI64_32($base + $offset, $val);
                    break;

                case 0x3F: // memory.size
                    $memZero = $this->readByte($bytecode, $pc);
                    $stack[] = $this->instance->getMemory()->size();
                    break;

                case 0x40: // memory.grow
                    $memZero = $this->readByte($bytecode, $pc);
                    $delta = array_pop($stack);
                    $stack[] = $this->instance->getMemory()->grow($delta);
                    break;

                // --- Constants ---
                case 0x41: // i32.const
                    $val = $this->readVarInt32($bytecode, $pc);
                    $stack[] = $this->normalizeI32($val);
                    break;

                case 0x42: // i64.const
                    $val = $this->readVarInt64($bytecode, $pc);
                    $stack[] = (int) $val;
                    break;

                case 0x43: // f32.const
                    $bytes = substr($bytecode, $pc, 4);
                    $pc += 4;
                    $stack[] = (float) unpack('g', $bytes)[1];
                    break;

                case 0x44: // f64.const
                    $bytes = substr($bytecode, $pc, 8);
                    $pc += 8;
                    $stack[] = (float) unpack('e', $bytes)[1];
                    break;

                // --- i32 Comparisons ---
                case 0x45: // i32.eqz
                    $v = array_pop($stack);
                    $stack[] = ($v === 0) ? 1 : 0;
                    break;

                case 0x46: // i32.eq
                    $b = array_pop($stack);
                    $a = array_pop($stack);
                    $stack[] = ($a === $b) ? 1 : 0;
                    break;

                case 0x47: // i32.ne
                    $b = array_pop($stack);
                    $a = array_pop($stack);
                    $stack[] = ($a !== $b) ? 1 : 0;
                    break;

                case 0x48: // i32.lt_s
                    $b = array_pop($stack);
                    $a = array_pop($stack);
                    $stack[] = ($a < $b) ? 1 : 0;
                    break;

                case 0x49: // i32.lt_u
                    $b = array_pop($stack);
                    $a = array_pop($stack);
                    $stack[] = (($a & 0xFFFFFFFF) < ($b & 0xFFFFFFFF)) ? 1 : 0;
                    break;

                case 0x4A: // i32.gt_s
                    $b = array_pop($stack);
                    $a = array_pop($stack);
                    $stack[] = ($a > $b) ? 1 : 0;
                    break;

                case 0x4B: // i32.gt_u
                    $b = array_pop($stack);
                    $a = array_pop($stack);
                    $stack[] = (($a & 0xFFFFFFFF) > ($b & 0xFFFFFFFF)) ? 1 : 0;
                    break;

                case 0x4C: // i32.le_s
                    $b = array_pop($stack);
                    $a = array_pop($stack);
                    $stack[] = ($a <= $b) ? 1 : 0;
                    break;

                case 0x4D: // i32.le_u
                    $b = array_pop($stack);
                    $a = array_pop($stack);
                    $stack[] = (($a & 0xFFFFFFFF) <= ($b & 0xFFFFFFFF)) ? 1 : 0;
                    break;

                case 0x4E: // i32.ge_s
                    $b = array_pop($stack);
                    $a = array_pop($stack);
                    $stack[] = ($a >= $b) ? 1 : 0;
                    break;

                case 0x4F: // i32.ge_u
                    $b = array_pop($stack);
                    $a = array_pop($stack);
                    $stack[] = (($a & 0xFFFFFFFF) >= ($b & 0xFFFFFFFF)) ? 1 : 0;
                    break;

                // --- i64 Comparisons ---
                case 0x50: // i64.eqz
                    $v = array_pop($stack);
                    $stack[] = ($v === 0) ? 1 : 0;
                    break;

                case 0x51: // i64.eq
                    $b = array_pop($stack);
                    $a = array_pop($stack);
                    $stack[] = ($a === $b) ? 1 : 0;
                    break;

                case 0x52: // i64.ne
                    $b = array_pop($stack);
                    $a = array_pop($stack);
                    $stack[] = ($a !== $b) ? 1 : 0;
                    break;

                case 0x53: // i64.lt_s
                    $b = array_pop($stack);
                    $a = array_pop($stack);
                    $stack[] = ($a < $b) ? 1 : 0;
                    break;

                case 0x54: // i64.lt_u
                    $b = array_pop($stack);
                    $a = array_pop($stack);
                    $stack[] = $this->compareU64($a, $b) < 0 ? 1 : 0;
                    break;

                case 0x55: // i64.gt_s
                    $b = array_pop($stack);
                    $a = array_pop($stack);
                    $stack[] = ($a > $b) ? 1 : 0;
                    break;

                case 0x56: // i64.gt_u
                    $b = array_pop($stack);
                    $a = array_pop($stack);
                    $stack[] = $this->compareU64($a, $b) > 0 ? 1 : 0;
                    break;

                case 0x57: // i64.le_s
                    $b = array_pop($stack);
                    $a = array_pop($stack);
                    $stack[] = ($a <= $b) ? 1 : 0;
                    break;

                case 0x58: // i64.le_u
                    $b = array_pop($stack);
                    $a = array_pop($stack);
                    $stack[] = $this->compareU64($a, $b) <= 0 ? 1 : 0;
                    break;

                case 0x59: // i64.ge_s
                    $b = array_pop($stack);
                    $a = array_pop($stack);
                    $stack[] = ($a >= $b) ? 1 : 0;
                    break;

                case 0x5A: // i64.ge_u
                    $b = array_pop($stack);
                    $a = array_pop($stack);
                    $stack[] = $this->compareU64($a, $b) >= 0 ? 1 : 0;
                    break;

                // --- f32 Comparisons ---
                case 0x5B: // f32.eq
                    $b = (float) array_pop($stack);
                    $a = (float) array_pop($stack);
                    $stack[] = ($a == $b && !is_nan($a) && !is_nan($b)) ? 1 : 0;
                    break;

                case 0x5C: // f32.ne
                    $b = (float) array_pop($stack);
                    $a = (float) array_pop($stack);
                    $stack[] = ($a != $b || is_nan($a) || is_nan($b)) ? 1 : 0;
                    break;

                case 0x5D: // f32.lt
                    $b = (float) array_pop($stack);
                    $a = (float) array_pop($stack);
                    $stack[] = (!is_nan($a) && !is_nan($b) && $a < $b) ? 1 : 0;
                    break;

                case 0x5E: // f32.gt
                    $b = (float) array_pop($stack);
                    $a = (float) array_pop($stack);
                    $stack[] = (!is_nan($a) && !is_nan($b) && $a > $b) ? 1 : 0;
                    break;

                case 0x5F: // f32.le
                    $b = (float) array_pop($stack);
                    $a = (float) array_pop($stack);
                    $stack[] = (!is_nan($a) && !is_nan($b) && $a <= $b) ? 1 : 0;
                    break;

                case 0x60: // f32.ge
                    $b = (float) array_pop($stack);
                    $a = (float) array_pop($stack);
                    $stack[] = (!is_nan($a) && !is_nan($b) && $a >= $b) ? 1 : 0;
                    break;

                // --- f64 Comparisons ---
                case 0x61: // f64.eq
                    $b = (float) array_pop($stack);
                    $a = (float) array_pop($stack);
                    $stack[] = ($a == $b && !is_nan($a) && !is_nan($b)) ? 1 : 0;
                    break;

                case 0x62: // f64.ne
                    $b = (float) array_pop($stack);
                    $a = (float) array_pop($stack);
                    $stack[] = ($a != $b || is_nan($a) || is_nan($b)) ? 1 : 0;
                    break;

                case 0x63: // f64.lt
                    $b = (float) array_pop($stack);
                    $a = (float) array_pop($stack);
                    $stack[] = (!is_nan($a) && !is_nan($b) && $a < $b) ? 1 : 0;
                    break;

                case 0x64: // f64.gt
                    $b = (float) array_pop($stack);
                    $a = (float) array_pop($stack);
                    $stack[] = (!is_nan($a) && !is_nan($b) && $a > $b) ? 1 : 0;
                    break;

                case 0x65: // f64.le
                    $b = (float) array_pop($stack);
                    $a = (float) array_pop($stack);
                    $stack[] = (!is_nan($a) && !is_nan($b) && $a <= $b) ? 1 : 0;
                    break;

                case 0x66: // f64.ge
                    $b = (float) array_pop($stack);
                    $a = (float) array_pop($stack);
                    $stack[] = (!is_nan($a) && !is_nan($b) && $a >= $b) ? 1 : 0;
                    break;

                // --- i32 Arithmetic & Bitwise ---
                case 0x67: // i32.clz
                    $a = array_pop($stack) & 0xFFFFFFFF;
                    $stack[] = $a === 0 ? 32 : (32 - strlen(decbin($a)));
                    break;

                case 0x68: // i32.ctz
                    $a = array_pop($stack) & 0xFFFFFFFF;
                    if ($a === 0) {
                        $stack[] = 32;
                    } else {
                        $bin = strrev(sprintf('%032b', $a));
                        $stack[] = strspn($bin, '0');
                    }
                    break;

                case 0x69: // i32.popcnt
                    $a = array_pop($stack) & 0xFFFFFFFF;
                    $stack[] = substr_count(decbin($a), '1');
                    break;

                case 0x6A: // i32.add
                    $b = array_pop($stack);
                    $a = array_pop($stack);
                    $stack[] = $this->normalizeI32($a + $b);
                    break;

                case 0x6B: // i32.sub
                    $b = array_pop($stack);
                    $a = array_pop($stack);
                    $stack[] = $this->normalizeI32($a - $b);
                    break;

                case 0x6C: // i32.mul
                    $b = array_pop($stack);
                    $a = array_pop($stack);
                    $stack[] = $this->normalizeI32(($a * $b) & 0xFFFFFFFF);
                    break;

                case 0x6D: // i32.div_s
                    $b = array_pop($stack);
                    $a = array_pop($stack);
                    if ($b === 0) {
                        throw new WasmTrapException('integer divide by zero', 'integer_divide_by_zero');
                    }
                    if ($a === -2147483648 && $b === -1) {
                        throw new WasmTrapException('integer overflow', 'integer_overflow');
                    }
                    $stack[] = intdiv($a, $b);
                    break;

                case 0x6E: // i32.div_u
                    $b = array_pop($stack) & 0xFFFFFFFF;
                    $a = array_pop($stack) & 0xFFFFFFFF;
                    if ($b === 0) {
                        throw new WasmTrapException('integer divide by zero', 'integer_divide_by_zero');
                    }
                    $stack[] = $this->normalizeI32(intdiv($a, $b));
                    break;

                case 0x6F: // i32.rem_s
                    $b = array_pop($stack);
                    $a = array_pop($stack);
                    if ($b === 0) {
                        throw new WasmTrapException('integer divide by zero', 'integer_divide_by_zero');
                    }
                    if ($b === -1) {
                        $stack[] = 0;
                    } else {
                        $stack[] = $a % $b;
                    }
                    break;

                case 0x70: // i32.rem_u
                    $b = array_pop($stack) & 0xFFFFFFFF;
                    $a = array_pop($stack) & 0xFFFFFFFF;
                    if ($b === 0) {
                        throw new WasmTrapException('integer divide by zero', 'integer_divide_by_zero');
                    }
                    $stack[] = $this->normalizeI32($a % $b);
                    break;

                case 0x71: // i32.and
                    $b = array_pop($stack);
                    $a = array_pop($stack);
                    $stack[] = $this->normalizeI32($a & $b);
                    break;

                case 0x72: // i32.or
                    $b = array_pop($stack);
                    $a = array_pop($stack);
                    $stack[] = $this->normalizeI32($a | $b);
                    break;

                case 0x73: // i32.xor
                    $b = array_pop($stack);
                    $a = array_pop($stack);
                    $stack[] = $this->normalizeI32($a ^ $b);
                    break;

                case 0x74: // i32.shl
                    $b = array_pop($stack) % 32;
                    $a = array_pop($stack);
                    $stack[] = $this->normalizeI32($a << $b);
                    break;

                case 0x75: // i32.shr_s
                    $b = array_pop($stack) % 32;
                    $a = array_pop($stack);
                    $stack[] = $a >> $b;
                    break;

                case 0x76: // i32.shr_u
                    $b = array_pop($stack) % 32;
                    $a = array_pop($stack) & 0xFFFFFFFF;
                    $stack[] = $this->normalizeI32($a >> $b);
                    break;

                case 0x77: // i32.rotl
                    $shift = (array_pop($stack) % 32 + 32) % 32;
                    $val = array_pop($stack) & 0xFFFFFFFF;
                    $res = $shift === 0 ? $val : ((($val << $shift) | ($val >> (32 - $shift))) & 0xFFFFFFFF);
                    $stack[] = $this->normalizeI32($res);
                    break;

                case 0x78: // i32.rotr
                    $shift = (array_pop($stack) % 32 + 32) % 32;
                    $val = array_pop($stack) & 0xFFFFFFFF;
                    $res = $shift === 0 ? $val : ((($val >> $shift) | ($val << (32 - $shift))) & 0xFFFFFFFF);
                    $stack[] = $this->normalizeI32($res);
                    break;

                // --- i64 Arithmetic & Bitwise ---
                case 0x79: // i64.clz
                    $a = (int) array_pop($stack);
                    if ($a === 0) {
                        $stack[] = 64;
                    } else {
                        $bin = sprintf('%064b', $a);
                        $stack[] = strspn($bin, '0');
                    }
                    break;

                case 0x7A: // i64.ctz
                    $a = (int) array_pop($stack);
                    if ($a === 0) {
                        $stack[] = 64;
                    } else {
                        $bin = strrev(sprintf('%064b', $a));
                        $stack[] = strspn($bin, '0');
                    }
                    break;

                case 0x7B: // i64.popcnt
                    $a = (int) array_pop($stack);
                    $stack[] = substr_count(sprintf('%064b', $a), '1');
                    break;

                case 0x7C: // i64.add
                    $b = (int) array_pop($stack);
                    $a = (int) array_pop($stack);
                    $stack[] = (int) ($a + $b);
                    break;

                case 0x7D: // i64.sub
                    $b = (int) array_pop($stack);
                    $a = (int) array_pop($stack);
                    $stack[] = (int) ($a - $b);
                    break;

                case 0x7E: // i64.mul
                    $b = (int) array_pop($stack);
                    $a = (int) array_pop($stack);
                    $stack[] = (int) ($a * $b);
                    break;

                case 0x7F: // i64.div_s
                    $b = (int) array_pop($stack);
                    $a = (int) array_pop($stack);
                    if ($b === 0) {
                        throw new WasmTrapException('integer divide by zero', 'integer_divide_by_zero');
                    }
                    if ($a === PHP_INT_MIN && $b === -1) {
                        throw new WasmTrapException('integer overflow', 'integer_overflow');
                    }
                    $stack[] = intdiv($a, $b);
                    break;

                case 0x80: // i64.div_u
                    $b = (int) array_pop($stack);
                    $a = (int) array_pop($stack);
                    if ($b === 0) {
                        throw new WasmTrapException('integer divide by zero', 'integer_divide_by_zero');
                    }
                    $stack[] = $this->divU64($a, $b);
                    break;

                case 0x81: // i64.rem_s
                    $b = (int) array_pop($stack);
                    $a = (int) array_pop($stack);
                    if ($b === 0) {
                        throw new WasmTrapException('integer divide by zero', 'integer_divide_by_zero');
                    }
                    if ($b === -1) {
                        $stack[] = 0;
                    } else {
                        $stack[] = $a % $b;
                    }
                    break;

                case 0x82: // i64.rem_u
                    $b = (int) array_pop($stack);
                    $a = (int) array_pop($stack);
                    if ($b === 0) {
                        throw new WasmTrapException('integer divide by zero', 'integer_divide_by_zero');
                    }
                    $stack[] = $this->remU64($a, $b);
                    break;

                case 0x83: // i64.and
                    $b = (int) array_pop($stack);
                    $a = (int) array_pop($stack);
                    $stack[] = $a & $b;
                    break;

                case 0x84: // i64.or
                    $b = (int) array_pop($stack);
                    $a = (int) array_pop($stack);
                    $stack[] = $a | $b;
                    break;

                case 0x85: // i64.xor
                    $b = (int) array_pop($stack);
                    $a = (int) array_pop($stack);
                    $stack[] = $a ^ $b;
                    break;

                case 0x86: // i64.shl
                    $b = (int) (array_pop($stack) % 64);
                    $a = (int) array_pop($stack);
                    $stack[] = $a << $b;
                    break;

                case 0x87: // i64.shr_s
                    $b = (int) (array_pop($stack) % 64);
                    $a = (int) array_pop($stack);
                    $stack[] = $a >> $b;
                    break;

                case 0x88: // i64.shr_u
                    $shift = (int) (array_pop($stack) % 64);
                    $a = (int) array_pop($stack);
                    if ($shift === 0) {
                        $stack[] = $a;
                    } else {
                        $res = ($a >> $shift) & ((1 << (64 - $shift)) - 1);
                        $stack[] = $res;
                    }
                    break;

                case 0x89: // i64.rotl
                    $shift = (int) ((array_pop($stack) % 64 + 64) % 64);
                    $val = (int) array_pop($stack);
                    if ($shift === 0) {
                        $stack[] = $val;
                    } else {
                        $left = $val << $shift;
                        $right = ($val >> (64 - $shift)) & ((1 << $shift) - 1);
                        $stack[] = $left | $right;
                    }
                    break;

                case 0x8A: // i64.rotr
                    $shift = (int) ((array_pop($stack) % 64 + 64) % 64);
                    $val = (int) array_pop($stack);
                    if ($shift === 0) {
                        $stack[] = $val;
                    } else {
                        $right = ($val >> $shift) & ((1 << (64 - $shift)) - 1);
                        $left = $val << (64 - $shift);
                        $stack[] = $left | $right;
                    }
                    break;

                // --- f32 Arithmetic ---
                case 0x8B: // f32.abs
                    $stack[] = abs((float) array_pop($stack));
                    break;

                case 0x8C: // f32.neg
                    $stack[] = -(float) array_pop($stack);
                    break;

                case 0x8D: // f32.ceil
                    $stack[] = ceil((float) array_pop($stack));
                    break;

                case 0x8E: // f32.floor
                    $stack[] = floor((float) array_pop($stack));
                    break;

                case 0x8F: // f32.trunc
                    $val = (float) array_pop($stack);
                    $stack[] = $val >= 0 ? floor($val) : ceil($val);
                    break;

                case 0x90: // f32.nearest
                    $stack[] = round((float) array_pop($stack), 0, PHP_ROUND_HALF_EVEN);
                    break;

                case 0x91: // f32.sqrt
                    $stack[] = sqrt((float) array_pop($stack));
                    break;

                case 0x92: // f32.add
                    $b = (float) array_pop($stack);
                    $a = (float) array_pop($stack);
                    $stack[] = $a + $b;
                    break;

                case 0x93: // f32.sub
                    $b = (float) array_pop($stack);
                    $a = (float) array_pop($stack);
                    $stack[] = $a - $b;
                    break;

                case 0x94: // f32.mul
                    $b = (float) array_pop($stack);
                    $a = (float) array_pop($stack);
                    $stack[] = $a * $b;
                    break;

                case 0x95: // f32.div
                    $b = (float) array_pop($stack);
                    $a = (float) array_pop($stack);
                    $stack[] = $b == 0 ? ($a >= 0 ? INF : -INF) : ($a / $b);
                    break;

                case 0x96: // f32.min
                    $b = (float) array_pop($stack);
                    $a = (float) array_pop($stack);
                    $stack[] = min($a, $b);
                    break;

                case 0x97: // f32.max
                    $b = (float) array_pop($stack);
                    $a = (float) array_pop($stack);
                    $stack[] = max($a, $b);
                    break;

                case 0x98: // f32.copysign
                    $b = (float) array_pop($stack);
                    $a = (float) array_pop($stack);
                    $signB = (1.0 / $b) < 0 || $b < 0;
                    $absA = abs($a);
                    $stack[] = $signB ? -$absA : $absA;
                    break;

                // --- f64 Arithmetic ---
                case 0x99: // f64.abs
                    $stack[] = abs((float) array_pop($stack));
                    break;

                case 0x9A: // f64.neg
                    $stack[] = -(float) array_pop($stack);
                    break;

                case 0x9B: // f64.ceil
                    $stack[] = ceil((float) array_pop($stack));
                    break;

                case 0x9C: // f64.floor
                    $stack[] = floor((float) array_pop($stack));
                    break;

                case 0x9D: // f64.trunc
                    $val = (float) array_pop($stack);
                    $stack[] = $val >= 0 ? floor($val) : ceil($val);
                    break;

                case 0x9E: // f64.nearest
                    $stack[] = round((float) array_pop($stack), 0, PHP_ROUND_HALF_EVEN);
                    break;

                case 0x9F: // f64.sqrt
                    $stack[] = sqrt((float) array_pop($stack));
                    break;

                case 0xA0: // f64.add
                    $b = (float) array_pop($stack);
                    $a = (float) array_pop($stack);
                    $stack[] = $a + $b;
                    break;

                case 0xA1: // f64.sub
                    $b = (float) array_pop($stack);
                    $a = (float) array_pop($stack);
                    $stack[] = $a - $b;
                    break;

                case 0xA2: // f64.mul
                    $b = (float) array_pop($stack);
                    $a = (float) array_pop($stack);
                    $stack[] = $a * $b;
                    break;

                case 0xA3: // f64.div
                    $b = (float) array_pop($stack);
                    $a = (float) array_pop($stack);
                    $stack[] = $b == 0 ? ($a >= 0 ? INF : -INF) : ($a / $b);
                    break;

                case 0xA4: // f64.min
                    $b = (float) array_pop($stack);
                    $a = (float) array_pop($stack);
                    $stack[] = min($a, $b);
                    break;

                case 0xA5: // f64.max
                    $b = (float) array_pop($stack);
                    $a = (float) array_pop($stack);
                    $stack[] = max($a, $b);
                    break;

                case 0xA6: // f64.copysign
                    $b = (float) array_pop($stack);
                    $a = (float) array_pop($stack);
                    $signB = (1.0 / $b) < 0 || $b < 0;
                    $absA = abs($a);
                    $stack[] = $signB ? -$absA : $absA;
                    break;

                // --- Conversions & Reinterpretations ---
                case 0xA7: // i32.wrap_i64
                    $val = (int) array_pop($stack);
                    $stack[] = $this->normalizeI32($val);
                    break;

                case 0xA8: // i32.trunc_f32_s
                case 0xAA: // i32.trunc_f64_s
                    $val = (float) array_pop($stack);
                    if (is_nan($val) || $val <= -2147483649.0 || $val >= 2147483648.0) {
                        throw new WasmTrapException('invalid conversion to integer (out of bounds or NaN)', 'invalid_conversion');
                    }
                    $stack[] = (int) ($val >= 0 ? floor($val) : ceil($val));
                    break;

                case 0xA9: // i32.trunc_f32_u
                case 0xAB: // i32.trunc_f64_u
                    $val = (float) array_pop($stack);
                    if (is_nan($val) || $val <= -1.0 || $val >= 4294967296.0) {
                        throw new WasmTrapException('invalid conversion to integer (out of bounds or NaN)', 'invalid_conversion');
                    }
                    $u = (int) floor($val);
                    $stack[] = $this->normalizeI32($u);
                    break;

                case 0xAC: // i64.extend_i32_s
                    $val = array_pop($stack);
                    $stack[] = (int) $val;
                    break;

                case 0xAD: // i64.extend_i32_u
                    $val = array_pop($stack) & 0xFFFFFFFF;
                    $stack[] = (int) $val;
                    break;

                case 0xAE: // i64.trunc_f32_s
                case 0xB0: // i64.trunc_f64_s
                    $val = (float) array_pop($stack);
                    if (is_nan($val) || $val <= (float) PHP_INT_MIN || $val >= (float) PHP_INT_MAX) {
                        throw new WasmTrapException('invalid conversion to integer (out of bounds or NaN)', 'invalid_conversion');
                    }
                    $stack[] = (int) ($val >= 0 ? floor($val) : ceil($val));
                    break;

                case 0xAF: // i64.trunc_f32_u
                case 0xB1: // i64.trunc_f64_u
                    $val = (float) array_pop($stack);
                    if (is_nan($val) || $val <= -1.0 || $val >= 18446744073709551616.0) {
                        throw new WasmTrapException('invalid conversion to integer (out of bounds or NaN)', 'invalid_conversion');
                    }
                    $stack[] = (int) floor($val);
                    break;

                case 0xB2: // f32.convert_i32_s
                case 0xB7: // f64.convert_i32_s
                case 0xB4: // f32.convert_i64_s
                case 0xB9: // f64.convert_i64_s
                    $val = array_pop($stack);
                    $stack[] = (float) $val;
                    break;

                case 0xB3: // f32.convert_i32_u
                case 0xB8: // f64.convert_i32_u
                    $val = array_pop($stack) & 0xFFFFFFFF;
                    $stack[] = (float) $val;
                    break;

                case 0xB5: // f32.convert_i64_u
                case 0xBA: // f64.convert_i64_u
                    $val = (int) array_pop($stack);
                    if ($val >= 0) {
                        $stack[] = (float) $val;
                    } else {
                        // Unsigned 64-bit float conversion
                        $stack[] = (float) ($val & 0x7FFFFFFFFFFFFFFF) + 9223372036854775808.0;
                    }
                    break;

                case 0xB6: // f32.demote_f64
                case 0xBB: // f64.promote_f32
                    $val = (float) array_pop($stack);
                    $stack[] = $val;
                    break;

                case 0xBC: // i32.reinterpret_f32
                    $val = (float) array_pop($stack);
                    $packed = pack('g', $val);
                    $unpacked = unpack('l', $packed)[1];
                    $stack[] = $unpacked;
                    break;

                case 0xBD: // i64.reinterpret_f64
                    $val = (float) array_pop($stack);
                    $packed = pack('e', $val);
                    $unpacked = unpack('q', $packed)[1];
                    $stack[] = $unpacked;
                    break;

                case 0xBE: // f32.reinterpret_i32
                    $val = array_pop($stack);
                    $packed = pack('l', $val);
                    $unpacked = unpack('g', $packed)[1];
                    $stack[] = (float) $unpacked;
                    break;

                case 0xBF: // f64.reinterpret_i64
                    $val = (int) array_pop($stack);
                    $packed = pack('q', $val);
                    $unpacked = unpack('e', $packed)[1];
                    $stack[] = (float) $unpacked;
                    break;

                default:
                    throw new WasmTrapException(
                        sprintf('Unknown or unsupported Wasm opcode: 0x%02X at byte %d', $opcode, $currentOpPc),
                        'unknown_opcode'
                    );
            }
        }

        return $this->extractResults($stack, $resultTypes);
    }

    /**
     * Extract function results from stack.
     */
    private function extractResults(array &$stack, array $resultTypes): mixed
    {
        $count = count($resultTypes);
        if ($count === 0) {
            return null;
        }
        if ($count === 1) {
            return empty($stack) ? 0 : array_pop($stack);
        }

        $res = [];
        for ($i = 0; $i < $count; $i++) {
            $res[] = array_pop($stack);
        }
        return array_reverse($res);
    }

    /**
     * Compute and cache jump targets for control instructions (block, loop, if, else, end).
     *
     * @return array<int, array{type: string, elsePc: ?int, endPc: int, resultCount: int}>
     */
    private function getJumpMap(int $funcIndex, string $bytecode): array
    {
        if (isset($this->jumpTables[$funcIndex])) {
            return $this->jumpTables[$funcIndex];
        }

        $map = [];
        $stack = [];
        $len = strlen($bytecode);
        $pc = 0;

        while ($pc < $len) {
            $opPc = $pc;
            $opcode = ord($bytecode[$pc++]);

            switch ($opcode) {
                case 0x02: // block
                case 0x03: // loop
                case 0x04: // if
                    $blockType = $this->readBlockType($bytecode, $pc);
                    $resultCount = ($blockType !== WasmModule::TYPE_VOID) ? 1 : 0;
                    $stack[] = [
                        'opcode'      => $opcode,
                        'opPc'        => $opPc,
                        'elsePc'      => null,
                        'resultCount' => $resultCount,
                    ];
                    break;

                case 0x05: // else
                    $topIndex = count($stack) - 1;
                    if ($topIndex >= 0 && $stack[$topIndex]['opcode'] === 0x04) {
                        $stack[$topIndex]['elsePc'] = $opPc;
                    }
                    break;

                case 0x0B: // end
                    if (!empty($stack)) {
                        $entry = array_pop($stack);
                        $map[$entry['opPc']] = [
                            'type'        => match ($entry['opcode']) {
                                0x02 => 'block',
                                0x03 => 'loop',
                                0x04 => 'if',
                                default => 'other',
                            },
                            'elsePc'      => $entry['elsePc'],
                            'endPc'       => $opPc,
                            'resultCount' => $entry['resultCount'],
                        ];
                    }
                    break;

                // Skip instruction immediates during scanning
                case 0x0C: // br
                case 0x0D: // br_if
                case 0x20: // local.get
                case 0x21: // local.set
                case 0x22: // local.tee
                case 0x23: // global.get
                case 0x24: // global.set
                case 0x10: // call
                    $this->readVarUint32($bytecode, $pc);
                    break;

                case 0x0E: // br_table
                    $cnt = $this->readVarUint32($bytecode, $pc);
                    for ($t = 0; $t < $cnt; $t++) {
                        $this->readVarUint32($bytecode, $pc);
                    }
                    $this->readVarUint32($bytecode, $pc);
                    break;

                case 0x11: // call_indirect
                    $this->readVarUint32($bytecode, $pc);
                    $this->readByte($bytecode, $pc);
                    break;

                case 0x28: case 0x29: case 0x2A: case 0x2B:
                case 0x2C: case 0x2D: case 0x2E: case 0x2F:
                case 0x30: case 0x31: case 0x32: case 0x33:
                case 0x34: case 0x35: case 0x36: case 0x37:
                case 0x38: case 0x39: case 0x3A: case 0x3B:
                case 0x3C: case 0x3D: case 0x3E:
                    $this->readVarUint32($bytecode, $pc); // align
                    $this->readVarUint32($bytecode, $pc); // offset
                    break;

                case 0x3F: case 0x40:
                    $this->readByte($bytecode, $pc); // memory index (0x00)
                    break;

                case 0x41: // i32.const
                    $this->readVarInt32($bytecode, $pc);
                    break;

                case 0x42: // i64.const
                    $this->readVarInt64($bytecode, $pc);
                    break;

                case 0x43: // f32.const
                    $pc += 4;
                    break;

                case 0x44: // f64.const
                    $pc += 8;
                    break;

                default:
                    // Single byte opcode without immediates
                    break;
            }
        }

        $this->jumpTables[$funcIndex] = $map;
        return $map;
    }

    // --- Fast In-place Readers for Stack Machine ---

    private function readByte(string $code, int &$pc): int
    {
        return ord($code[$pc++]);
    }

    private function readBlockType(string $code, int &$pc): int
    {
        $byte = ord($code[$pc++]);
        return $byte;
    }

    private function readVarUint32(string $code, int &$pc): int
    {
        $result = 0;
        $shift = 0;
        while (true) {
            $byte = ord($code[$pc++]);
            $result |= ($byte & 0x7F) << $shift;
            $shift += 7;
            if (($byte & 0x80) === 0) {
                break;
            }
        }
        return $result;
    }

    private function readVarInt32(string $code, int &$pc): int
    {
        $result = 0;
        $shift = 0;
        $byte = 0;
        while (true) {
            $byte = ord($code[$pc++]);
            $result |= ($byte & 0x7F) << $shift;
            $shift += 7;
            if (($byte & 0x80) === 0) {
                break;
            }
        }
        if ($shift < 32 && ($byte & 0x40) !== 0) {
            $result |= -1 << $shift;
        }
        if ($result & 0x80000000) {
            $result = $result - 0x100000000;
        }
        return $result;
    }

    private function readVarInt64(string $code, int &$pc): int
    {
        $result = 0;
        $shift = 0;
        $byte = 0;
        while (true) {
            $byte = ord($code[$pc++]);
            $result |= ($byte & 0x7F) << $shift;
            $shift += 7;
            if (($byte & 0x80) === 0) {
                break;
            }
        }
        if ($shift < 64 && ($byte & 0x40) !== 0) {
            $result |= -1 << $shift;
        }
        return $result;
    }

    private function normalizeI32(int|float $val): int
    {
        $intVal = (int) $val;
        if ($intVal & 0x80000000) {
            return ($intVal & 0xFFFFFFFF) - 0x100000000;
        }
        return $intVal & 0xFFFFFFFF;
    }

    private function normalizeValue(mixed $val, int $type): int|float
    {
        return match ($type) {
            WasmModule::TYPE_I32 => $this->normalizeI32($val),
            WasmModule::TYPE_I64 => (int) $val,
            WasmModule::TYPE_F32, WasmModule::TYPE_F64 => (float) $val,
            default => $val,
        };
    }

    private function compareU64(int $a, int $b): int
    {
        if ($a === $b) {
            return 0;
        }
        $aNeg = $a < 0;
        $bNeg = $b < 0;
        if ($aNeg !== $bNeg) {
            return $aNeg ? 1 : -1;
        }
        return $a < $b ? -1 : 1;
    }

    private function divU64(int $a, int $b): int
    {
        if ($b === 0) {
            throw new WasmTrapException('integer divide by zero', 'integer_divide_by_zero');
        }
        if ($a >= 0 && $b > 0) {
            return intdiv($a, $b);
        }
        // Big integer arithmetic via bcmath or manual binary division
        $uA = sprintf('%u', $a);
        $uB = sprintf('%u', $b);
        if (function_exists('bcdiv')) {
            $resStr = bcdiv($uA, $uB, 0);
            return (int) $resStr;
        }
        return (int) floor((float) $uA / (float) $uB);
    }

    private function remU64(int $a, int $b): int
    {
        if ($b === 0) {
            throw new WasmTrapException('integer divide by zero', 'integer_divide_by_zero');
        }
        if ($a >= 0 && $b > 0) {
            return $a % $b;
        }
        $uA = sprintf('%u', $a);
        $uB = sprintf('%u', $b);
        if (function_exists('bcmod')) {
            $resStr = bcmod($uA, $uB);
            return (int) $resStr;
        }
        $quot = (int) floor((float) $uA / (float) $uB);
        return (int) ($uA - $quot * $uB);
    }
}
