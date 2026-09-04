<?php
declare(strict_types=1);

namespace Oshim\Wasm;

use Oshim\Wasm\Exceptions\WasmException;
use Oshim\Wasm\Exceptions\WasmTrapException;

/**
 * WebAssembly 1.0 Instantiated Module Instance.
 * Binds resolved imports, memories, tables, globals, and executes start function.
 */
class WasmInstance
{
    private WasmModule $module;
    /** @var array<string, array<string, callable|WasmMemory|WasmTable|WasmGlobal|mixed>> */
    private array $imports = [];
    /** @var list<WasmMemory> */
    private array $memories = [];
    /** @var list<WasmTable> */
    private array $tables = [];
    /** @var list<WasmGlobal> */
    private array $globals = [];
    /** @var list<callable|null> Imported function callables mapped to function indices */
    private array $importedFunctionCallables = [];
    private WasmStackMachine $stackMachine;
    /** @var array<string, mixed> Cached exported objects/callables */
    private array $exports = [];

    /**
     * @param WasmModule $module
     * @param array<string, array<string, mixed>> $imports
     * @param array{fuel?: int, maxCallDepth?: int, timeout?: float, sandboxMaxPages?: int} $options
     */
    public function __construct(WasmModule $module, array $imports = [], array $options = [])
    {
        $this->module = $module;
        $this->imports = $imports;

        $fuelLimit = (int) ($options['fuel'] ?? 0);
        $maxCallDepth = (int) ($options['maxCallDepth'] ?? 1024);
        $timeout = (float) ($options['timeout'] ?? 0.0);
        $sandboxMaxPages = isset($options['sandboxMaxPages']) ? (int) $options['sandboxMaxPages'] : null;

        $this->stackMachine = new WasmStackMachine($this, $fuelLimit, $maxCallDepth, $timeout);

        $this->initializeImports();
        $this->initializeMemories($sandboxMaxPages);
        $this->initializeTables();
        $this->initializeGlobals();
        $this->initializeElements();
        $this->initializeData();
        $this->executeStartFunction();
    }

    /**
     * Call an exported function by name.
     *
     * @param string $name Export name
     * @param list<int|float> $args
     * @return mixed
     */
    public function call(string $name, array $args = []): mixed
    {
        $exp = $this->module->getExport($name);
        if ($exp === null) {
            throw new WasmException("Export '{$name}' not found in module");
        }
        if ($exp['kind'] !== WasmModule::KIND_FUNC) {
            throw new WasmException("Export '{$name}' is not a function (kind: {$exp['kind']})");
        }

        return $this->stackMachine->invoke($exp['index'], $args);
    }

    /**
     * Alias for call()
     */
    public function invoke(string $name, array $args = []): mixed
    {
        return $this->call($name, $args);
    }

    /**
     * Call a function directly by its global function index.
     *
     * @param int $funcIndex
     * @param list<int|float> $args
     * @return mixed
     */
    public function callFunctionIndex(int $funcIndex, array $args = []): mixed
    {
        return $this->stackMachine->invoke($funcIndex, $args);
    }

    /**
     * Get an export by name (function index, memory, table, or global).
     */
    public function getExport(string $name): mixed
    {
        $exp = $this->module->getExport($name);
        if ($exp === null) {
            return null;
        }

        return match ($exp['kind']) {
            WasmModule::KIND_FUNC => fn(mixed ...$args) => $this->stackMachine->invoke($exp['index'], array_values($args)),
            WasmModule::KIND_TABLE => $this->tables[$exp['index']] ?? null,
            WasmModule::KIND_MEMORY => $this->memories[$exp['index']] ?? null,
            WasmModule::KIND_GLOBAL => $this->globals[$exp['index']] ?? null,
            default => null,
        };
    }

    /**
     * Get list of all exported function names.
     *
     * @return list<string>
     */
    public function getExportedFunctionNames(): array
    {
        $names = [];
        foreach ($this->module->exports as $exp) {
            if ($exp['kind'] === WasmModule::KIND_FUNC) {
                $names[] = $exp['name'];
            }
        }
        return $names;
    }

    /**
     * Get list of all export names.
     *
     * @return list<string>
     */
    public function getExportNames(): array
    {
        $names = [];
        foreach ($this->module->exports as $exp) {
            $names[] = $exp['name'];
        }
        return $names;
    }

