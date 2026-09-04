<?php
declare(strict_types=1);

namespace Oshim\Ui\Canvas3D\Core;

use Oshim\Ui\Canvas3D\Math\Color;
use Oshim\Ui\Canvas3D\Math\Vector3;

/**
 * Parallel Sun-like Directional Light with cast shadows support.
 */
class DirectionalLight extends Light3D
{
    public Vector3 $target;

    public function __construct(
        Color|string $color = '#ffffff',
        float $intensity = 1.0,
        string $name = 'DirectionalLight'
    ) {
        parent::__construct($color, $intensity, $name, 'DirectionalLight');
        $this->position->set(5.0, 10.0, 7.5);
        $this->target = new Vector3(0.0, 0.0, 0.0);
    }

    public function setDirection(float $x, float $y, float $z): self
    {
        $this->position->set($x, $y, $z);
        return $this;
    }
}
