<?php
declare(strict_types=1);

namespace Oshim\Wasm;

use Oshim\Wasm\Exceptions\WasmException;

/**
 * Sovereign WebAssembly Engine Facade for Oshim Framework.
 * High-level developer API for compiling, instantiating, and executing Wasm modules.
 */
class WasmEngine
{
    /** @var array<string, array<string, callable>> Global host functions */
    private static array $globalHostFunctions = [];

    /**
     * Compile raw binary string into a WasmModule.
     */
    public static function compile(string $binary, array $options = []): WasmModule
    {
        $parser = new WasmBinaryParser($binary);
        return $parser->parse($binary);
    }

    /**
     * Compile binary from file path into a WasmModule.
     */
    public static function compileFile(string $filePath, array $options = []): WasmModule
    {
        $parser = new WasmBinaryParser();
        return $parser->parseFile($filePath);
    }

    /**
     * Load a WebAssembly file and instantiate it.
     *
     * @param string $filePath Absolute or relative path to .wasm file
     * @param array<string, array<string, mixed>> $imports Custom host imports
     * @param array{fuel?: int, maxMemoryPages?: int, maxCallDepth?: int, timeout?: float, wasi?: bool} $options
     */
    public static function loadFile(string $filePath, array $imports = [], array $options = []): WasmInstance
    {
        $module = self::compileFile($filePath, $options);
        return self::instantiate($module, $imports, $options);
    }

    /**
     * Load raw WebAssembly binary and instantiate it.
     */
    public static function loadBinary(string $binary, array $imports = [], array $options = []): WasmInstance
    {
        return self::instantiate($binary, $imports, $options);
    }

    /**
     * Instantiate a WasmModule or raw binary bytes.
     *
     * @param WasmModule|string $moduleOrBinary
     * @param array<string, array<string, mixed>> $imports
     * @param array{fuel?: int, maxMemoryPages?: int, maxCallDepth?: int, timeout?: float, wasi?: bool} $options
     */
    public static function instantiate(
        WasmModule|string $moduleOrBinary,
        array $imports = [],
        array $options = []
    ): WasmInstance {
        $module = is_string($moduleOrBinary) ? self::compile($moduleOrBinary, $options) : $moduleOrBinary;

        // Merge global host functions
        $allImports = self::$globalHostFunctions;
        foreach ($imports as $mod => $fields) {
            foreach ($fields as $fieldName => $callable) {
                $allImports[$mod][$fieldName] = $callable;
            }
        }

        $sandbox = new WasmSandbox($options);
        return $sandbox->instantiate($module, $allImports);
    }

    /**
     * Run an exported function in a WebAssembly file with one call.
     *
     * @param string $filePath
     * @param string $functionName
     * @param list<int|float> $args
     * @param array{fuel?: int, maxMemoryPages?: int, maxCallDepth?: int, timeout?: float, wasi?: bool} $options
     * @return mixed
     */
    public static function run(
        string $filePath,
        string $functionName = 'main',
        array $args = [],
        array $options = []
    ): mixed {
        $instance = self::loadFile($filePath, [], $options);

        // Fallback search if specified function name not directly exported
        if ($instance->getModule()->getExport($functionName) === null) {
            if ($instance->getModule()->getExport('_start') !== null) {
                $functionName = '_start';
            } elseif ($instance->getModule()->getExport('main') !== null) {
                $functionName = 'main';
            } else {
                $funcs = $instance->getExportedFunctionNames();
                if (!empty($funcs)) {
                    $functionName = $funcs[0];
                }
            }
        }

        return $instance->call($functionName, $args);
    }

    /**
     * Create a fresh execution sandbox.
     */
    public static function createSandbox(array $options = []): WasmSandbox
    {
        return new WasmSandbox($options);
    }

    /**
     * Register a persistent global host function across all engine instantiations.
     */
    public static function registerHostFunction(string $module, string $name, callable $callable): void
    {
        self::$globalHostFunctions[$module][$name] = $callable;
    }

    /**
     * Clear registered global host functions.
     */
    public static function clearGlobalHostFunctions(): void
    {
        self::$globalHostFunctions = [];
    }
}
