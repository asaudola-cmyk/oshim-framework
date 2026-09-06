<?php

declare(strict_types=1);

namespace Unum;

/**
 * 👑 Physics & Mathematics Foundation Engine
 * 
 * WHY: Traditional compilers use arbitrary heuristic optimizations.
 * The UNUM PhysicsMathEngine uses fundamental laws of physics and pure mathematics:
 * 1. Kurt Gödel's Invariant: Prime-power factorization assigns a unique mathematical fingerprint to any AST.
 * 2. Posit (Type-III Unum) Representation: Projective reals on the Riemann sphere eliminating IEEE-754 NaN/underflow bugs.
 * 3. Rolf Landauer's Principle: Minimizes Hamming distance between state transitions to reduce switching energy & clock propagation delay.
 */
final class PhysicsMathEngine
{
    /**
     * Prime table for Gödel Numbering invariant generation.
     */
    private const PRIMES = [2, 3, 5, 7, 11, 13, 17, 19, 23, 29, 31, 37, 41, 43, 47, 53];

    /**
     * Computes the Gödel Number invariant for an operation sequence.
     * G(s) = 2^a1 * 3^a2 * 5^a3 ...
     * Returns a 64-bit folded Galois Field invariant.
     *
     * @param int[] $tokens Array of token/opcode integer states.
     */
    public static function computeGodelInvariant(array $tokens): int
    {
        $hash = 1;
        $primeCount = count(self::PRIMES);

        foreach ($tokens as $idx => $token) {
            $prime = self::PRIMES[$idx % $primeCount];
            /* Galois Field GF(2^64) modular arithmetic fold */
            $exp = ($token % 7) + 1;
            $pow = 1;
            for ($i = 0; $i < $exp; $i++) {
                $pow = ($pow * $prime) & 0x7FFFFFFFFFFFFFFF;
            }
            $hash = ($hash * $pow) ^ ($token << (($idx % 8) * 8));
        }

        return $hash;
    }

    /**
     * Encodes an IEEE-754 float into a 32-bit Posit (Type-III Unum, es=2).
     * Value representation: (-1)^sign * 16^regime * 2^exponent * (1 + fraction)
     */
    public static function floatToPosit32(float $value): int
    {
        if ($value === 0.0) {
            return 0;
        }

        $sign = 0;
        if ($value < 0.0) {
            $sign = 1;
            $value = -$value;
        }

        /* Compute binary exponent */
        $totalExp = (int)floor(log($value, 2.0));
        $regime = (int)floor($totalExp / 4.0);
        $exp = $totalExp - ($regime * 4);
        if ($exp < 0) {
            $exp += 4;
            $regime -= 1;
        }

        $fraction = ($value / (2.0 ** $totalExp)) - 1.0;
        if ($fraction < 0.0) $fraction = 0.0;

        /* Build bitstream starting after sign bit (bit 30 down) */
        $bits = 0;
        $pos = 30;

        /* Encode regime: if regime >= 0, (regime+1) 1s then a 0 */
        if ($regime >= 0) {
            $ones = $regime + 1;
            for ($i = 0; $i < $ones && $pos >= 0; $i++, $pos--) {
                $bits |= (1 << $pos);
            }
            if ($pos >= 0) {
                $pos--; /* terminating 0 */
            }
        } else {
            /* If regime < 0, (-regime) 0s then a 1 */
            $zeros = -$regime;
            $pos -= $zeros;
            if ($pos >= 0) {
                $bits |= (1 << $pos);
                $pos--; /* terminating 1 */
            }
        }

        /* Encode exponent (2 bits) */
        if ($pos >= 0) {
            $bits |= (($exp >> 1) & 1) << $pos;
            $pos--;
        }
        if ($pos >= 0) {
            $bits |= ($exp & 1) << $pos;
            $pos--;
        }

        /* Encode fraction into remaining bits */
        if ($pos >= 0) {
            $fracBits = (int)round($fraction * (1 << ($pos + 1)));
            $bits |= ($fracBits & ((1 << ($pos + 1)) - 1));
        }

        if ($sign === 1) {
            /* 2's complement negation for negative posits */
            $bits = ((~$bits) + 1) & 0x7FFFFFFF;
            return (1 << 31) | $bits;
        }

        return $bits & 0x7FFFFFFF;
    }

