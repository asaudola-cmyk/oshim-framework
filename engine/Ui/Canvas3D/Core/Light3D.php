<?php
declare(strict_types=1);

namespace Oshim\Ui\Canvas3D\Core;

use Oshim\Ui\Canvas3D\Math\Color;

/**
 * Base Light class for 3D illumination.
 */
abstract class Light3D extends Node3D
{
    public Color $color;
    public float $intensity;

    public function __construct(
        Color|string $color = '#ffffff',
        float $intensity = 1.0,
        string $name = 'Light3D',
        string $type = 'Light'
    ) {
        parent::__construct($name, $type);
        $this->color = is_string($color) ? Color::fromHex($color) : $color;
        $this->intensity = max(0.0, $intensity);
    }

    public function toThreeJsObject(): array
    {
        $data = parent::toThreeJsObject();
        $data['color'] = $this->color->toHexInt();
        $data['intensity'] = $this->intensity;
        return $data;
    }
}
