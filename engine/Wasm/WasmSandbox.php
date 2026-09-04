<?php
declare(strict_types=1);

namespace Oshim\Wasm;

use Oshim\Wasm\Exceptions\WasmException;
use Oshim\Wasm\Exceptions\WasmTrapException;
use Oshim\Wasm\Exceptions\WasmFuelExhaustedException;
use Oshim\Wasm\Exceptions\WasmMemoryOutOfBoundsException;
use Oshim\Wasm\Exceptions\WasmStackOverflowException;

/**
 * Sandboxed Execution Environment for WebAssembly Modules.
 * Enforces resource quotas (fuel, memory pages, call stack depth, timeouts) and provides WASI host isolation.
 */
class WasmSandbox
{
    private int $fuelLimit = 0; // 0 = unlimited
    private ?int $maxMemoryPages = null;
    private int $maxCallDepth = 1024;
    private float $timeoutSeconds = 0.0;
    private bool $wasiEnabled = true;

    private string $stdout = '';
    private string $stderr = '';
    private ?int $exitCode = null;
    /** @var array<string, string> */
    private array $env = [];
    /** @var list<string> */
    private array $args = [];

    /** @var array<string, array<string, callable|mixed>> */
    private array $hostImports = [];
    private ?WasmInstance $lastInstance = null;

    public function __construct(array $options = [])
    {
        if (isset($options['fuel'])) {
            $this->fuelLimit = (int) $options['fuel'];
        }
        if (isset($options['maxMemoryPages'])) {
            $this->maxMemoryPages = (int) $options['maxMemoryPages'];
        }
        if (isset($options['maxCallDepth'])) {
            $this->maxCallDepth = (int) $options['maxCallDepth'];
        }
        if (isset($options['timeout'])) {
            $this->timeoutSeconds = (float) $options['timeout'];
        }
        if (isset($options['wasi'])) {
            $this->wasiEnabled = (bool) $options['wasi'];
        }
        if (isset($options['env']) && is_array($options['env'])) {
            $this->env = $options['env'];
        }
        if (isset($options['args']) && is_array($options['args'])) {
            $this->args = array_values($options['args']);
        }
    }

    public function setFuelLimit(int $fuel): static
    {
        $this->fuelLimit = max(0, $fuel);
        return $this;
    }

    public function getFuelLimit(): int
    {
        return $this->fuelLimit;
    }

    public function setMaxMemoryPages(int $pages): static
    {
        $this->maxMemoryPages = max(1, $pages);
        return $this;
    }

    public function getMaxMemoryPages(): ?int
    {
        return $this->maxMemoryPages;
    }

    public function setMaxCallDepth(int $depth): static
    {
        $this->maxCallDepth = max(1, $depth);
        return $this;
    }

    public function getMaxCallDepth(): int
    {
        return $this->maxCallDepth;
    }

    public function setTimeout(float $seconds): static
    {
        $this->timeoutSeconds = max(0.0, $seconds);
        return $this;
    }

    public function getTimeout(): float
    {
        return $this->timeoutSeconds;
    }

    public function enableWasi(bool $enable = true): static
    {
        $this->wasiEnabled = $enable;
        return $this;
    }

    public function isWasiEnabled(): bool
    {
        return $this->wasiEnabled;
    }

    public function setEnv(array $env): static
    {
        $this->env = $env;
        return $this;
    }

    public function setArgs(array $args): static
    {
        $this->args = array_values($args);
        return $this;
    }

    /**
     * Register a custom host function import.
     */
    public function registerHostFunction(string $module, string $name, callable $callable): static
    {
        $this->hostImports[$module][$name] = $callable;
        return $this;
    }

    /**
     * Get registered host functions.
     *
     * @return array<string, array<string, callable|mixed>>
     */
    public function getHostFunctions(): array
    {
        return $this->hostImports;
    }

    /**
     * Get captured stdout.
     */
    public function getStdout(): string
    {
        return $this->stdout;
    }

