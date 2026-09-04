<?php
declare(strict_types=1);

namespace Oshim\Ui\Canvas3D\Core;

use Oshim\Ui\Canvas3D\Math\Matrix4;

/**
 * Perspective Projection Camera mimicking human optical perspective.
 */
class PerspectiveCamera extends Camera3D
{
    public float $fov;
    public float $aspect;
    public float $near;
    public float $far;

    public function __construct(
        float $fov = 60.0,
        float $aspect = 1.777778, // 16:9
        float $near = 0.1,
        float $far = 1000.0,
        string $name = 'PerspectiveCamera'
    ) {
        parent::__construct($name, 'PerspectiveCamera');
        $this->fov = $fov;
        $this->aspect = $aspect;
        $this->near = $near;
        $this->far = $far;
        $this->position->set(0.0, 0.0, 5.0);
    }

    public function getProjectionMatrix(): Matrix4
    {
        return Matrix4::perspective($this->fov, $this->aspect, $this->near, $this->far);
    }

    public function toThreeJsObject(): array
    {
        $data = parent::toThreeJsObject();
        $data['fov'] = $this->fov;
        $data['aspect'] = $this->aspect;
        $data['near'] = $this->near;
        $data['far'] = $this->far;
        return $data;
    }
}
