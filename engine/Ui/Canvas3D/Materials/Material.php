<?php
declare(strict_types=1);

namespace Oshim\Ui\Canvas3D\Materials;

use Oshim\Ui\Canvas3D\Math\Color;

/**
 * Base Material for WebGL surface shading & Three.js rendering.
 */
abstract class Material
{
    public string $uuid;
    public string $name;
    public string $type;
    public Color $color;
    public float $opacity = 1.0;
    public bool $transparent = false;
    public bool $wireframe = false;
    public bool $depthTest = true;
    public bool $depthWrite = true;

    public function __construct(string $name = 'Material', string $type = 'Material')
    {
        $this->name = $name;
        $this->type = $type;
        $this->uuid = 'mat_' . bin2hex(random_bytes(8));
        $this->color = Color::create(1.0, 1.0, 1.0);
    }

    public function setWireframe(bool $wireframe): self
    {
        $this->wireframe = $wireframe;
        return $this;
    }

    public function setOpacity(float $opacity): self
    {
        $this->opacity = max(0.0, min(1.0, $opacity));
        if ($this->opacity < 1.0) {
            $this->transparent = true;
        }
        return $this;
    }

    public function setColor(Color|string $color): self
    {
        if (is_string($color)) {
            $this->color = Color::fromHex($color);
        } else {
            $this->color = $color;
        }
        return $this;
    }

    /**
     * Three.js Object/Material Loader compatible export.
     */
    public function toThreeJsData(): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type,
            'name' => $this->name,
            'color' => $this->color->toHexInt(),
            'opacity' => $this->opacity,
            'transparent' => $this->transparent,
            'wireframe' => $this->wireframe,
            'depthTest' => $this->depthTest,
            'depthWrite' => $this->depthWrite,
        ];
    }
}
