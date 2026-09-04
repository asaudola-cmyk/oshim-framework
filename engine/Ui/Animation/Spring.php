<?php
declare(strict_types=1);

namespace Oshim\Ui\Animation;

use JsonSerializable;

/**
 * Server-Driven Spring Physics Oscillator.
 *
 * Implements an exact analytical solution to the damped harmonic oscillator
 * differential equation: m*x''(t) + c*x'(t) + k*x(t) = 0.
 *
 * Supports underdamped (bouncy), critically damped (fastest settling without oscillation),
 * and overdamped (smooth slow decay) regimes.
 */
class Spring implements JsonSerializable
{
    protected float $stiffness;
    protected float $damping;
    protected float $mass;
    protected float $initialVelocity;
    protected float $restDelta;
    protected float $restSpeed;
    protected float $delay;

    public function __construct(
        float $stiffness = 100.0,
        float $damping = 10.0,
        float $mass = 1.0,
        float $initialVelocity = 0.0,
        float $restDelta = 0.001,
        float $restSpeed = 0.001,
        float $delay = 0.0
    ) {
        $this->stiffness = max(0.0001, $stiffness);
        $this->damping = max(0.0, $damping);
        $this->mass = max(0.0001, $mass);
        $this->initialVelocity = $initialVelocity;
        $this->restDelta = max(0.00001, $restDelta);
        $this->restSpeed = max(0.00001, $restSpeed);
        $this->delay = max(0.0, $delay);
    }

    public static function make(float $stiffness = 100.0, float $damping = 10.0, float $mass = 1.0): self
    {
        return new self($stiffness, $damping, $mass);
    }

    /**
     * Default balanced spring.
     */
    public static function default(): self
    {
        return new self(100.0, 10.0, 1.0);
    }

    /**
     * Gentle spring with smooth settling and minimal bounce.
     */
    public static function gentle(): self
    {
        return new self(120.0, 14.0, 1.0);
    }

    /**
     * Fun, wobbly spring with multiple energetic oscillations.
     */
    public static function wobbly(): self
    {
        return new self(180.0, 12.0, 1.0);
    }

    /**
     * High-energy bouncy spring (Framer Motion equivalent).
     */
    public static function bouncy(): self
    {
        return new self(300.0, 10.0, 1.0);
    }

    /**
     * Crisp, stiff spring for sharp UI responsiveness.
     */
    public static function stiff(): self
    {
        return new self(250.0, 25.0, 1.0);
    }

    /**
     * Snappy spring for quick clicks and micro-interactions.
     */
    public static function snappy(): self
    {
        return new self(400.0, 30.0, 0.8);
    }

    /**
     * Slow, cinematic spring for ambient and card entrances.
     */
    public static function slow(): self
    {
        return new self(60.0, 15.0, 1.2);
    }

    public function getStiffness(): float
    {
        return $this->stiffness;
    }

    public function getDamping(): float
    {
        return $this->damping;
    }

    public function getMass(): float
    {
        return $this->mass;
    }

    public function getInitialVelocity(): float
    {
        return $this->initialVelocity;
    }

    public function getRestDelta(): float
    {
        return $this->restDelta;
    }

    public function getRestSpeed(): float
    {
        return $this->restSpeed;
    }

    public function getDelay(): float
    {
        return $this->delay;
    }

    public function withStiffness(float $stiffness): self
    {
        $clone = clone $this;
        $clone->stiffness = max(0.0001, $stiffness);
        return $clone;
    }

    public function withDamping(float $damping): self
    {
        $clone = clone $this;
        $clone->damping = max(0.0, $damping);
        return $clone;
    }

    public function withMass(float $mass): self
    {
        $clone = clone $this;
        $clone->mass = max(0.0001, $mass);
        return $clone;
    }

    public function withVelocity(float $initialVelocity): self
    {
        $clone = clone $this;
        $clone->initialVelocity = $initialVelocity;
        return $clone;
    }

    public function withRestDelta(float $restDelta): self
    {
        $clone = clone $this;
        $clone->restDelta = max(0.00001, $restDelta);
        return $clone;
    }

    public function withRestSpeed(float $restSpeed): self
    {
        $clone = clone $this;
        $clone->restSpeed = max(0.00001, $restSpeed);
        return $clone;
    }

