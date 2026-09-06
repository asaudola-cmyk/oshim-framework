<?php
declare(strict_types=1);

namespace Oshim\Compiler\Wasm;

/**
 * 👑 Sovereign OSHIM WebAssembly Compiler
 * 
 * WHY: Compiles PHP mathematical and state transitions directly into WebAssembly bytecode (.wasm)
 * and generates the browser loader for instantaneous 0ms client-side execution.
 */
class WasmCompiler
{
    /**
     * Compiles standard state transitions into a WebAssembly module.
     * 
     * @param string $outputPath Absolute file path for the .wasm binary
     * @return int Size of the generated binary in bytes
     */
    public static function compile(string $outputPath): int
    {
        $builder = new WasmModuleBuilder();

        // 1. Register signature: (i32) -> [i32]
        $typeUnary = $builder->addType(
            [WasmModuleBuilder::TYPE_I32],
            [WasmModuleBuilder::TYPE_I32]
        );

        // 2. Register signature: (i32, i32) -> [i32]
        $typeBinary = $builder->addType(
            [WasmModuleBuilder::TYPE_I32, WasmModuleBuilder::TYPE_I32],
            [WasmModuleBuilder::TYPE_I32]
        );

        // Function 1: "increment": (a) => a + 1
        $incCode = WasmModuleBuilder::OP_LOCAL_GET . WasmModuleBuilder::encodeU32(0)
                 . WasmModuleBuilder::OP_I32_CONST . WasmModuleBuilder::encodeI32(1)
                 . WasmModuleBuilder::OP_I32_ADD;
        $builder->addFunction('increment', $typeUnary, $incCode);

        // Function 2: "decrement": (a) => a - 1
        $decCode = WasmModuleBuilder::OP_LOCAL_GET . WasmModuleBuilder::encodeU32(0)
                 . WasmModuleBuilder::OP_I32_CONST . WasmModuleBuilder::encodeI32(1)
                 . WasmModuleBuilder::OP_I32_SUB;
        $builder->addFunction('decrement', $typeUnary, $decCode);

        // Function 3: "add": (a, b) => a + b
        $addCode = WasmModuleBuilder::OP_LOCAL_GET . WasmModuleBuilder::encodeU32(0)
                 . WasmModuleBuilder::OP_LOCAL_GET . WasmModuleBuilder::encodeU32(1)
                 . WasmModuleBuilder::OP_I32_ADD;
        $builder->addFunction('add', $typeBinary, $addCode);

        // Function 4: "multiply": (a, b) => a * b
        $mulCode = WasmModuleBuilder::OP_LOCAL_GET . WasmModuleBuilder::encodeU32(0)
                 . WasmModuleBuilder::OP_LOCAL_GET . WasmModuleBuilder::encodeU32(1)
                 . WasmModuleBuilder::OP_I32_MUL;
        $builder->addFunction('multiply', $typeBinary, $mulCode);

        $binary = $builder->build();

        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($outputPath, $binary);

        return strlen($binary);
    }

    /**
     * Generates the browser JavaScript runtime wrapper.
     */
    public static function generateClientRuntime(): string
    {
        return <<<JS
/**
 * 👑 Sovereign OSHIM WebAssembly Browser Runtime
 * Loads and executes pure PHP-generated .wasm modules at native CPU speed.
 */
class OshimWasm {
    static async load(wasmUrl = '/app.wasm') {
        const response = await fetch(wasmUrl);
        const buffer = await response.arrayBuffer();
        const { instance } = await WebAssembly.instantiate(buffer);
        return instance.exports;
    }
}
if (typeof window !== 'undefined') {
    window.OshimWasm = OshimWasm;
}
JS;
    }
}
