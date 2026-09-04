<?php
declare(strict_types=1);

namespace Oshim\Ui\Animation;

use JsonSerializable;

/**
 * Declarative Multi-Step Keyframe Generator.
 *
 * Supports discrete percentage steps, numeric property interpolation,
 * spring-driven keyframe synthesis, and CSS @keyframes generation.
 */
class Keyframes implements JsonSerializable
{
    protected string $name;
    /** @var array<string, array<string, mixed>> */
    protected array $steps = [];
    protected float $duration = 1.0;
    protected float $delay = 0.0;
    protected int|string $iterations = 1;
    protected string $direction = 'normal';
    protected string $fillMode = 'forwards';
    protected string $easing = 'ease';

    public function __construct(string $name, array $steps = [])
    {
        $this->name = $name;
        foreach ($steps as $stop => $props) {
            $this->addStep($stop, (array)$props);
        }
    }

    public static function make(string $name = 'oshim_keyframes'): self
    {
        return new self($name);
    }

    /**
     * Add a step at a given percentage (0 to 100).
     *
     * @param float|int|string $stop e.g. 0, 50, 100, or "0%", "100%"
     * @param array<string, mixed> $properties CSS properties or transform values
     */
    public function addStep(float|int|string $stop, array $properties): self
    {
        $key = is_string($stop) && str_ends_with($stop, '%')
            ? $stop
            : (string)round((float)$stop, 2) . '%';

        $this->steps[$key] = $properties;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function withName(string $name): self
    {
        $clone = clone $this;
        $clone->name = $name;
        return $clone;
    }

    public function getSteps(): array
    {
        return $this->steps;
    }

    public function getDuration(): float
    {
        return $this->duration;
    }

    public function duration(float $durationInSeconds): self
    {
        $this->duration = max(0.001, $durationInSeconds);
        return $this;
    }

    public function getDelay(): float
    {
        return $this->delay;
    }

    public function delay(float $delayInSeconds): self
    {
        $this->delay = max(0.0, $delayInSeconds);
        return $this;
    }

    public function getIterations(): int|string
    {
        return $this->iterations;
    }

    public function iterations(int|string $iterations): self
    {
        $this->iterations = $iterations === 'infinite' ? 'infinite' : max(1, (int)$iterations);
        return $this;
    }

    public function infinite(): self
    {
        $this->iterations = 'infinite';
        return $this;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function direction(string $direction): self
    {
        $this->direction = in_array($direction, ['normal', 'reverse', 'alternate', 'alternate-reverse'], true)
            ? $direction
            : 'normal';
        return $this;
    }

    public function alternate(): self
    {
        $this->direction = 'alternate';
        return $this;
    }

    public function getFillMode(): string
    {
        return $this->fillMode;
    }

    public function fillMode(string $fillMode): self
    {
        $this->fillMode = in_array($fillMode, ['none', 'forwards', 'backwards', 'both'], true)
            ? $fillMode
            : 'forwards';
        return $this;
    }

    public function getEasing(): string
    {
        return $this->easing;
    }

    public function easing(string $easing): self
    {
        $this->easing = $easing;
        return $this;
    }

    /**
     * Synthesize high-fidelity keyframes directly from spring physics parameters.
     *
     * @param string $name Animation name.
     * @param array<string, mixed> $from Starting properties.
     * @param array<string, mixed> $to Ending properties.
     * @param Spring $spring Spring physics oscillator.
     * @param int $steps Number of discrete sampling steps.
     */
    public static function fromSpring(
        string $name,
        array $from,
        array $to,
        Spring $spring,
        int $steps = 25
    ): self {
        $keyframes = new self($name);
        $duration = $spring->settlingDuration();
        $keyframes->duration($duration);
        $keyframes->delay($spring->getDelay());
        $keyframes->easing('linear');

        $steps = max(2, $steps);
        $keys = array_unique(array_merge(array_keys($from), array_keys($to)));

        for ($i = 0; $i < $steps; $i++) {
            $progress = $i / ($steps - 1);
            $t = $progress * $duration;
            $percentage = round($progress * 100, 2);

            $stepProps = [];
            $transforms = [];

            foreach ($keys as $k) {
                $startVal = $from[$k] ?? 0;
                $endVal = $to[$k] ?? 0;

                [$startNum, $unit] = self::extractNumberAndUnit($startVal);
                [$endNum] = self::extractNumberAndUnit($endVal);

                // Solve physics position for this property
                $currentNum = $spring->solve($t, $startNum, $endNum);

                if (in_array($k, ['x', 'y', 'z', 'scale', 'scaleX', 'scaleY', 'rotate', 'rotateX', 'rotateY', 'skewX', 'skewY'], true)) {
                    $transforms[] = self::formatTransformProperty($k, $currentNum, $unit);
                } else {
                    $stepProps[$k] = self::formatCssProperty($k, $currentNum, $unit);
                }
            }

            if (!empty($transforms)) {
                $stepProps['transform'] = implode(' ', $transforms);
            }

            $keyframes->addStep($percentage, $stepProps);
        }

        return $keyframes;
    }

    /**
     * Compile CSS @keyframes rule string.
     */
    public function toCss(): string
    {
        $lines = ["@keyframes {$this->name} {"];

        // Sort stops numerically
        $sorted = $this->steps;
        uksort($sorted, function ($a, $b) {
            $valA = (float)rtrim($a, '%');
            $valB = (float)rtrim($b, '%');
            return $valA <=> $valB;
        });

        foreach ($sorted as $stop => $props) {
            $propStrings = [];
            foreach ($props as $key => $val) {
                $propStrings[] = sprintf('%s: %s;', $key, (string)$val);
            }
            $lines[] = sprintf("  %s { %s }", $stop, implode(' ', $propStrings));
        }

        $lines[] = "}";
        return implode("\n", $lines);
    }

    /**
     * Generate standard CSS animation shorthand rule value.
     */
    public function toAnimationCss(): string
    {
        return sprintf(
            '%s %.3fs %s %.3fs %s %s %s',
            $this->name,
            $this->duration,
            $this->easing,
            $this->delay,
            $this->iterations,
            $this->direction,
            $this->fillMode
        );
    }

    /**
     * Extract numeric value and optional unit suffix.
     *
     * @return array{0: float, 1: string}
     */
    protected static function extractNumberAndUnit(mixed $value): array
    {
        if (is_numeric($value)) {
            return [(float)$value, ''];
        }

        $str = trim((string)$value);
        if (preg_match('/^(-?[\d\.]+)([a-zA-Z%]*)$/', $str, $matches)) {
            return [(float)$matches[1], $matches[2]];
        }

        return [0.0, ''];
    }

    protected static function formatTransformProperty(string $property, float $value, string $unit = ''): string
    {
        return match ($property) {
            'x' => sprintf('translateX(%.2f%s)', $value, $unit !== '' ? $unit : 'px'),
            'y' => sprintf('translateY(%.2f%s)', $value, $unit !== '' ? $unit : 'px'),
            'z' => sprintf('translateZ(%.2f%s)', $value, $unit !== '' ? $unit : 'px'),
            'scale' => sprintf('scale(%.4f)', $value),
            'scaleX' => sprintf('scaleX(%.4f)', $value),
            'scaleY' => sprintf('scaleY(%.4f)', $value),
            'rotate' => sprintf('rotate(%.2f%s)', $value, $unit !== '' ? $unit : 'deg'),
            'rotateX' => sprintf('rotateX(%.2f%s)', $value, $unit !== '' ? $unit : 'deg'),
            'rotateY' => sprintf('rotateY(%.2f%s)', $value, $unit !== '' ? $unit : 'deg'),
            'skewX' => sprintf('skewX(%.2f%s)', $value, $unit !== '' ? $unit : 'deg'),
            'skewY' => sprintf('skewY(%.2f%s)', $value, $unit !== '' ? $unit : 'deg'),
            default => sprintf('%s(%.2f%s)', $property, $value, $unit),
        };
    }

    protected static function formatCssProperty(string $property, float $value, string $unit = ''): string
    {
        if ($property === 'opacity') {
            return sprintf('%.4f', max(0.0, min(1.0, $value)));
        }

        return sprintf('%.2f%s', $value, $unit);
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'duration' => $this->duration,
            'delay' => $this->delay,
            'iterations' => $this->iterations,
            'direction' => $this->direction,
            'fillMode' => $this->fillMode,
            'easing' => $this->easing,
            'steps' => $this->steps,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
