<?php

declare(strict_types=1);

namespace Unum\Tensor;

use FFI;
use InvalidArgumentException;
use RuntimeException;
use Unum\HardwareExecutor;

/**
 * 👑 Sovereign Bare-Metal 2D Tensor & Neural Engine
 * 
 * WHY: Traditional AI/ML stacks require gigabytes of Python runtime, PyTorch/TensorFlow,
 * and complex C++ bindings just to multiply matrices and compute activations.
 * Tensor2D stores contiguous float32 data directly in native C memory, executing AVX-512
 * and AVX2 fused multiply-add (FMA) instructions directly from PHP with zero garbage collection overhead.
 */
final class Tensor2D
{
    private int $rows;
    private int $cols;
    private int $size;
    private FFI\CData $buffer;
    private HardwareExecutor $executor;

    public function __construct(int $rows, int $cols, ?FFI\CData $buffer = null, ?HardwareExecutor $executor = null)
    {
        if ($rows <= 0 || $cols <= 0) {
            throw new InvalidArgumentException("Tensor dimensions must be positive integers, got: {$rows}x{$cols}");
        }

        $this->rows = $rows;
        $this->cols = $cols;
        $this->size = $rows * $cols;
        $this->executor = $executor ?? new HardwareExecutor();

        if ($buffer !== null) {
            $this->buffer = $buffer;
        } else {
            /* WHY: Allocate contiguous C memory buffer for SIMD vector access */
            $this->buffer = $this->executor->newFloatBuffer($this->size);
            for ($i = 0; $i < $this->size; $i++) {
                $this->buffer[$i] = 0.0;
            }
        }
    }

    /**
     * Creates a tensor initialized to zeros.
     */
    public static function zeros(int $rows, int $cols, ?HardwareExecutor $executor = null): self
    {
        return new self($rows, $cols, null, $executor);
    }

    /**
     * Creates a tensor from a 2D PHP nested float array.
     * 
     * @param array<int, array<int, float|int>> $data
     */
    public static function fromArray(array $data, ?HardwareExecutor $executor = null): self
    {
        $rows = count($data);
        if ($rows === 0) {
            throw new InvalidArgumentException("Cannot create tensor from empty array.");
        }

        $cols = count($data[0]);
        if ($cols === 0) {
            throw new InvalidArgumentException("Cannot create tensor with empty row.");
        }

        $exec = $executor ?? new HardwareExecutor();
        $buffer = $exec->newFloatBuffer($rows * $cols);

        $idx = 0;
        for ($r = 0; $r < $rows; $r++) {
            if (count($data[$r]) !== $cols) {
                throw new InvalidArgumentException("Irregular matrix: row {$r} has " . count($data[$r]) . " cols, expected {$cols}.");
            }
            for ($c = 0; $c < $cols; $c++) {
                $buffer[$idx++] = (float)$data[$r][$c];
            }
        }

        return new self($rows, $cols, $buffer, $exec);
    }

    /**
     * Creates a tensor initialized with uniform pseudo-random values.
     */
    public static function random(int $rows, int $cols, float $min = -1.0, float $max = 1.0, ?HardwareExecutor $executor = null): self
    {
        $exec = $executor ?? new HardwareExecutor();
        $tensor = new self($rows, $cols, null, $exec);
        $range = $max - $min;

        for ($i = 0; $i < $tensor->size; $i++) {
            $tensor->buffer[$i] = $min + ((float)mt_rand() / (float)mt_getrandmax()) * $range;
        }

        return $tensor;
    }

    /**
     * Returns shape as [rows, cols].
     * 
     * @return array{0: int, 1: int}
     */
    public function shape(): array
    {
        return [$this->rows, $this->cols];
    }

    public function rows(): int
    {
        return $this->rows;
    }

    public function cols(): int
    {
        return $this->cols;
    }

    public function size(): int
    {
        return $this->size;
    }

    public function getBuffer(): FFI\CData
    {
        return $this->buffer;
    }

    public function get(int $row, int $col): float
    {
        if ($row < 0 || $row >= $this->rows || $col < 0 || $col >= $this->cols) {
            throw new InvalidArgumentException("Index out of bounds: ({$row}, {$col}) for tensor shape {$this->rows}x{$this->cols}");
        }
        return (float)$this->buffer[$row * $this->cols + $col];
    }