    /**
     * Get captured stderr.
     */
    public function getStderr(): string
    {
        return $this->stderr;
    }

    /**
     * Get process exit code (from WASI proc_exit).
     */
    public function getExitCode(): ?int
    {
        return $this->exitCode;
    }

    /**
     * Clear captured outputs and reset state.
     */
    public function clearOutput(): void
    {
        $this->stdout = '';
        $this->stderr = '';
        $this->exitCode = null;
    }

    /**
     * Append to sandbox stdout.
     */
    public function appendStdout(string $data): void
    {
        $this->stdout .= $data;
    }

    /**
     * Append to sandbox stderr.
     */
    public function appendStderr(string $data): void
    {
        $this->stderr .= $data;
    }

    /**
     * Execute callable with explicit fuel limit.
     */
    public function executeWithFuel(callable $code, int $fuel): mixed
    {
        $prevFuel = $this->fuelLimit;
        $this->fuelLimit = $fuel;
        try {
            return $code();
        } finally {
            $this->fuelLimit = $prevFuel;
        }
    }

    /**
     * Instantiate a WasmModule or binary string inside the sandbox.
     */
    public function instantiate(WasmModule|string $moduleOrBinary, array $customImports = []): WasmInstance
    {
        $module = is_string($moduleOrBinary) ? (new WasmBinaryParser())->parse($moduleOrBinary) : $moduleOrBinary;
        $imports = $this->hostImports;

        // Merge custom user imports
        foreach ($customImports as $mod => $fields) {
            foreach ($fields as $fieldName => $callable) {
                $imports[$mod][$fieldName] = $callable;
            }
        }

        // Holder for memory reference to be bound after instance creation
        $memoryRef = new class {
            public ?WasmMemory $memory = null;
        };

        // Attach WASI Preview 1 host calls if enabled
        if ($this->wasiEnabled) {
            $this->attachWasiImports($imports, $memoryRef);
        }

        $options = [
            'fuel'            => $this->fuelLimit,
            'maxCallDepth'    => $this->maxCallDepth,
            'timeout'         => $this->timeoutSeconds,
            'sandboxMaxPages' => $this->maxMemoryPages,
            'sandbox'         => $this,
        ];

        $instance = new WasmInstance($module, $imports, $options);
        $memoryRef->memory = $instance->getMemory();
        $this->lastInstance = $instance;

        return $instance;
    }

    /**
     * Compile binary / parse file and run exported function.
     */
    public function run(WasmModule|string $wasm, string $funcName = '_start', array $args = []): mixed
    {
        $instance = $this->instantiate($wasm);

        // If requested function doesn't exist, try fallback names: _start, main, or first exported function
        if ($instance->getModule()->getExport($funcName) === null) {
            if ($instance->getModule()->getExport('_start') !== null) {
                $funcName = '_start';
            } elseif ($instance->getModule()->getExport('main') !== null) {
                $funcName = 'main';
            } else {
                $exportedFuncs = $instance->getExportedFunctionNames();
                if (!empty($exportedFuncs)) {
                    $funcName = $exportedFuncs[0];
                }
            }
        }

        return $instance->call($funcName, $args);
    }

    /**
     * Proxy invoke to last instantiated instance.
     */
    public function invoke(string $name, array $args = []): mixed
    {
        if ($this->lastInstance === null) {
            throw new WasmException('No Wasm module instance has been loaded into this sandbox yet');
        }
        return $this->lastInstance->call($name, $args);
    }

    /**
     * Proxy call to last instantiated instance.
     */
    public function call(string $name, array $args = []): mixed
    {
        return $this->invoke($name, $args);
    }

    /**
     * Proxy getExportNames to last instantiated instance.
     *
     * @return list<string>
     */
    public function getExportNames(): array
    {
        if ($this->lastInstance === null) {
            return [];
        }
        return $this->lastInstance->getExportNames();
    }

