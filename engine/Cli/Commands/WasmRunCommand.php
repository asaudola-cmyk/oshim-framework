<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Wasm\WasmEngine;
use Oshim\Wasm\WasmSandbox;
use Oshim\Wasm\Exceptions\WasmException;
use Throwable;

/**
 * CLI command to execute WebAssembly (.wasm) binary modules in a secure pure-PHP sandbox.
 */
class WasmRunCommand extends Command
{
    protected string $name = 'wasm:run';
    protected string $description = 'Execute a WebAssembly (Wasm) binary module in a secure pure-PHP sandbox';

    protected function configure(): void
    {
        $this->addArgument('file', Input::REQUIRED, 'Path to the .wasm binary file')
             ->addOption('func', 'f', Input::VALUE_OPTIONAL, 'Exported function name to execute', 'main')
             ->addOption('args', 'a', Input::VALUE_OPTIONAL, 'Comma-separated arguments to pass to function')
             ->addOption('fuel', null, Input::VALUE_OPTIONAL, 'Maximum instruction fuel budget')
             ->addOption('memory-limit', 'm', Input::VALUE_OPTIONAL, 'Maximum linear memory in pages (64KB each)')
             ->addOption('timeout', 't', Input::VALUE_OPTIONAL, 'Execution timeout in seconds');
    }

    public function execute(Input $input, Output $output): int
    {
        $args = $input->getArguments();
        $filePath = (string) ($input->getArgument('file') ?? ($args[0] ?? ''));

        if (empty($filePath)) {
            $output->writeln('<red>Error: Missing required Wasm file path.</red>');
            $output->writeln('Usage: <bold>oshim wasm:run <file.wasm> [--func=name] [--args=1,2,3]</bold>');
            return 1;
        }

        if (!file_exists($filePath)) {
            $output->writeln("<red>Error: WebAssembly file not found: {$filePath}</red>");
            return 1;
        }

        $funcName = (string) ($input->getOption('func') ?? 'main');
        $rawArgs = $input->getOption('args');

        $callArgs = [];
        if ($rawArgs !== null && $rawArgs !== '') {
            $parts = explode(',', (string) $rawArgs);
            foreach ($parts as $part) {
                $trimmed = trim($part);
                if (is_numeric($trimmed)) {
                    $callArgs[] = str_contains($trimmed, '.') ? (float) $trimmed : (int) $trimmed;
                } else {
                    $callArgs[] = $trimmed;
                }
            }
        } elseif (count($args) > 1) {
            // Positional arguments following file path
            for ($i = 1; $i < count($args); $i++) {
                $val = $args[$i];
                if (is_numeric($val)) {
                    $callArgs[] = str_contains((string) $val, '.') ? (float) $val : (int) $val;
                } else {
                    $callArgs[] = $val;
                }
            }
        }

        $fuel = $input->getOption('fuel') !== null ? (int) $input->getOption('fuel') : 0;
        $maxMemPages = $input->getOption('memory-limit') !== null ? (int) $input->getOption('memory-limit') : null;
        $timeout = $input->getOption('timeout') !== null ? (float) $input->getOption('timeout') : 0.0;

        $output->writeln('<bold><cyan>⚡ OSHIM Native WebAssembly Runtime</cyan></bold>');
        $output->writeln("Loading module: <bold>{$filePath}</bold>");

        $startTime = microtime(true);

        try {
            $sandbox = new WasmSandbox([
                'fuel'           => $fuel,
                'maxMemoryPages' => $maxMemPages,
                'timeout'        => $timeout,
                'wasi'           => true,
            ]);

            $module = WasmEngine::compileFile($filePath);
            $instance = $sandbox->instantiate($module);

            // Determine target function
            $targetFunc = $funcName;
            if ($module->getExport($targetFunc) === null) {
                if ($module->getExport('_start') !== null) {
                    $targetFunc = '_start';
                } elseif ($module->getExport('main') !== null) {
                    $targetFunc = 'main';
                } else {
                    $allFuncs = $instance->getExportedFunctionNames();
                    if (!empty($allFuncs)) {
                        $targetFunc = $allFuncs[0];
                    }
                }
            }

            $output->writeln("Invoking function: <info>{$targetFunc}</info>(" . implode(', ', array_map('json_encode', $callArgs)) . ")");

            $result = $instance->call($targetFunc, $callArgs);
            $elapsedMs = (microtime(true) - $startTime) * 1000;
            $instructions = $instance->getStackMachine()->getInstructionsExecuted();
            $memPages = $instance->getMemory() ? $instance->getMemory()->size() : 0;

            $output->writeln();
            $output->writeln('<green>✔ Execution completed successfully!</green>');

            if ($result !== null) {
                $resStr = is_array($result) ? json_encode($result) : (string) $result;
                $output->writeln("<bold>Result:</bold> <cyan>{$resStr}</cyan>");
            }

            $output->writeln(sprintf(
                '<dim>Stats: %d instructions executed | %d memory page(s) (%.1f KB) | %.2f ms</dim>',
                $instructions,
                $memPages,
                $memPages * 64,
                $elapsedMs
            ));

            return 0;
        } catch (WasmException $e) {
            $elapsedMs = (microtime(true) - $startTime) * 1000;
            $output->writeln();
            $output->writeln("<red>✘ Wasm Trap/Execution Error: {$e->getMessage()}</red>");
            $output->writeln(sprintf('<dim>Failed after %.2f ms</dim>', $elapsedMs));
            return 1;
        } catch (Throwable $e) {
            $elapsedMs = (microtime(true) - $startTime) * 1000;
            $output->writeln();
            $output->writeln("<red>✘ System Error: {$e->getMessage()}</red>");
            return 1;
        }
    }
}
