<?php
declare(strict_types=1);

namespace Oshim\Ui\Canvas3D\Core;

use Oshim\Ui\Canvas3D\Math\Matrix4;

/**
 * Orthographic Parallel Projection Camera for technical diagrams and isometric 3D views.
 */
class OrthographicCamera extends Camera3D
{
    public float $left;
    public float $right;
    public float $top;
    public float $bottom;
    public float $near;
    public float $far;

    public function __construct(
        float $left = -5.0,
        float $right = 5.0,
        float $top = 5.0,
        float $bottom = -5.0,
        float $near = 0.1,
        float $far = 1000.0,
        string $name = 'OrthographicCamera'
    ) {
        parent::__construct($name, 'OrthographicCamera');
        $this->left = $left;
        $this->right = $right;
        $this->top = $top;
        $this->bottom = $bottom;
        $this->near = $near;
        $this->far = $far;
        $this->position->set(0.0, 0.0, 10.0);
    }

    public function getProjectionMatrix(): Matrix4
    {
        return Matrix4::orthographic($this->left, $this->right, $this->top, $this->bottom, $this->near, $this->far);
    }

    public function toThreeJsObject(): array
    {
        $data = parent::toThreeJsObject();
        $data['left'] = $this->left;
        $data['right'] = $this->right;
        $data['top'] = $this->top;
        $data['bottom'] = $this->bottom;
        $data['near'] = $this->near;
        $data['far'] = $this->far;
        return $data;
    }
}
