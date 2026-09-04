<?php
declare(strict_types=1);

namespace Oshim\Ui\Canvas3D\Geometries;

/**
 * 3D UV Sphere Geometry generator.
 */
class SphereGeometry extends Geometry
{
    public function __construct(
        public float $radius = 1.0,
        public int $widthSegments = 16,
        public int $heightSegments = 12
    ) {
        $this->widthSegments = max(3, $this->widthSegments);
        $this->heightSegments = max(2, $this->heightSegments);
        parent::__construct('SphereGeometry');
    }

    protected function generate(): void
    {
        $this->vertices = [];
        $this->normals = [];
        $this->uvs = [];
        $this->indices = [];

        $grid = [];
        $index = 0;

        for ($iy = 0; $iy <= $this->heightSegments; $iy++) {
            $verticesRow = [];
            $v = $iy / $this->heightSegments;
            $uOffset = 0.0;
            if ($iy === 0) {
                $uOffset = 0.5 / $this->widthSegments;
            } elseif ($iy === $this->heightSegments) {
                $uOffset = -0.5 / $this->widthSegments;
            }

            for ($ix = 0; $ix <= $this->widthSegments; $ix++) {
                $u = $ix / $this->widthSegments;

                // Vertex
                $theta = $u * M_PI * 2.0;
                $phi = $v * M_PI;

                $x = -$this->radius * cos($theta) * sin($phi);
                $y = $this->radius * cos($phi);
                $z = $this->radius * sin($theta) * sin($phi);

                $this->vertices[] = $x;
                $this->vertices[] = $y;
                $this->vertices[] = $z;

                // Normal
                $len = sqrt(($x * $x) + ($y * $y) + ($z * $z));
                $invLen = $len > 0.0 ? 1.0 / $len : 0.0;
                $this->normals[] = $x * $invLen;
                $this->normals[] = $y * $invLen;
                $this->normals[] = $z * $invLen;

                // UV
                $this->uvs[] = $u + $uOffset;
                $this->uvs[] = 1.0 - $v;

                $verticesRow[] = $index++;
            }
            $grid[] = $verticesRow;
        }

        // Indices
        for ($iy = 0; $iy < $this->heightSegments; $iy++) {
            for ($ix = 0; $ix < $this->widthSegments; $ix++) {
                $a = $grid[$iy][$ix + 1];
                $b = $grid[$iy][$ix];
                $c = $grid[$iy + 1][$ix];
                $d = $grid[$iy + 1][$ix + 1];

                if ($iy !== 0) {
                    $this->indices[] = $a;
                    $this->indices[] = $b;
                    $this->indices[] = $d;
                }
                if ($iy !== $this->heightSegments - 1) {
                    $this->indices[] = $b;
                    $this->indices[] = $c;
                    $this->indices[] = $d;
                }
            }
        }
    }
}
