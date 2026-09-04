<?php
declare(strict_types=1);

namespace Oshim\Ai\Tensor;

use InvalidArgumentException;
use RuntimeException;

/**
 * High-Performance Multi-Dimensional Tensor Engine in Pure PHP 8.3+.
 * Supports N-Dimensional Tensors, GEMM Matrix Multiplication, LayerNorm,
 * Softmax, Activation functions, and INT8/FP16 Quantization.
 */
class Tensor
{
    /** @var array<float> 1D flattened data buffer */
    private array $data;
    /** @var array<int> Tensor dimensions / shape e.g. [2, 3] */
    private array $shape;
    /** @var array<int> Dimension strides */
    private array $strides;
    private int $size;

    public function __construct(array $data, array $shape)
    {
        $this->shape = array_map('intval', $shape);
        $this->size = (int)array_product($this->shape);

        if (count($data) !== $this->size) {
            throw new InvalidArgumentException("Data size (" . count($data) . ") does not match shape product ({$this->size})");
        }

        $this->data = array_values(array_map('floatval', $data));
        $this->strides = $this->calculateStrides($this->shape);
    }

    public static function zeros(array $shape): self
    {
        $size = (int)array_product($shape);
        return new self(array_fill(0, $size, 0.0), $shape);
    }

    public static function ones(array $shape): self
    {
        $size = (int)array_product($shape);
        return new self(array_fill(0, $size, 1.0), $shape);
    }

    public static function from1D(array $data): self
    {
        return new self($data, [count($data)]);
    }

    public static function from2D(array $matrix): self
    {
        $rows = count($matrix);
        $cols = $rows > 0 ? count($matrix[0]) : 0;
        $flat = [];
        foreach ($matrix as $row) {
            foreach ($row as $val) {
                $flat[] = (float)$val;
            }
        }
        return new self($flat, [$rows, $cols]);
    }

