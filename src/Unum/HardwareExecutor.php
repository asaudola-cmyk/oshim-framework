<?php

declare(strict_types=1);

namespace Unum;

use FFI;
use RuntimeException;

/**
 * 👑 Hardware Executor & Silicon Bridge
 * 
 * WHY: Bridges PHP directly to native CPU silicon via System V AMD64 ABI.
 * Allocates executable memory pages (mmap PROT_EXEC), compiles 64-bit Universal Numbers
 * into native machine code, and invokes direct hardware execution in nanoseconds.
 */
final class HardwareExecutor
{
    private static ?FFI $ffi = null;
    private static ?string $libPath = null;

    public function __construct(?string $libPath = null)
    {
        if (self::$ffi === null) {
            $path = $libPath ?? dirname(__DIR__, 2) . '/libs/libunum.so';
            if (!file_exists($path)) {
                throw new RuntimeException("UNUM native library not found at: {$path}. Compile via gcc first.");
            }
            self::$libPath = realpath($path);

            $cdefs = <<<'CDEF'
                typedef uint64_t unum_t;
                unum_t unum_encode(uint8_t op, uint8_t type, uint8_t reg_dest, uint8_t reg_src, uint8_t simd, uint32_t payload);
                void unum_decode(unum_t num, uint8_t *op, uint8_t *type, uint8_t *reg_dest, uint8_t *reg_src, uint8_t *simd, uint32_t *payload);
                void* unum_alloc_executable_page(size_t size);
                int unum_free_executable_page(void *addr, size_t size);
                int unum_emit_machine_code(const unum_t *numbers, size_t count, uint8_t *code_buffer, size_t max_size, size_t *emitted_size);
                int64_t unum_execute(const void *code_page, int64_t arg1, int64_t arg2, int64_t arg3);
                float unum_simd_dot_f32(const float *a, const float *b, size_t dim);
                float unum_simd_dot_batch(const float *a, const float *b, size_t dim, size_t count);
                uint32_t unum_cpu_features(void);
CDEF;

            self::$ffi = FFI::cdef($cdefs, self::$libPath);
        }
    }

    /**
     * Allocates page-aligned executable memory via POSIX mmap.
     */
    public function allocPage(int $size = 4096): FFI\CData
    {
        $page = self::$ffi->unum_alloc_executable_page($size);
        if ($page === null) {
            throw new RuntimeException("Failed to allocate executable memory page via mmap(PROT_EXEC).");
        }
        return $page;
    }

    /**
     * Frees an executable memory page via POSIX munmap.
     */
    public function freePage(FFI\CData $page, int $size = 4096): int
    {
        return self::$ffi->unum_free_executable_page($page, $size);
    }

    /**
     * Compiles an array of Universal Numbers into an executable memory page.
     * 
     * @param UniversalNumber[] $numbers
     * @return array{page: FFI\CData, emitted_bytes: int, size: int}
     */
    public function compile(array $numbers, int $pageSize = 4096): array
    {
        $count = count($numbers);
        if ($count === 0) {
            throw new RuntimeException("Cannot compile empty universal number sequence.");
        }

        /* Allocate C array of unum_t */
        $cArray = self::$ffi->new("unum_t[{$count}]");
        foreach ($numbers as $i => $num) {
            $cArray[$i] = $num->toInt();
        }

        /* Allocate executable memory page */
        $page = $this->allocPage($pageSize);

        /* Emit machine code bytes */
        $emittedSizePtr = self::$ffi->new("size_t");
        $codeBuffer = FFI::cast("uint8_t*", $page);

        $err = self::$ffi->unum_emit_machine_code($cArray, $count, $codeBuffer, $pageSize, FFI::addr($emittedSizePtr));
        if ($err !== 0) {
            $this->freePage($page, $pageSize);
            throw new RuntimeException("Machine code emission failed with error code: {$err}");
        }

        $emittedBytes = (int)$emittedSizePtr->cdata;

        return [
            'page'          => $page,
            'emitted_bytes' => $emittedBytes,
            'size'          => $pageSize,
        ];
    }

    /**
     * Executes the machine code directly on the CPU registers.
     * Passes arg1 -> RDI, arg2 -> RSI, arg3 -> RDX.
     * Returns RAX directly to PHP.
     */
    public function execute(FFI\CData $page, int $arg1 = 0, int $arg2 = 0, int $arg3 = 0): int
    {
        return self::$ffi->unum_execute($page, $arg1, $arg2, $arg3);
    }

    /**
     * High-speed hardware AVX-512 / AVX2 vector dot product.
     * 
     * @param float[] $vecA
     * @param float[] $vecB
     */
    public function simdDot(array $vecA, array $vecB): float
    {
        $dim = count($vecA);
        if ($dim !== count($vecB)) {
            throw new RuntimeException("Vector dimension mismatch: " . $dim . " vs " . count($vecB));
        }

        $cA = self::$ffi->new("float[{$dim}]");
        $cB = self::$ffi->new("float[{$dim}]");

        for ($i = 0; $i < $dim; $i++) {
            $cA[$i] = (float)$vecA[$i];
            $cB[$i] = (float)$vecB[$i];
        }

        return (float)self::$ffi->unum_simd_dot_f32($cA, $cB, $dim);
    }

    /**
     * High-speed batch hardware AVX SIMD execution.
     * Computes dot product 'count' times in native C with zero per-iteration FFI overhead.
     * 
     * @param float[] $vecA
     * @param float[] $vecB
     */
    public function simdDotBatch(array $vecA, array $vecB, int $count): float
    {
        $dim = count($vecA);
        if ($dim !== count($vecB)) {
            throw new RuntimeException("Vector dimension mismatch: " . $dim . " vs " . count($vecB));
        }

        $cA = self::$ffi->new("float[{$dim}]");
        $cB = self::$ffi->new("float[{$dim}]");

        for ($i = 0; $i < $dim; $i++) {
            $cA[$i] = (float)$vecA[$i];
            $cB[$i] = (float)$vecB[$i];
        }

        return (float)self::$ffi->unum_simd_dot_batch($cA, $cB, $dim, $count);
    }

    /**
     * Returns hardware CPU capabilities.
     * 
     * @return array{avx: bool, avx2: bool, avx512: bool, fma: bool}
     */
    public function getCpuFeatures(): array
    {
        $flags = self::$ffi->unum_cpu_features();
        return [
            'avx'    => ($flags & 1) !== 0,
            'avx2'   => ($flags & 2) !== 0,
            'avx512' => ($flags & 4) !== 0,
            'fma'    => ($flags & 8) !== 0,
        ];
    }
}
