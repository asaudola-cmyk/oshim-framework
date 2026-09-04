<?php
declare(strict_types=1);

namespace Oshim;

require_once __DIR__ . '/Autoloader.php';

// Auto-register PSR-4 Autoloader on require
Autoloader::register([], dirname(__DIR__));

use Closure;
use Oshim\Ai\OshimAi;
use Oshim\Cache\CacheManager;
use Oshim\Database\Connection;
use Oshim\Database\ConnectionManager;
use Oshim\Http\Request;
use Oshim\Http\Response;
use Oshim\Kernel\MicroKernel;
use Oshim\Ledger\Blockchain;
use Oshim\Queue\QueueManager;
use Oshim\Ui\Css\TailwindJitCompiler;
use Oshim\Wasm\WasmEngine;

/**
 * 👑 OSHIM Sovereign Gateway & Micro-Kernel.
 * Enables zero-cost standalone execution: use any subsystem or run a complete web microservice
 * without booting service providers, configuration files, or heavy monolithic kernels.
 */
class Oshim
{
    private static ?MicroKernel $microKernel = null;
    private static ?Blockchain $ledger = null;
    private static ?WasmEngine $wasm = null;
    private static ?OshimAi $ai = null;
    private static ?CacheManager $cache = null;
    private static ?QueueManager $queue = null;

    /**
     * Get or initialize the MicroKernel.
     */
    public static function micro(): MicroKernel
    {
        if (self::$microKernel === null) {
            self::$microKernel = new MicroKernel();
        }
        return self::$microKernel;
    }

    // --- Micro Routing API ---
    public static function get(string $path, Closure|array|string $handler): MicroKernel
    {
        return self::micro()->get($path, $handler);
    }

    public static function post(string $path, Closure|array|string $handler): MicroKernel
    {
        return self::micro()->post($path, $handler);
    }

    public static function put(string $path, Closure|array|string $handler): MicroKernel
    {
        return self::micro()->put($path, $handler);
    }

    public static function delete(string $path, Closure|array|string $handler): MicroKernel
    {
        return self::micro()->delete($path, $handler);
    }

    public static function route(string $method, string $path, Closure|array|string $handler): MicroKernel
    {
        return self::micro()->addRoute($method, $path, $handler);
    }

    /**
     * Execute MicroKernel and emit response.
     */
    public static function run(?Request $request = null): Response
    {
        $response = self::micro()->handle($request);
        if (php_sapi_name() !== 'cli' && !getenv('TESTING')) {
            $response->send();
        }
        return $response;
    }

    // --- Autonomous Standalone Subsystems (Lazy-Loaded Zero-Cost) ---

    /**
     * Standalone AI Engine (LangGraph, Multi-Agent Squads, Tensors, Vector RAG).
     */
    public static function ai(): OshimAi
    {
        if (self::$ai === null) {
            self::$ai = new OshimAi();
        }
        return self::$ai;
    }

    /**
     * Standalone Cryptographic Immutable Blockchain Ledger.
     */
    public static function ledger(int $difficulty = 1): Blockchain
    {
        if (self::$ledger === null) {
            self::$ledger = new Blockchain($difficulty);
        }
        return self::$ledger;
    }

    /**
     * Standalone WebAssembly (Wasm) Engine.
     */
    public static function wasm(): WasmEngine
    {
        if (self::$wasm === null) {
            self::$wasm = new WasmEngine();
        }
        return self::$wasm;
    }

    /**
     * Standalone Pure PHP Tailwind CSS JIT Compiler.
     */
    public static function tailwind(string $html): string
    {
        return TailwindJitCompiler::compile($html);
    }

    /**
     * Standalone Database Connection (Only boots DB when called).
     */
    public static function db(?string $connection = null): Connection
    {
        $manager = ConnectionManager::getInstance();
        return $manager->connection($connection);
    }

    /**
     * Standalone Cache Manager.
     */
    public static function cache(): CacheManager
    {
        if (self::$cache === null) {
            self::$cache = new CacheManager();
        }
        return self::$cache;
    }

    /**
     * Standalone Queue Manager.
     */
    public static function queue(): QueueManager
    {
        if (self::$queue === null) {
            self::$queue = new QueueManager();
        }
        return self::$queue;
    }

    /**
     * Reset in-memory micro states (useful for test isolation).
     */
    public static function reset(): void
    {
        self::$microKernel = null;
        self::$ledger = null;
        self::$wasm = null;
        self::$ai = null;
        self::$cache = null;
        self::$queue = null;
    }
}

// Register global alias \Oshim if available
if (!class_exists('Oshim', false)) {
    class_alias(Oshim::class, 'Oshim');
}