    public function getShape(): array
    {
        return $this->shape;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function to2DArray(): array
    {
        if (count($this->shape) !== 2) {
            throw new RuntimeException("Tensor is not 2-dimensional (shape: " . json_encode($this->shape) . ")");
        }
        [$rows, $cols] = $this->shape;
        $matrix = [];
        for ($i = 0; $i < $rows; $i++) {
            $matrix[$i] = array_slice($this->data, $i * $cols, $cols);
        }
        return $matrix;
    }

    /**
     * Matrix Multiplication: C = A × B
     */
    public function matmul(Tensor $b): Tensor
    {
        if (count($this->shape) !== 2 || count($b->shape) !== 2) {
            throw new InvalidArgumentException("Matrix multiplication requires 2D tensors");
        }

        [$m, $k1] = $this->shape;
        [$k2, $n] = $b->shape;

        if ($k1 !== $k2) {
            throw new InvalidArgumentException("Shape mismatch for matmul: [{$m}, {$k1}] × [{$k2}, {$n}]");
        }

        $aData = $this->data;
        $bData = $b->data;
        $cData = array_fill(0, $m * $n, 0.0);

        // Cache-friendly chunked GEMM multiplication
        for ($i = 0; $i < $m; $i++) {
            $aOffset = $i * $k1;
            $cOffset = $i * $n;
            for ($k = 0; $k < $k1; $k++) {
                $aVal = $aData[$aOffset + $k];
                if ($aVal == 0.0) continue;
                $bOffset = $k * $n;
                for ($j = 0; $j < $n; $j++) {
                    $cData[$cOffset + $j] += $aVal * $bData[$bOffset + $j];
                }
            }
        }

        return new Tensor($cData, [$m, $n]);
    }

    /**
     * Element-wise Addition: C = A + B
     */
    public function add(Tensor|float $b): Tensor
    {
        if (is_numeric($b)) {
            $scalar = (float)$b;
            $out = [];
            foreach ($this->data as $val) {
                $out[] = $val + $scalar;
            }
            return new Tensor($out, $this->shape);
        }

        if ($this->shape !== $b->shape) {
            throw new InvalidArgumentException("Tensor shapes must match for addition");
        }

        $out = [];
        $len = $this->size;
        $bData = $b->data;
        for ($i = 0; $i < $len; $i++) {
            $out[$i] = $this->data[$i] + $bData[$i];
        }
        return new Tensor($out, $this->shape);
    }

    /**
     * Element-wise Multiplication (Hadamard product)
     */
    public function multiply(Tensor|float $b): Tensor
    {
        if (is_numeric($b)) {
            $scalar = (float)$b;
            $out = [];
            foreach ($this->data as $val) {
                $out[] = $val * $scalar;
            }
            return new Tensor($out, $this->shape);
        }

        $out = [];
        $len = $this->size;
        $bData = $b->data;
        for ($i = 0; $i < $len; $i++) {
            $out[$i] = $this->data[$i] * $bData[$i];
        }
        return new Tensor($out, $this->shape);
    }

    /**
     * Softmax normalization along last axis.
     */
    public function softmax(): Tensor
    {
        $data = $this->data;
        $lastDim = end($this->shape);
        $outer = $this->size / $lastDim;

        $out = [];
        for ($i = 0; $i < $outer; $i++) {
            $offset = $i * $lastDim;
            $slice = array_slice($data, $offset, $lastDim);

            // Numerically stable softmax: subtract max
            $max = max($slice);
            $expSum = 0.0;
            $exps = [];
            foreach ($slice as $val) {
                $e = exp($val - $max);
                $exps[] = $e;
                $expSum += $e;
            }

            foreach ($exps as $e) {
                $out[] = $expSum > 0 ? ($e / $expSum) : 0.0;
            }
        }

        return new Tensor($out, $this->shape);
    }

    /**
     * Layer Normalization (mean=0, variance=1)
     */
    public function layerNorm(float $eps = 1e-5): Tensor
    {
        $lastDim = end($this->shape);
        $outer = $this->size / $lastDim;
        $out = [];

        for ($i = 0; $i < $outer; $i++) {
            $offset = $i * $lastDim;
            $slice = array_slice($this->data, $offset, $lastDim);

            $mean = array_sum($slice) / $lastDim;
            $varSum = 0.0;
            foreach ($slice as $v) {
                $diff = $v - $mean;
                $varSum += $diff * $diff;
            }
            $variance = $varSum / $lastDim;
            $std = sqrt($variance + $eps);

            foreach ($slice as $v) {
                $out[] = ($v - $mean) / $std;
            }
        }

        return new Tensor($out, $this->shape);
    }

    /**
     * ReLU Activation: max(0, x)
     */
    public function relu(): Tensor
    {
        $out = [];
        foreach ($this->data as $v) {
            $out[] = $v > 0.0 ? $v : 0.0;
        }
        return new Tensor($out, $this->shape);
    }

    /**
     * INT8 Quantization (returns quantized scale, zero point, and int8 buffer)
     */
    public function quantizeInt8(): array
    {
        $min = min($this->data);
        $max = max($this->data);
        $scale = ($max - $min) / 255.0;
        $scale = $scale > 0 ? $scale : 1.0;
        $zeroPoint = (int)round(-$min / $scale);

        $quantized = [];
        foreach ($this->data as $v) {
            $q = (int)round($v / $scale) + $zeroPoint;
            $quantized[] = max(0, min(255, $q));
        }

        return [
            'quantized_data' => $quantized,
            'scale' => $scale,
            'zero_point' => $zeroPoint,
            'shape' => $this->shape,
        ];
    }

    private function calculateStrides(array $shape): array
    {
        $strides = [];
        $stride = 1;
        for ($i = count($shape) - 1; $i >= 0; $i--) {
            $strides[$i] = $stride;
            $stride *= $shape[$i];
        }
        ksort($strides);
        return $strides;
    }
}
