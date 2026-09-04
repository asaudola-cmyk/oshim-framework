<?php
declare(strict_types=1);

namespace Oshim\Ui\Canvas3D\Core;

use Oshim\Ui\Canvas3D\Math\Color;

/**
 * Omnidirectional Ambient Light providing baseline base illumination.
 */
class AmbientLight extends Light3D
{
    public function __construct(
        Color|string $color = '#ffffff',
        float $intensity = 0.5,
        string $name = 'AmbientLight'
    ) {
        parent::__construct($color, $intensity, $name, 'AmbientLight');
    }
}
