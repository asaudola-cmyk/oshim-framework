<?php
declare(strict_types=1);

namespace Oshim\Wasm;

/**
 * Represents a parsed WebAssembly 1.0 module structure.
 */
class WasmModule
{
    // WebAssembly Type Constants
    public const TYPE_I32 = 0x7F;
    public const TYPE_I64 = 0x7E;
    public const TYPE_F32 = 0x7D;
    public const TYPE_F64 = 0x7C;
    public const TYPE_FUNCREF = 0x70;
    public const TYPE_VOID = 0x40;

    // External Kind Constants
    public const KIND_FUNC = 0x00;
    public const KIND_TABLE = 0x01;
    public const KIND_MEMORY = 0x02;
    public const KIND_GLOBAL = 0x03;

    /** @var list<array{params: list<int>, results: list<int>}> */
    public array $types = [];

    /** @var list<array{module: string, name: string, kind: int, desc: mixed}> */
    public array $imports = [];

    /** @var list<int> Type indices for internal functions */
    public array $functions = [];

    /** @var list<array{elemType: int, min: int, max: ?int}> */
    public array $tables = [];

    /** @var list<array{min: int, max: ?int}> */
    public array $memories = [];

    /** @var list<array{type: int, mutable: bool, init: string|array}> */
    public array $globals = [];

    /** @var list<array{name: string, kind: int, index: int}> */
    public array $exports = [];

    /** @var int|null Function index executed upon instantiation */
    public ?int $start = null;

    /** @var list<array{tableIndex: int, offsetExpr: string|array, init: list<int>}> */
    public array $elements = [];

    /** @var list<array{locals: list<int>, code: string}> */
    public array $codes = [];

    /** @var list<array{memoryIndex: int, offsetExpr: string|array, data: string}> */
    public array $data = [];

    /** @var list<array{name: string, data: string}> */
    public array $customSections = [];

    /**
     * Get count of imported functions.
     */
    public function getImportedFunctionCount(): int
    {
        $count = 0;
        foreach ($this->imports as $imp) {
            if ($imp['kind'] === self::KIND_FUNC) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Get count of imported tables.
     */
    public function getImportedTableCount(): int
    {
        $count = 0;
        foreach ($this->imports as $imp) {
            if ($imp['kind'] === self::KIND_TABLE) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Get count of imported memories.
     */
    public function getImportedMemoryCount(): int
    {
        $count = 0;
        foreach ($this->imports as $imp) {
            if ($imp['kind'] === self::KIND_MEMORY) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Get count of imported globals.
     */
    public function getImportedGlobalCount(): int
    {
        $count = 0;
        foreach ($this->imports as $imp) {
            if ($imp['kind'] === self::KIND_GLOBAL) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Get the total number of functions (imported + defined).
     */
    public function getTotalFunctionCount(): int
    {
        return $this->getImportedFunctionCount() + count($this->functions);
    }

    /**
     * Check whether a given function index is imported.
     */
    public function isImportedFunction(int $funcIndex): bool
    {
        return $funcIndex < $this->getImportedFunctionCount();
    }

    /**
     * Get the imported function definition by its global function index.
     *
     * @return array{module: string, name: string, kind: int, desc: int}|null
     */
    public function getImportedFunction(int $funcIndex): ?array
    {
        $fIdx = 0;
        foreach ($this->imports as $imp) {
            if ($imp['kind'] === self::KIND_FUNC) {
                if ($fIdx === $funcIndex) {
                    return $imp;
                }
                $fIdx++;
            }
        }
        return null;
    }

    /**
     * Get the function type signature (params and results) by function index.
     *
     * @return array{params: list<int>, results: list<int>}|null
     */
    public function getFunctionType(int $funcIndex): ?array
    {
        $impCount = $this->getImportedFunctionCount();
        if ($funcIndex < $impCount) {
            $imp = $this->getImportedFunction($funcIndex);
            if ($imp !== null && isset($this->types[$imp['desc']])) {
                return $this->types[$imp['desc']];
            }
            return null;
        }

        $internalIndex = $funcIndex - $impCount;
        if (isset($this->functions[$internalIndex])) {
            $typeIndex = $this->functions[$internalIndex];
            return $this->types[$typeIndex] ?? null;
        }

        return null;
    }

    /**
     * Get the code definition for an internal function index.
     *
     * @return array{locals: list<int>, code: string}|null
     */
    public function getInternalFunctionCode(int $funcIndex): ?array
    {
        $impCount = $this->getImportedFunctionCount();
        $internalIndex = $funcIndex - $impCount;
        return $this->codes[$internalIndex] ?? null;
    }

    /**
     * Find an export by name.
     *
     * @return array{name: string, kind: int, index: int}|null
     */
    public function getExport(string $name): ?array
    {
        foreach ($this->exports as $exp) {
            if ($exp['name'] === $name) {
                return $exp;
            }
        }
        return null;
    }

    /**
     * Get all exported functions as a map: [exportName => funcIndex].
     *
     * @return array<string, int>
     */
    public function getExportedFunctions(): array
    {
        $funcs = [];
        foreach ($this->exports as $exp) {
            if ($exp['kind'] === self::KIND_FUNC) {
                $funcs[$exp['name']] = $exp['index'];
            }
        }
        return $funcs;
    }

    /**
     * Format a type byte to its human-readable name.
     */
    public static function typeToString(int $type): string
    {
        return match ($type) {
            self::TYPE_I32 => 'i32',
            self::TYPE_I64 => 'i64',
            self::TYPE_F32 => 'f32',
            self::TYPE_F64 => 'f64',
            self::TYPE_FUNCREF => 'funcref',
            self::TYPE_VOID => 'void',
            default => sprintf('type(0x%02X)', $type),
        };
    }
}
