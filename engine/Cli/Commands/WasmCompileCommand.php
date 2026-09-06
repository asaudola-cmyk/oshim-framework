<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Compiler\Wasm\WasmCompiler;

/**
 * 👑 Sovereign OSHIM WebAssembly Compiler CLI Command
 * 
 * Compiles application logic into a browser-executable .wasm binary.
 */
class WasmCompileCommand extends Command
{
    protected string $name = 'compile:wasm';
    protected string $description = 'Compile PHP state & math logic into native WebAssembly bytecode (.wasm)';

    protected function configure(): void
    {
        $this->addOption('output', 'o', Input::VALUE_OPTIONAL, 'Target output path for .wasm binary', 'public/app.wasm');
    }

    public function execute(Input $input, Output $output): int
    {
        $basePath = defined('OSHIM_BASE_PATH') ? OSHIM_BASE_PATH : dirname(__DIR__, 3);
        $outputPath = (string)$input->getOption('output', 'public/app.wasm');

        if (!str_starts_with($outputPath, '/')) {
            $outputPath = $basePath . '/' . $outputPath;
        }

        $output->writeln("<bold><cyan>⚡ OSHIM Pure PHP WebAssembly (Wasm) Compiler</cyan></bold>");
        $output->writeln("Target file: <yellow>{$outputPath}</yellow>");

        try {
            $bytes = WasmCompiler::compile($outputPath);

            // Also write client runtime JS helper to public directory
            $runtimePath = $basePath . '/public/oshim-wasm-runtime.js';
            file_put_contents($runtimePath, WasmCompiler::generateClientRuntime());

            $output->writeln("<green>✔ Successfully compiled WebAssembly module ({$bytes} bytes)</green>");
            $output->writeln("<green>✔ Browser loader written to public/oshim-wasm-runtime.js</green>");
            $output->writeln("<cyan>Exported Wasm functions: increment(), decrement(), add(), multiply()</cyan>\n");

        } catch (\Throwable $e) {
            $output->writeln("<red>Failed to compile WebAssembly: " . $e->getMessage() . "</red>");
            return 1;
        }

        return 0;
    }
}
