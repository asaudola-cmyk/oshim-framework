<?php
declare(strict_types=1);

namespace Oshim\Ui\Canvas3D\Core;

use Oshim\Ui\Canvas3D\Math\Color;

/**
 * Omnidirectional Point Light originating from a single point in space.
 */
class PointLight extends Light3D
{
    public float $distance = 0.0;
    public float $decay = 2.0;

    public function __construct(
        Color|string $color = '#00f2fe',
        float $intensity = 1.0,
        float $distance = 50.0,
        float $decay = 2.0,
        string $name = 'PointLight'
    ) {
        parent::__construct($color, $intensity, $name, 'PointLight');
        $this->distance = $distance;
        $this->decay = $decay;
    }

    public function toThreeJsObject(): array
    {
        $data = parent::toThreeJsObject();
        $data['distance'] = $this->distance;
        $data['decay'] = $this->decay;
        return $data;
    }
}