    public function withDelay(float $delay): self
    {
        $clone = clone $this;
        $clone->delay = max(0.0, $delay);
        return $clone;
    }

    /**
     * Undamped natural angular frequency: omega_0 = sqrt(k / m).
     */
    public function getNaturalFrequency(): float
    {
        return sqrt($this->stiffness / $this->mass);
    }

    /**
     * Damping ratio: zeta = c / (2 * sqrt(m * k)).
     */
    public function getDampingRatio(): float
    {
        return $this->damping / (2.0 * sqrt($this->mass * $this->stiffness));
    }

    public function isUnderdamped(): bool
    {
        return $this->getDampingRatio() < 0.9999;
    }

    public function isCriticallyDamped(): bool
    {
        $ratio = $this->getDampingRatio();
        return abs($ratio - 1.0) <= 0.0001;
    }

    public function isOverdamped(): bool
    {
        return $this->getDampingRatio() > 1.0001;
    }

    /**
     * Analytical solution for position x(t) moving from $from to $to.
     *
     * @param float $t Elapsed time in seconds (relative to start after delay).
     * @param float $from Initial value at t = 0.
     * @param float $to Target resting value.
     * @return float Current position at time $t.
     */
    public function solve(float $t, float $from = 0.0, float $to = 1.0): float
    {
        if ($t <= 0.0) {
            return $from;
        }

        $x0 = $from - $to;
        $v0 = $this->initialVelocity;
        $w0 = $this->getNaturalFrequency();
        $zeta = $this->getDampingRatio();

        if ($zeta < 0.9999) {
            // Underdamped regime: zeta < 1
            $wd = $w0 * sqrt(1.0 - ($zeta * $zeta));
            $envelope = exp(-$zeta * $w0 * $t);
            $A = $x0;
            $B = ($v0 + ($zeta * $w0 * $x0)) / $wd;
            $displacement = $envelope * (($A * cos($wd * $t)) + ($B * sin($wd * $t)));
            return $to + $displacement;
        }

        if ($zeta > 1.0001) {
            // Overdamped regime: zeta > 1
            $wd = $w0 * sqrt(($zeta * $zeta) - 1.0);
            $envelope = exp(-$zeta * $w0 * $t);
            $A = $x0;
            $B = ($v0 + ($zeta * $w0 * $x0)) / $wd;
            $displacement = $envelope * (($A * cosh($wd * $t)) + ($B * sinh($wd * $t)));
            return $to + $displacement;
        }

        // Critically damped regime: zeta == 1
        $envelope = exp(-$w0 * $t);
        $A = $x0;
        $B = $v0 + ($w0 * $x0);
        $displacement = $envelope * ($A + ($B * $t));
        return $to + $displacement;
    }

    /**
     * First derivative (instantaneous velocity) v(t) = dx/dt.
     */
    public function velocity(float $t, float $from = 0.0, float $to = 1.0): float
    {
        if ($t <= 0.0) {
            return $this->initialVelocity;
        }

        $x0 = $from - $to;
        $v0 = $this->initialVelocity;
        $w0 = $this->getNaturalFrequency();
        $zeta = $this->getDampingRatio();
        $alpha = $zeta * $w0;

        if ($zeta < 0.9999) {
            // Underdamped derivative
            $wd = $w0 * sqrt(1.0 - ($zeta * $zeta));
            $envelope = exp(-$alpha * $t);
            $A = $x0;
            $B = ($v0 + ($alpha * $x0)) / $wd;
            $cosVal = cos($wd * $t);
            $sinVal = sin($wd * $t);

            return $envelope * ((($B * $wd) - ($A * $alpha)) * $cosVal - (($A * $wd) + ($B * $alpha)) * $sinVal);
        }

        if ($zeta > 1.0001) {
            // Overdamped derivative
            $wd = $w0 * sqrt(($zeta * $zeta) - 1.0);
            $envelope = exp(-$alpha * $t);
            $A = $x0;
            $B = ($v0 + ($alpha * $x0)) / $wd;
            $coshVal = cosh($wd * $t);
            $sinhVal = sinh($wd * $t);

            return $envelope * ((($B * $wd) - ($A * $alpha)) * $coshVal + (($A * $wd) - ($B * $alpha)) * $sinhVal);
        }

        // Critically damped derivative
        $envelope = exp(-$w0 * $t);
        $A = $x0;
        $B = $v0 + ($w0 * $x0);
        return $envelope * ($B - ($w0 * ($A + ($B * $t))));
    }

