<?php
declare(strict_types=1);

namespace Oshim\Ui\Canvas3D\Math;

/**
 * Euler angles representing 3D rotations in radians.
 */
class Euler
{
    public function __construct(
        public float $x = 0.0,
        public float $y = 0.0,
        public float $z = 0.0,
        public string $order = 'XYZ'
    ) {
    }

    public static function create(float $x = 0.0, float $y = 0.0, float $z = 0.0, string $order = 'XYZ'): self
    {
        return new self($x, $y, $z, $order);
    }

    public static function fromDegrees(float $degX, float $degY, float $degZ, string $order = 'XYZ'): self
    {
        return new self(
            deg2rad($degX),
            deg2rad($degY),
            deg2rad($degZ),
            $order
        );
    }

    public function toDegrees(): array
    {
        return [
            rad2deg($this->x),
            rad2deg($this->y),
            rad2deg($this->z),
        ];
    }

    public function toArray(): array
    {
        return [$this->x, $this->y, $this->z];
    }

    public function clone(): self
    {
        return new self($this->x, $this->y, $this->z, $this->order);
    }
}
