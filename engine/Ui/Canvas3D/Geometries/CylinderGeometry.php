<?php
declare(strict_types=1);

namespace Oshim\Ui\Canvas3D\Geometries;

/**
 * 3D Cylinder & Cone Geometry generator.
 */
class CylinderGeometry extends Geometry
{
    public function __construct(
        public float $radiusTop = 1.0,
        public float $radiusBottom = 1.0,
        public float $height = 2.0,
        public int $radialSegments = 16,
        public int $heightSegments = 1
    ) {
        $this->radialSegments = max(3, $this->radialSegments);
        $this->heightSegments = max(1, $this->heightSegments);
        parent::__construct('CylinderGeometry');
    }

    protected function generate(): void
    {
        $this->vertices = [];
        $this->normals = [];
        $this->uvs = [];
        $this->indices = [];

        $index = 0;
        $indexArray = [];
        $halfHeight = $this->height / 2.0;

        $slope = ($this->radiusBottom - $this->radiusTop) / $this->height;

        // Torso
        for ($y = 0; $y <= $this->heightSegments; $y++) {
            $indexRow = [];
            $v = $y / $this->heightSegments;
            $radius = $v * ($this->radiusBottom - $this->radiusTop) + $this->radiusTop;

            for ($x = 0; $x <= $this->radialSegments; $x++) {
                $u = $x / $this->radialSegments;
                $theta = $u * M_PI * 2.0;

                $sinTheta = sin($theta);
                $cosTheta = cos($theta);

                $vx = $radius * $sinTheta;
                $vy = -$v * $this->height + $halfHeight;
                $vz = $radius * $cosTheta;

                $this->vertices[] = $vx;
                $this->vertices[] = $vy;
                $this->vertices[] = $vz;

                // Normal
                $nx = $sinTheta;
                $ny = $slope;
                $nz = $cosTheta;
                $len = sqrt(($nx * $nx) + ($ny * $ny) + ($nz * $nz));
                $inv = $len > 0 ? 1.0 / $len : 1.0;

                $this->normals[] = $nx * $inv;
                $this->normals[] = $ny * $inv;
                $this->normals[] = $nz * $inv;

                $this->uvs[] = $u;
                $this->uvs[] = 1.0 - $v;

                $indexRow[] = $index++;
            }
            $indexArray[] = $indexRow;
        }

        // Torso indices
        for ($x = 0; $x < $this->radialSegments; $x++) {
            for ($y = 0; $y < $this->heightSegments; $y++) {
                $a = $indexArray[$y][$x];
                $b = $indexArray[$y + 1][$x];
                $c = $indexArray[$y + 1][$x + 1];
                $d = $indexArray[$y][$x + 1];

                $this->indices[] = $a;
                $this->indices[] = $b;
                $this->indices[] = $d;

                $this->indices[] = $b;
                $this->indices[] = $c;
                $this->indices[] = $d;
            }
        }
    }
}
