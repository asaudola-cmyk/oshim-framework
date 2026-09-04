<?php
declare(strict_types=1);

namespace Oshim\Ui\Canvas3D\Materials;

use Oshim\Ui\Canvas3D\Math\Color;

/**
 * Basic flat-shaded unlit material.
 */
class MeshBasicMaterial extends Material
{
    public function __construct(
        Color|string $color = '#00f2fe',
        bool $wireframe = false,
        float $opacity = 1.0,
        string $name = 'MeshBasicMaterial'
    ) {
        parent::__construct($name, 'MeshBasicMaterial');
        $this->setColor($color);
        $this->wireframe = $wireframe;
        $this->setOpacity($opacity);
    }
}
