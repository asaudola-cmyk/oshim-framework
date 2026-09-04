<?php
declare(strict_types=1);

namespace Tests\Unit\Wasm;

use Oshim\Testing\TestCase;
use Oshim\Wasm\WasmMemory;
use Oshim\Wasm\WasmBinaryParser;
use Oshim\Wasm\WasmModule;
use Oshim\Wasm\WasmStackMachine;
use Oshim\Wasm\WasmSandbox;
use Oshim\Wasm\WasmEngine;

class WasmRuntimeTest extends TestCase
{
    public function testWasmLinearMemoryOperations(): void
    {
        $mem = new WasmMemory(1, 4); // 1 page (64KB), max 4 pages
        $this->assertSame(1, $mem->size());
        $this->assertSame(65536, $mem->getByteSize());

        // Test U8 store and load
        $mem->storeU8(10, 0xAB);
        $this->assertSame(0xAB, $mem->loadU8(10));

        // Test I32 store and load
        $mem->storeI32(100, 123456789);
        $this->assertSame(123456789, $mem->loadI32(100));

        // Negative 32-bit integer
        $mem->storeI32(200, -98765);
        $this->assertSame(-98765, $mem->loadI32(200));

        // String storage
        $mem->storeString(300, 'Hello WebAssembly from PHP!');
        $this->assertSame('Hello WebAssembly from PHP!', $mem->loadString(300, 27));

        // Memory grow
        $oldPages = $mem->grow(2);
        $this->assertSame(1, $oldPages);
        $this->assertSame(3, $mem->size());
        $this->assertSame(3 * 65536, $mem->getByteSize());
    }

    public function testWasmBinaryParserAndExecutionOfAddFunction(): void
    {
        // Construct standard binary WebAssembly for:
        // (module
        //   (func $add (param i32 i32) (result i32)
        //     local.get 0
        //     local.get 1
        //     i32.add)
        //   (export "add" (func $add)))
        
        $magicAndVersion = "\x00\x61\x73\x6D\x01\x00\x00\x00";

        // Section 1: Type Section (1 type: (i32, i32) -> i32)
        // 0x01 (Sec Type), size 7: 0x01 (1 type), 0x60 (func), 0x02 (2 params), 0x7F (i32), 0x7F (i32), 0x01 (1 return), 0x7F (i32)
        $typeSec = "\x01\x07\x01\x60\x02\x7F\x7F\x01\x7F";

        // Section 3: Function Section (1 func with type 0)
        // 0x03 (Sec Func), size 2: 0x01 (1 func), 0x00 (type 0)
        $funcSec = "\x03\x02\x01\x00";

        // Section 7: Export Section (export "add" func 0)
        // 0x07 (Sec Export), size 7: 0x01 (1 export), 0x03 (len 3), "add", 0x00 (func kind), 0x00 (func 0)
        $exportSec = "\x07\x07\x01\x03add\x00\x00";

        // Section 10: Code Section (1 code body)
        // 0x0A (Sec Code), size 9: 0x01 (1 body), 0x07 (body size 7):
        // 0x00 (0 locals), 0x20 0x00 (local.get 0), 0x20 0x01 (local.get 1), 0x6A (i32.add), 0x0B (end)
        $codeSec = "\x0A\x09\x01\x07\x00\x20\x00\x20\x01\x6A\x0B";

        $wasmBinary = $magicAndVersion . $typeSec . $funcSec . $exportSec . $codeSec;

        $engine = new WasmEngine();
        $sandbox = $engine->loadBinary($wasmBinary);

        $this->assertContains('add', $sandbox->getExportNames());

        $result1 = $sandbox->invoke('add', [40, 2]);
        $this->assertSame(42, $result1);

        $result2 = $sandbox->invoke('add', [1500, 3500]);
        $this->assertSame(5000, $result2);
    }

    public function testWasmMultiplicationAndConditionals(): void
    {
        // (module
        //   (func $calc (param i32 i32) (result i32)
        //     local.get 0
        //     local.get 1
        //     i32.mul
        //     i32.const 10
        //     i32.add)
        //   (export "calc" (func $calc)))

        $magicAndVersion = "\x00\x61\x73\x6D\x01\x00\x00\x00";
        $typeSec = "\x01\x07\x01\x60\x02\x7F\x7F\x01\x7F";
        $funcSec = "\x03\x02\x01\x00";
        $exportSec = "\x07\x08\x01\x04calc\x00\x00";
        // Body: 0 locals, local.get 0, local.get 1, i32.mul (0x6C), i32.const 10 (0x41 0x0A), i32.add (0x6A), end (0x0B)
        $codeSec = "\x0A\x0C\x01\x0A\x00\x20\x00\x20\x01\x6C\x41\x0A\x6A\x0B";

        $wasmBinary = $magicAndVersion . $typeSec . $funcSec . $exportSec . $codeSec;

        $engine = new WasmEngine();
        $sandbox = $engine->loadBinary($wasmBinary);

        // 5 * 6 + 10 = 40
        $result = $sandbox->invoke('calc', [5, 6]);
        $this->assertSame(40, $result);
    }
}
