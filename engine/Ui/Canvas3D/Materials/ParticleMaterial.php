<?php
declare(strict_types=1);

namespace Oshim\Ui\Canvas3D\Materials;

use Oshim\Ui\Canvas3D\Math\Color;

/**
 * Particle point cloud material for ambient 3D starfields and data streams.
 */
class ParticleMaterial extends Material
{
    public float $size = 2.0;

    public function __construct(
        Color|string $color = '#00f2fe',
        float $size = 2.0,
        float $opacity = 0.8,
        string $name = 'PointsMaterial'
    ) {
        parent::__construct($name, 'PointsMaterial');
        $this->setColor($color);
        $this->size = $size;
        $this->setOpacity($opacity);
        $this->transparent = true;
    }

    public function toThreeJsData(): array
    {
        $data = parent::toThreeJsData();
        $data['size'] = $this->size;
        return $data;
    }
}