    /**
     * Proxy getExportedFunctionNames to last instantiated instance.
     *
     * @return list<string>
     */
    public function getExportedFunctionNames(): array
    {
        if ($this->lastInstance === null) {
            return [];
        }
        return $this->lastInstance->getExportedFunctionNames();
    }

    /**
     * Get primary linear memory of last instantiated module.
     */
    public function getMemory(int $index = 0): ?WasmMemory
    {
        return $this->lastInstance?->getMemory($index);
    }

    /**
     * Get table of last instantiated module.
     */
    public function getTable(int $index = 0): ?WasmTable
    {
        return $this->lastInstance?->getTable($index);
    }

    /**
     * Get global of last instantiated module.
     */
    public function getGlobal(int $index = 0): ?WasmGlobal
    {
        return $this->lastInstance?->getGlobal($index);
    }

    /**
     * Get last instantiated module instance.
     */
    public function getLastInstance(): ?WasmInstance
    {
        return $this->lastInstance;
    }

    /**
     * Setup standard WASI Preview 1 host imports (wasi_snapshot_preview1 / wasi_unstable).
     */
    private function attachWasiImports(array &$imports, object $memoryRef): void
    {
        $wasiSnapshot = [
            // fd_write(fd: i32, iovs_ptr: i32, iovs_len: i32, nwritten_ptr: i32): i32
            'fd_write' => function (int $fd, int $iovsPtr, int $iovsLen, int $nwrittenPtr) use ($memoryRef): int {
                // WASI errno: 0 = SUCCESS, 8 = EBADF (Bad file descriptor)
                if ($fd !== 1 && $fd !== 2) {
                    return 8;
                }

                $memory = $memoryRef->memory;
                if ($memory === null) {
                    return 8;
                }

                $totalWritten = 0;
                for ($i = 0; $i < $iovsLen; $i++) {
                    $iovAddr = $iovsPtr + ($i * 8);
                    $bufPtr = $memory->loadI32($iovAddr);
                    $bufLen = $memory->loadI32($iovAddr + 4);

                    if ($bufLen > 0) {
                        $data = $memory->readBytes($bufPtr, $bufLen);
                        if ($fd === 1) {
                            $this->stdout .= $data;
                        } else {
                            $this->stderr .= $data;
                        }
                        $totalWritten += $bufLen;
                    }
                }

                if ($nwrittenPtr > 0) {
                    $memory->storeI32($nwrittenPtr, $totalWritten);
                }

                return 0; // WASI SUCCESS
            },

            // proc_exit(rval: i32)
            'proc_exit' => function (int $code): void {
                $this->exitCode = $code;
                throw new WasmTrapException("WASI proc_exit called with code {$code}", 'wasi_exit');
            },

            // environ_sizes_get(environ_count_ptr: i32, environ_buf_size_ptr: i32): i32
            'environ_sizes_get' => function (int $cntPtr, int $bufSizePtr) use ($memoryRef): int {
                $memory = $memoryRef->memory;
                if ($memory === null) {
                    return 0;
                }
                $count = count($this->env);
                $bufSize = 0;
                foreach ($this->env as $k => $v) {
                    $bufSize += strlen($k) + 1 + strlen($v) + 1; // "KEY=VALUE\0"
                }
                $memory->storeI32($cntPtr, $count);
                $memory->storeI32($bufSizePtr, $bufSize);
                return 0;
            },

            // environ_get(environ_ptr: i32, environ_buf_ptr: i32): i32
            'environ_get' => function (int $envPtr, int $bufPtr) use ($memoryRef): int {
                $memory = $memoryRef->memory;
                if ($memory === null) {
                    return 0;
                }
                $currBuf = $bufPtr;
                $currPtr = $envPtr;
                foreach ($this->env as $k => $v) {
                    $entry = "{$k}={$v}\x00";
                    $memory->storeI32($currPtr, $currBuf);
                    $memory->writeBytes($currBuf, $entry);
                    $currPtr += 4;
                    $currBuf += strlen($entry);
                }
                return 0;
            },

            // args_sizes_get(argc_ptr: i32, argv_buf_size_ptr: i32): i32
            'args_sizes_get' => function (int $argcPtr, int $bufSizePtr) use ($memoryRef): int {
                $memory = $memoryRef->memory;
                if ($memory === null) {
                    return 0;
                }
                $argc = count($this->args);
                $bufSize = 0;
                foreach ($this->args as $arg) {
                    $bufSize += strlen($arg) + 1;
                }
                $memory->storeI32($argcPtr, $argc);
                $memory->storeI32($bufSizePtr, $bufSize);
                return 0;
            },

            // args_get(argv_ptr: i32, argv_buf_ptr: i32): i32
            'args_get' => function (int $argvPtr, int $bufPtr) use ($memoryRef): int {
                $memory = $memoryRef->memory;
                if ($memory === null) {
                    return 0;
                }
                $currBuf = $bufPtr;
                $currPtr = $argvPtr;
                foreach ($this->args as $arg) {
                    $entry = $arg . "\x00";
                    $memory->storeI32($currPtr, $currBuf);
                    $memory->writeBytes($currBuf, $entry);
                    $currPtr += 4;
                    $currBuf += strlen($entry);
                }
                return 0;
            },

            // clock_time_get(id: i32, precision: i64, time_ptr: i32): i32
            'clock_time_get' => function (int $id, int $precision, int $timePtr) use ($memoryRef): int {
                $memory = $memoryRef->memory;
                if ($memory !== null) {
                    $ns = (int) (microtime(true) * 1_000_000_000);
                    $memory->storeI64($timePtr, $ns);
                }
                return 0;
            },

            // random_get(buf_ptr: i32, buf_len: i32): i32
            'random_get' => function (int $bufPtr, int $bufLen) use ($memoryRef): int {
                $memory = $memoryRef->memory;
                if ($memory !== null && $bufLen > 0) {
                    try {
                        $bytes = random_bytes($bufLen);
                    } catch (\Throwable) {
                        $bytes = str_repeat("\x00", $bufLen);
                    }
                    $memory->writeBytes($bufPtr, $bytes);
                }
                return 0;
            },

            // fd_close(fd: i32): i32
            'fd_close' => fn(int $fd) => 0,

            // fd_seek(fd: i32, offset: i64, whence: i32, newoffset_ptr: i32): i32
            'fd_seek' => fn(int $fd, int $offset, int $whence, int $newoffsetPtr) => 0,

            // fd_read(fd: i32, iovs_ptr: i32, iovs_len: i32, nread_ptr: i32): i32
            'fd_read' => fn(int $fd, int $iovsPtr, int $iovsLen, int $nreadPtr) => 0,

            // fd_fdstat_get(fd: i32, stat_ptr: i32): i32
            'fd_fdstat_get' => fn(int $fd, int $statPtr) => 0,

            // fd_fdstat_set_flags(fd: i32, flags: i32): i32
            'fd_fdstat_set_flags' => fn(int $fd, int $flags) => 0,

            // fd_prestat_get(fd: i32, prestat_ptr: i32): i32 (52 = EBADF for non-preopened dirs)
            'fd_prestat_get' => fn(int $fd, int $prestatPtr) => 52,

            // fd_prestat_dir_name(fd: i32, path: i32, path_len: i32): i32
            'fd_prestat_dir_name' => fn(int $fd, int $path, int $pathLen) => 52,

            // sched_yield(): i32
            'sched_yield' => fn() => 0,
        ];

        foreach (['wasi_snapshot_preview1', 'wasi_unstable'] as $modName) {
            foreach ($wasiSnapshot as $funcName => $callable) {
                if (!isset($imports[$modName][$funcName])) {
                    $imports[$modName][$funcName] = $callable;
                }
            }
        }
    }
}
