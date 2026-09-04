<?php
declare(strict_types=1);

namespace Oshim\Ui\Canvas3D\Math;

/**
 * 3D Vector primitive for WebGL & Canvas3D Scene Graph calculations.
 */
class Vector3
{
    public function __construct(
        public float $x = 0.0,
        public float $y = 0.0,
        public float $z = 0.0
    ) {
    }

    public static function create(float $x = 0.0, float $y = 0.0, float $z = 0.0): self
    {
        return new self($x, $y, $z);
    }

    public static function fromArray(array $coords): self
    {
        return new self(
            (float) ($coords[0] ?? $coords['x'] ?? 0.0),
            (float) ($coords[1] ?? $coords['y'] ?? 0.0),
            (float) ($coords[2] ?? $coords['z'] ?? 0.0)
        );
    }

    public function set(float $x, float $y, float $z): self
    {
        $this->x = $x;
        $this->y = $y;
        $this->z = $z;
        return $this;
    }

    public function add(self $v): self
    {
        return new self($this->x + $v->x, $this->y + $v->y, $this->z + $v->z);
    }

    public function sub(self $v): self
    {
        return new self($this->x - $v->x, $this->y - $v->y, $this->z - $v->z);
    }

    public function scale(float $scalar): self
    {
        return new self($this->x * $scalar, $this->y * $scalar, $this->z * $scalar);
    }

    public function dot(self $v): float
    {
        return ($this->x * $v->x) + ($this->y * $v->y) + ($this->z * $v->z);
    }

    public function cross(self $v): self
    {
        return new self(
            ($this->y * $v->z) - ($this->z * $v->y),
            ($this->z * $v->x) - ($this->x * $v->z),
            ($this->x * $v->y) - ($this->y * $v->x)
        );
    }

    public function lengthSquared(): float
    {
        return ($this->x * $this->x) + ($this->y * $this->y) + ($this->z * $this->z);
    }

    public function length(): float
    {
        return sqrt($this->lengthSquared());
    }

    public function distanceTo(self $v): float
    {
        return $this->sub($v)->length();
    }

    public function normalize(): self
    {
        $len = $this->length();
        if ($len <= 1e-9) {
            return new self(0.0, 0.0, 0.0);
        }
        return $this->scale(1.0 / $len);
    }

    public function lerp(self $target, float $alpha): self
    {
        $clampedAlpha = max(0.0, min(1.0, $alpha));
        return new self(
            $this->x + (($target->x - $this->x) * $clampedAlpha),
            $this->y + (($target->y - $this->y) * $clampedAlpha),
            $this->z + (($target->z - $this->z) * $clampedAlpha)
        );
    }

    public function negate(): self
    {
        return new self(-$this->x, -$this->y, -$this->z);
    }

    public function clone(): self
    {
        return new self($this->x, $this->y, $this->z);
    }

    /**
     * @return array{0: float, 1: float, 2: float}
     */
    public function toArray(): array
    {
        return [$this->x, $this->y, $this->z];
    }

    public function toGlsl(): string
    {
        return sprintf('vec3(%.4f, %.4f, %.4f)', $this->x, $this->y, $this->z);
    }

    public function equals(self $other, float $epsilon = 1e-5): bool
    {
        return abs($this->x - $other->x) < $epsilon
            && abs($this->y - $other->y) < $epsilon
            && abs($this->z - $other->z) < $epsilon;
    }
}
