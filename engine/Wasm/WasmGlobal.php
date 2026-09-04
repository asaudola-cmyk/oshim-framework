<?php
declare(strict_types=1);

namespace Oshim\Wasm;

use Oshim\Wasm\Exceptions\WasmTrapException;

/**
 * WebAssembly 1.0 Global Variable Instance.
 * Stores typed value and enforces mutability constraints.
 */
class WasmGlobal
{
    private int $type;
    private bool $mutable;
    private int|float $value;

    public function __construct(int $type, bool $mutable, int|float $value = 0)
    {
        $this->type = $type;
        $this->mutable = $mutable;
        $this->value = $this->normalizeValue($value, $type);
    }

    /**
     * Get current global value.
     */
    public function getValue(): int|float
    {
        return $this->value;
    }

    /**
     * Set global value (validates mutability and type).
     */
    public function setValue(int|float $value): void
    {
        if (!$this->mutable) {
            throw new WasmTrapException('Cannot modify immutable global variable', 'immutable_global_write');
        }
        $this->value = $this->normalizeValue($value, $this->type);
    }

    /**
     * Internal force set (for module initialization).
     */
    public function setInitialValue(int|float $value): void
    {
        $this->value = $this->normalizeValue($value, $this->type);
    }

    /**
     * Get global value type.
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * Check if global is mutable.
     */
    public function isMutable(): bool
    {
        return $this->mutable;
    }

    /**
     * Normalize and clamp value to type specification.
     */
    private function normalizeValue(int|float $val, int $type): int|float
    {
        return match ($type) {
            WasmModule::TYPE_I32 => ($val & 0x80000000) ? (($val & 0xFFFFFFFF) - 0x100000000) : ($val & 0xFFFFFFFF),
            WasmModule::TYPE_I64 => (int) $val,
            WasmModule::TYPE_F32, WasmModule::TYPE_F64 => (float) $val,
            default => $val,
        };
    }
}
