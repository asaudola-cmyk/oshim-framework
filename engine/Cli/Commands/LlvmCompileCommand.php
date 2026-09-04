<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Compiler\Llvm\LlvmEngine;

class LlvmCompileCommand extends Command
{
    protected string $name = 'compile:llvm';
    protected string $description = 'Demonstrate OSHIM Native LLVM AOT Compilation & 0ms GC Arena Allocator';

    public function execute(Input $input, Output $output): int
    {
        $output->writeln("<bold><cyan>⚡ OSHIM Native Compiler (LLVM Bridge)</cyan></bold>");
        
        $engine = new LlvmEngine();
        
        if (!$engine->isSupported()) {
            $output->writeln("<red>✖ LLVM Binding Failed: " . $engine->getError() . "</red>");
            $output->writeln("<yellow>Note: You need `libLLVM.so` (llvm-dev) installed on your system to run the AOT compiler.</yellow>");
            return 1;
        }

        $output->writeln("<green>✔ LLVM C API Bindings Loaded Successfully via FFI.</green>\n");
        
        // Example 1: Math Function
        $output->writeln("Compiling PHP Function to LLVM IR:");
        $output->writeln("<dim>function add(int \$a, int \$b): int { return \$a + \$b; }</dim>\n");
        $ir = $engine->generateAddFunctionIr();
        $output->writeln("<yellow>--- LLVM IR OUTPUT (Math) ---</yellow>");
        $output->writeln(trim($ir));
        $output->writeln("<yellow>-----------------------------</yellow>\n");
        
        // Example 2: 0ms Arena Allocator (No GC!)
        $output->writeln("Compiling OSHIM 0ms Arena Allocator (Pointer Bump) to LLVM IR:");
        $output->writeln("<dim>// Bypasses PHP's Zend GC! O(1) Allocation.</dim>\n");
        if (method_exists($engine, 'generateArenaBumpAllocatorIr')) {
            $irArena = $engine->generateArenaBumpAllocatorIr();
            $output->writeln("<yellow>--- LLVM IR OUTPUT (Arena Allocator) ---</yellow>");
            $output->writeln(trim($irArena));
            $output->writeln("<yellow>----------------------------------------</yellow>\n");
        }
        
        $output->writeln("<green>🚀 The generated IR can now be compiled to a Native Machine Code Binary (.elf/.exe) via LLVM clang!</green>");
        
        return 0;
    }
}
