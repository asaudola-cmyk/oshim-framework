<?php
declare(strict_types=1);

namespace Oshim\Ui\Canvas3D\Core;

use Oshim\Ui\Canvas3D\Math\Matrix4;
use Oshim\Ui\Canvas3D\Math\Vector3;

/**
 * Base Camera class for 3D Viewports.
 */
abstract class Camera3D extends Node3D
{
    public Vector3 $target;

    public function __construct(string $name = 'Camera3D', string $type = 'Camera')
    {
        parent::__construct($name, $type);
        $this->target = new Vector3(0.0, 0.0, 0.0);
    }

    public function lookAt(Vector3|float $x, ?float $y = null, ?float $z = null): self
    {
        if ($x instanceof Vector3) {
            $this->target = $x->clone();
        } else {
            $this->target = new Vector3($x, $y ?? 0.0, $z ?? 0.0);
        }
        return $this;
    }

    abstract public function getProjectionMatrix(): Matrix4;

    public function getViewMatrix(): Matrix4
    {
        return Matrix4::lookAt($this->position, $this->target, new Vector3(0.0, 1.0, 0.0));
    }
}