    /**
     * Calculates the settling duration in seconds when the spring comes to rest
     * within restDelta and restSpeed tolerances.
     */
    public function settlingDuration(float $from = 0.0, float $to = 1.0, float $maxDuration = 10.0): float
    {
        $step = 0.005; // 5ms precision
        $duration = 0.0;
        $lastActiveTime = 0.0;

        for ($t = 0.0; $t <= $maxDuration; $t += $step) {
            $val = $this->solve($t, $from, $to);
            $vel = $this->velocity($t, $from, $to);

            $posDiff = abs($val - $to);
            $velDiff = abs($vel);

            if ($posDiff > $this->restDelta || $velDiff > $this->restSpeed) {
                $lastActiveTime = $t;
            }
        }

        $duration = round($lastActiveTime + ($step * 2), 3);
        return max(0.05, min($maxDuration, $duration));
    }

    /**
     * Sample points along the spring trajectory from 0 to settling duration.
     *
     * @return list<array{t: float, progress: float, value: float, velocity: float}>
     */
    public function sample(int $sampleCount = 50, float $from = 0.0, float $to = 1.0): array
    {
        $sampleCount = max(2, $sampleCount);
        $duration = $this->settlingDuration($from, $to);
        $samples = [];

        for ($i = 0; $i < $sampleCount; $i++) {
            $progress = $i / ($sampleCount - 1);
            $t = $progress * $duration;
            $value = $this->solve($t, $from, $to);
            $vel = $this->velocity($t, $from, $to);

            $samples[] = [
                't' => round($t, 4),
                'progress' => round($progress, 4),
                'value' => round($value, 5),
                'velocity' => round($vel, 5),
            ];
        }

        return $samples;
    }

    /**
     * Compile spring trajectory into modern CSS linear() easing function.
     * Compatible with modern browsers (Chrome 113+, Safari 17.2+, Firefox 112+).
     *
     * Example: linear(0, 0.12 10%, 0.5 40%, 1.05 75%, 1)
     */
    public function toCssLinear(int $sampleCount = 30): string
    {
        $samples = $this->sample($sampleCount, 0.0, 1.0);
        $parts = [];

        foreach ($samples as $i => $s) {
            $val = round($s['value'], 4);
            $pct = round($s['progress'] * 100, 1);

            if ($i === 0 || $i === count($samples) - 1) {
                $parts[] = (string)$val;
            } else {
                $parts[] = sprintf('%.4f %g%%', $val, $pct);
            }
        }

        return 'linear(' . implode(', ', $parts) . ')';
    }

    /**
     * Generate pure CSS @keyframes block from spring trajectory.
     */
    public function toCssKeyframes(
        string $name,
        string $property,
        float $from,
        float $to,
        string $unit = 'px',
        int $steps = 25
    ): string {
        $samples = $this->sample($steps, $from, $to);
        $cssLines = ["@keyframes {$name} {"];

        foreach ($samples as $s) {
            $pct = round($s['progress'] * 100, 1);
            $val = round($s['value'], 3);

            if ($property === 'opacity' || $property === 'scale') {
                $cssLines[] = sprintf("  %g%% { %s: %.3f; }", $pct, $property, $val);
            } elseif ($property === 'transform') {
                $cssLines[] = sprintf("  %g%% { transform: translateY(%.2f%s); }", $pct, $val, $unit);
            } else {
                $cssLines[] = sprintf("  %g%% { %s: %.2f%s; }", $pct, $property, $val, $unit);
            }
        }

        $cssLines[] = "}";
        return implode("\n", $cssLines);
    }

    public function toArray(): array
    {
        return [
            'stiffness' => $this->stiffness,
            'damping' => $this->damping,
            'mass' => $this->mass,
            'initialVelocity' => $this->initialVelocity,
            'restDelta' => $this->restDelta,
            'restSpeed' => $this->restSpeed,
            'delay' => $this->delay,
            'naturalFrequency' => round($this->getNaturalFrequency(), 4),
            'dampingRatio' => round($this->getDampingRatio(), 4),
            'settlingDuration' => $this->settlingDuration(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
