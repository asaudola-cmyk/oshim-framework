<?php
declare(strict_types=1);

namespace Oshim\Ui\Canvas3D\Materials;

use Oshim\Ui\Canvas3D\Math\Color;

/**
 * Physically Based Rendering (PBR) Standard Material.
 */
class MeshStandardMaterial extends Material
{
    public float $roughness = 0.5;
    public float $metalness = 0.5;
    public Color $emissive;
    public float $emissiveIntensity = 0.0;

    public function __construct(
        Color|string $color = '#ffffff',
        float $roughness = 0.5,
        float $metalness = 0.5,
        Color|string $emissive = '#000000',
        float $emissiveIntensity = 0.0,
        string $name = 'MeshStandardMaterial'
    ) {
        parent::__construct($name, 'MeshStandardMaterial');
        $this->setColor($color);
        $this->roughness = max(0.0, min(1.0, $roughness));
        $this->metalness = max(0.0, min(1.0, $metalness));
        $this->emissive = is_string($emissive) ? Color::fromHex($emissive) : $emissive;
        $this->emissiveIntensity = $emissiveIntensity;
    }

    public function toThreeJsData(): array
    {
        $data = parent::toThreeJsData();
        $data['roughness'] = $this->roughness;
        $data['metalness'] = $this->metalness;
        $data['emissive'] = $this->emissive->toHexInt();
        $data['emissiveIntensity'] = $this->emissiveIntensity;
        return $data;
    }
}