    public function set(int $row, int $col, float $value): void
    {
        if ($row < 0 || $row >= $this->rows || $col < 0 || $col >= $this->cols) {
            throw new InvalidArgumentException("Index out of bounds: ({$row}, {$col}) for tensor shape {$this->rows}x{$this->cols}");
        }
        $this->buffer[$row * $this->cols + $col] = $value;
    }

    /**
     * Performs bare-metal hardware matrix multiplication: C = A x B.
     * Dimensions: A is (M x K), B is (K x N), result is (M x N).
     */
    public function matmul(self $other): self
    {
        if ($this->cols !== $other->rows) {
            throw new RuntimeException("Matrix multiplication dimension mismatch: ({$this->rows}x{$this->cols}) x ({$other->rows}x{$other->cols})");
        }

        $M = $this->rows;
        $K = $this->cols;
        $N = $other->cols;

        /* Allocate output buffer */
        $outBuffer = $this->executor->newFloatBuffer($M * $N);

        /* Execute AVX-512 / AVX2 hardware GEMM in pure C */
        $this->executor->tensorMatmul($this->buffer, $other->buffer, $outBuffer, $M, $K, $N);

        return new self($M, $N, $outBuffer, $this->executor);
    }

    /**
     * Performs element-wise addition: C = A + B.
     */
    public function add(self $other, bool $inPlace = false): self
    {
        if ($this->rows !== $other->rows || $this->cols !== $other->cols) {
            throw new RuntimeException("Dimension mismatch for addition: ({$this->rows}x{$this->cols}) vs ({$other->rows}x{$other->cols})");
        }

        $target = $inPlace ? $this : $this->copy();
        for ($i = 0; $i < $this->size; $i++) {
            $target->buffer[$i] += $other->buffer[$i];
        }

        return $target;
    }

    /**
     * Multiplies every element by a scalar.
     */
    public function scale(float $scalar, bool $inPlace = false): self
    {
        $target = $inPlace ? $this : $this->copy();
        for ($i = 0; $i < $this->size; $i++) {
            $target->buffer[$i] *= $scalar;
        }

        return $target;
    }

    /**
     * Computes vectorized Rectified Linear Unit (ReLU): f(x) = max(0, x).
     */
    public function relu(bool $inPlace = false): self
    {
        $target = $inPlace ? $this : $this->copy();
        $this->executor->tensorActivate($target->buffer, $target->size, 0);
        return $target;
    }

    /**
     * Computes Gaussian Error Linear Unit (GELU) used in LLM Transformers.
     * f(x) = 0.5 * x * (1 + tanh(sqrt(2/pi) * (x + 0.044715 * x^3)))
     */
    public function gelu(bool $inPlace = false): self
    {
        $target = $inPlace ? $this : $this->copy();
        $this->executor->tensorActivate($target->buffer, $target->size, 1);
        return $target;
    }

    /**
     * Computes row-wise Softmax activation across columns (e.g. self-attention scores).
     * f(x_i) = exp(x_i - max(x)) / sum(exp(x_j - max(x)))
     */
    public function softmax(bool $inPlace = false): self
    {
        $target = $inPlace ? $this : $this->copy();

        for ($r = 0; $r < $target->rows; $r++) {
            $rowOffset = $r * $target->cols;
            /* WHY: Obtain direct C float pointer to row beginning */
            $rowPtr = FFI::addr($target->buffer[$rowOffset]);
            $this->executor->tensorActivate($rowPtr, $target->cols, 2);
        }

        return $target;
    }

    /**
     * Deep clones tensor data into a fresh native C buffer.
     */
    public function copy(): self
    {
        $newBuffer = $this->executor->newFloatBuffer($this->size);
        for ($i = 0; $i < $this->size; $i++) {
            $newBuffer[$i] = (float)$this->buffer[$i];
        }
        return new self($this->rows, $this->cols, $newBuffer, $this->executor);
    }

    /**
     * Converts native C buffer into standard nested PHP array.
     * 
     * @return array<int, array<int, float>>
     */
    public function toArray(): array
    {
        $res = [];
        $idx = 0;
        for ($r = 0; $r < $this->rows; $r++) {
            $row = [];
            for ($c = 0; $c < $this->cols; $c++) {
                $row[] = (float)$this->buffer[$idx++];
            }
            $res[] = $row;
        }
        return $res;
    }
}