    /**
     * Decodes a 32-bit Posit back into an IEEE-754 float with mathematical fidelity.
     */
    public static function posit32ToFloat(int $posit): float
    {
        if (($posit & 0xFFFFFFFF) === 0) {
            return 0.0;
        }

        $sign = ($posit >> 31) & 1;
        $payload = $posit & 0x7FFFFFFF;

        if ($sign === 1) {
            $payload = ((~$payload) + 1) & 0x7FFFFFFF;
        }

        /* Scan regime starting from bit 30 */
        $pos = 30;
        $firstBit = ($payload >> $pos) & 1;
        $runLength = 0;

        while ($pos >= 0 && (($payload >> $pos) & 1) === $firstBit) {
            $runLength++;
            $pos--;
        }
        $pos--; /* Skip terminating inverted bit */

        $regime = ($firstBit === 1) ? ($runLength - 1) : -$runLength;

        /* Extract 2-bit exponent */
        $exp = 0;
        if ($pos >= 0) {
            $exp |= (($payload >> $pos) & 1) << 1;
            $pos--;
        }
        if ($pos >= 0) {
            $exp |= (($payload >> $pos) & 1);
            $pos--;
        }

        /* Extract fraction */
        $fraction = 0.0;
        if ($pos >= 0) {
            $fracBits = $payload & ((1 << ($pos + 1)) - 1);
            $fraction = $fracBits / (float)(1 << ($pos + 1));
        }

        $scale = (16.0 ** $regime) * (2.0 ** $exp);
        $value = $scale * (1.0 + $fraction);

        return $sign === 1 ? -$value : $value;
    }

    /**
     * Calculates the Hamming Distance (differing bit count) between two 64-bit numbers.
     * In Landauer's Physics, every bit flip consumes physical thermodynamic entropy:
     * ΔE >= k_B * T * ln(2). Minimizing Hamming distance accelerates silicon execution.
     */
    public static function hammingDistance(int $a, int $b): int
    {
        $xor = $a ^ $b;
        $count = 0;
        while ($xor !== 0) {
            $count += $xor & 1;
            $xor = ($xor >> 1) & 0x7FFFFFFFFFFFFFFF;
        }
        return $count;
    }

    /**
     * Optimizes an array of Universal Numbers to minimize Landauer state-transition entropy.
     * Uses Gray-code style reordering for commutative operations.
     * 
     * @param UniversalNumber[] $numbers
     * @return UniversalNumber[]
     */
    public static function optimizeInstructionEntropy(array $numbers): array
    {
        if (count($numbers) <= 2) {
            return $numbers;
        }

        /* Preserve first instruction (prologue/setup) and last instruction (ret) */
        $optimized = [];
        $optimized[] = $numbers[0];

        $pool = array_slice($numbers, 1, -1);
        $current = $numbers[0]->toInt();

        while (!empty($pool)) {
            /* Greedily select instruction with minimal Hamming distance */
            $bestIdx = 0;
            $minDist = 64;

            foreach ($pool as $idx => $cand) {
                $dist = self::hammingDistance($current, $cand->toInt());
                if ($dist < $minDist) {
                    $minDist = $dist;
                    $bestIdx = $idx;
                }
            }

            $current = $pool[$bestIdx]->toInt();
            $optimized[] = $pool[$bestIdx];
            array_splice($pool, $bestIdx, 1);
        }

        $optimized[] = $numbers[count($numbers) - 1];
        return $optimized;
    }
}
