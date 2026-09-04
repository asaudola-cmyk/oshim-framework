<?php
declare(strict_types=1);

namespace Oshim\Ui\Canvas3D\Core;

use Oshim\Ui\Canvas3D\Geometries\Geometry;
use Oshim\Ui\Canvas3D\Materials\Material;
use Oshim\Ui\Canvas3D\Math\Vector3;

/**
 * 3D Mesh binding geometric vertex structure with surface shading material.
 */
class Mesh3D extends Node3D
{
    public Geometry $geometry;
    public Material $material;

    /** @var array{x: float, y: float, z: float} Auto-rotation speed in rad/sec */
    public array $spinSpeed = ['x' => 0.0, 'y' => 0.0, 'z' => 0.0];

    /** @var float Floating hover bobbing amplitude */
    public float $floatAmplitude = 0.0;

    /** @var float Floating hover bobbing frequency */
    public float $floatSpeed = 0.0;

    public function __construct(
        Geometry $geometry,
        Material $material,
        string $name = 'Mesh3D'
    ) {
        parent::__construct($name, 'Mesh');
        $this->geometry = $geometry;
        $this->material = $material;
    }

    public static function create(Geometry $geometry, Material $material, string $name = 'Mesh3D'): self
    {
        return new self($geometry, $material, $name);
    }

    public function setSpin(float $x, float $y, float $z): self
    {
        $this->spinSpeed = ['x' => $x, 'y' => $y, 'z' => $z];
        return $this;
    }

    public function setFloating(float $amplitude, float $speed): self
    {
        $this->floatAmplitude = $amplitude;
        $this->floatSpeed = $speed;
        return $this;
    }

    public function toThreeJsObject(): array
    {
        $data = parent::toThreeJsObject();
        $data['geometry'] = $this->geometry->uuid;
        $data['material'] = $this->material->uuid;
        if ($this->spinSpeed['x'] !== 0.0 || $this->spinSpeed['y'] !== 0.0 || $this->spinSpeed['z'] !== 0.0) {
            $data['userData']['spinSpeed'] = $this->spinSpeed;
        }
        if ($this->floatAmplitude > 0.0) {
            $data['userData']['floatAmplitude'] = $this->floatAmplitude;
            $data['userData']['floatSpeed'] = $this->floatSpeed;
        }
        return $data;
    }
}