    /**
     * Get primary linear memory instance.
     */
    public function getMemory(int $index = 0): ?WasmMemory
    {
        return $this->memories[$index] ?? null;
    }

    /**
     * Get table instance.
     */
    public function getTable(int $index = 0): ?WasmTable
    {
        return $this->tables[$index] ?? null;
    }

    /**
     * Get global variable instance.
     */
    public function getGlobal(int $index = 0): ?WasmGlobal
    {
        return $this->globals[$index] ?? null;
    }

    /**
     * Get stack machine interpreter.
     */
    public function getStackMachine(): WasmStackMachine
    {
        return $this->stackMachine;
    }

    /**
     * Get underlying module.
     */
    public function getModule(): WasmModule
    {
        return $this->module;
    }

    /**
     * Get callable for imported function by global function index.
     */
    public function getImportedFunctionCallable(int $funcIndex): ?callable
    {
        return $this->importedFunctionCallables[$funcIndex] ?? null;
    }

    // --- Private Initialization Routines ---

    private function initializeImports(): void
    {
        $fIdx = 0;
        foreach ($this->module->imports as $imp) {
            $mod = $imp['module'];
            $name = $imp['name'];

            if ($imp['kind'] === WasmModule::KIND_FUNC) {
                $callable = $this->imports[$mod][$name] ?? null;
                if ($callable !== null && is_callable($callable)) {
                    $this->importedFunctionCallables[$fIdx] = $callable;
                } else {
                    $this->importedFunctionCallables[$fIdx] = null;
                }
                $fIdx++;
            } elseif ($imp['kind'] === WasmModule::KIND_MEMORY) {
                $mem = $this->imports[$mod][$name] ?? null;
                if ($mem instanceof WasmMemory) {
                    $this->memories[] = $mem;
                }
            } elseif ($imp['kind'] === WasmModule::KIND_TABLE) {
                $tbl = $this->imports[$mod][$name] ?? null;
                if ($tbl instanceof WasmTable) {
                    $this->tables[] = $tbl;
                }
            } elseif ($imp['kind'] === WasmModule::KIND_GLOBAL) {
                $glob = $this->imports[$mod][$name] ?? null;
                if ($glob instanceof WasmGlobal) {
                    $this->globals[] = $glob;
                } elseif (is_numeric($glob)) {
                    $this->globals[] = new WasmGlobal($imp['desc']['type'], $imp['desc']['mutable'], $glob);
                }
            }
        }
    }

    private function initializeMemories(?int $sandboxMaxPages): void
    {
        foreach ($this->module->memories as $memDef) {
            $this->memories[] = new WasmMemory($memDef['min'], $memDef['max'], $sandboxMaxPages);
        }
    }

    private function initializeTables(): void
    {
        foreach ($this->module->tables as $tblDef) {
            $this->tables[] = new WasmTable($tblDef['elemType'], $tblDef['min'], $tblDef['max']);
        }
    }

    private function initializeGlobals(): void
    {
        foreach ($this->module->globals as $gDef) {
            $initialValue = $this->stackMachine->evaluateInitExpr($gDef['init']);
            $this->globals[] = new WasmGlobal($gDef['type'], $gDef['mutable'], $initialValue);
        }
    }

    private function initializeElements(): void
    {
        foreach ($this->module->elements as $elem) {
            $offset = (int) $this->stackMachine->evaluateInitExpr($elem['offsetExpr']);
            $table = $this->tables[$elem['tableIndex']] ?? null;
            if ($table !== null) {
                foreach ($elem['init'] as $i => $funcIdx) {
                    $targetSlot = $offset + $i;
                    if ($targetSlot >= $table->size()) {
                        $table->grow($targetSlot - $table->size() + 1);
                    }
                    $table->set($targetSlot, $funcIdx);
                }
            }
        }
    }

    private function initializeData(): void
    {
        foreach ($this->module->data as $dataDef) {
            $offset = (int) $this->stackMachine->evaluateInitExpr($dataDef['offsetExpr']);
            $mem = $this->memories[$dataDef['memoryIndex']] ?? null;
            if ($mem !== null) {
                $mem->writeBytes($offset, $dataDef['data']);
            }
        }
    }

    private function executeStartFunction(): void
    {
        if ($this->module->start !== null) {
            $this->stackMachine->invoke($this->module->start, []);
        }
    }
}
