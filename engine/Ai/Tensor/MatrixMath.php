<?php
declare(strict_types=1);

namespace Oshim\Ai\Tensor;

class MatrixMath
{
    public static function dotProduct(array $vecA, array $vecB): float
    {
        $vecA = array_values($vecA);
        $vecB = array_values($vecB);
        $len = min(count($vecA), count($vecB));
        $sum = 0.0;
        for ($i = 0; $i < $len; $i++) {
            $sum += ((float)$vecA[$i] * (float)$vecB[$i]);
        }
        return $sum;
    }

    /**
     * Alias for dotProduct (required by VectorStore METRIC_DOT).
     */
    public static function dot(array $vecA, array $vecB): float
    {
        return self::dotProduct($vecA, $vecB);
    }

    public static function vectorMagnitude(array $vec): float
    {
        $sumSq = 0.0;
        foreach ($vec as $val) {
            $sumSq += ((float)$val * (float)$val);
        }
        return sqrt($sumSq);
    }

    /**
     * L2 Unit normalize a vector.
     *
     * @param array<float|int> $vec
     * @return array<float>
     */
    public static function l2Normalize(array $vec): array
    {
        $mag = self::vectorMagnitude($vec);
        if ($mag == 0.0) {
            return $vec;
        }
        return array_map(fn($v) => (float)$v / $mag, $vec);
    }

    public static function cosineSimilarity(array $vecA, array $vecB): float
    {
        $dot = self::dotProduct($vecA, $vecB);
        $magA = self::vectorMagnitude($vecA);
        $magB = self::vectorMagnitude($vecB);

        if ($magA == 0.0 || $magB == 0.0) {
            return 0.0;
        }

        return $dot / ($magA * $magB);
    }

    public static function softmax(array $logits): array
    {
        if (empty($logits)) {
            return [];
        }

        $maxVal = max($logits);
        $expSum = 0.0;
        $exps = [];

        foreach ($logits as $v) {
            $e = exp((float)$v - (float)$maxVal);
            $exps[] = $e;
            $expSum += $e;
        }

        $probs = [];
        foreach ($exps as $e) {
            $probs[] = ($expSum > 0.0) ? ($e / $expSum) : 0.0;
        }

        return $probs;
    }

    public static function matrixMultiply(array $matrixA, array $matrixB): array
    {
        $rowsA = count($matrixA);
        $colsA = count($matrixA[0]);
        $rowsB = count($matrixB);
        $colsB = count($matrixB[0]);

        if ($colsA !== $rowsB) {
            throw new \InvalidArgumentException("Matrix dimension mismatch: {$colsA} != {$rowsB}");
        }

        $result = [];
        for ($i = 0; $i < $rowsA; $i++) {
            $result[$i] = [];
            for ($j = 0; $j < $colsB; $j++) {
                $sum = 0.0;
                for ($k = 0; $k < $colsA; $k++) {
                    $sum += ($matrixA[$i][$k] * $matrixB[$k][$j]);
                }
                $result[$i][$j] = $sum;
            }
        }

        return $result;
    }
}
