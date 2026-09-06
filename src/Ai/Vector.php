<?php
declare(strict_types=1);

namespace Oshim\Ai;

use RuntimeException;

/**
 * 🧠 Sovereign SIMD-Accelerated Neural Embedding Vector
 * 
 * WHY: Represents high-dimensional AI vectors (e.g. 768D BERT, 1536D OpenAI embeddings)
 * as raw 32-bit float binary buffers. Delegates similarity calculations directly to
 * hardware AVX2 / AVX-512 vector execution units in the C kernel.
 */
final class Vector
{
    private string $binary;
    private int $dimensions;

    public function __construct(string $binary, int $dimensions)
    {
        $expectedBytes = $dimensions * 4;
        if (strlen($binary) !== $expectedBytes) {
            throw new RuntimeException("Binary buffer size (" . strlen($binary) . ") does not match dimensions ({$dimensions} * 4 = {$expectedBytes} bytes)");
        }
        $this->binary = $binary;
        $this->dimensions = $dimensions;
    }

    /**
     * Instantiates a Vector from an array of PHP floats.
     * 
     * @param float[] $floats
     */
    public static function fromArray(array $floats): self
    {
        $binary = pack('f*', ...$floats);
        return new self($binary, count($floats));
    }

    /**
     * Instantiates a random normalized vector for testing/benchmarking.
     */
    public static function random(int $dimensions): self
    {
        $floats = [];
        $sumSq = 0.0;
        for ($i = 0; $i < $dimensions; $i++) {
            $val = (mt_rand(-1000, 1000)) / 1000.0;
            $floats[] = $val;
            $sumSq += $val * $val;
        }
        $norm = sqrt($sumSq) ?: 1.0;
        for ($i = 0; $i < $dimensions; $i++) {
            $floats[$i] /= $norm;
        }

        return self::fromArray($floats);
    }

    /**
     * Calculates dot product using AVX-512 / AVX2 hardware SIMD.
     */
    public function dot(Vector $other): float
    {
        $this->assertMatchingDimensions($other);
        return oshim_simd_dot($this->binary, $other->binary);
    }

    /**
     * Calculates cosine similarity (-1.0 to 1.0) using AVX-512 / AVX2 hardware SIMD.
     */
    public function cosineSimilarity(Vector $other): float
    {
        $this->assertMatchingDimensions($other);
        return oshim_simd_cosine($this->binary, $other->binary);
    }

    /**
     * Calculates Euclidean distance using AVX-512 / AVX2 hardware SIMD.
     */
    public function euclideanDistance(Vector $other): float
    {
        $this->assertMatchingDimensions($other);
        return oshim_simd_euclidean($this->binary, $other->binary);
    }

    private function assertMatchingDimensions(Vector $other): void
    {
        if ($this->dimensions !== $other->dimensions) {
            throw new RuntimeException("Dimension mismatch: {$this->dimensions} != {$other->dimensions}");
        }
    }

    public function getBinary(): string
    {
        return $this->binary;
    }

    public function getDimensions(): int
    {
        return $this->dimensions;
    }

    /**
     * Unpacks back to PHP float array.
     * 
     * @return float[]
     */
    public function toArray(): array
    {
        return array_values(unpack('f*', $this->binary));
    }
}
