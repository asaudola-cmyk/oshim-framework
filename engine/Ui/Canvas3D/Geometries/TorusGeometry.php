<?php
declare(strict_types=1);

namespace Oshim\Ui\Canvas3D\Geometries;

/**
 * 3D Torus (Donut) Geometry generator.
 */
class TorusGeometry extends Geometry
{
    public function __construct(
        public float $radius = 1.0,
        public float $tube = 0.4,
        public int $radialSegments = 12,
        public int $tubularSegments = 24
    ) {
        $this->radialSegments = max(3, $this->radialSegments);
        $this->tubularSegments = max(3, $this->tubularSegments);
        parent::__construct('TorusGeometry');
    }

    protected function generate(): void
    {
        $this->vertices = [];
        $this->normals = [];
        $this->uvs = [];
        $this->indices = [];

        for ($j = 0; $j <= $this->radialSegments; $j++) {
            for ($i = 0; $i <= $this->tubularSegments; $i++) {
                $u = ($i / $this->tubularSegments) * M_PI * 2.0;
                $v = ($j / $this->radialSegments) * M_PI * 2.0;

                $x = ($this->radius + ($this->tube * cos($v))) * cos($u);
                $y = ($this->radius + ($this->tube * cos($v))) * sin($u);
                $z = $this->tube * sin($v);

                $this->vertices[] = $x;
                $this->vertices[] = $y;
                $this->vertices[] = $z;

                $cx = $this->radius * cos($u);
                $cy = $this->radius * sin($u);
                $cz = 0.0;

                $nx = $x - $cx;
                $ny = $y - $cy;
                $nz = $z - $cz;
                $len = sqrt(($nx * $nx) + ($ny * $ny) + ($nz * $nz));
                $inv = $len > 0.0 ? 1.0 / $len : 1.0;

                $this->normals[] = $nx * $inv;
                $this->normals[] = $ny * $inv;
                $this->normals[] = $nz * $inv;

                $this->uvs[] = $i / $this->tubularSegments;
                $this->uvs[] = $j / $this->radialSegments;
            }
        }

        for ($j = 1; $j <= $this->radialSegments; $j++) {
            for ($i = 1; $i <= $this->tubularSegments; $i++) {
                $a = ($this->tubularSegments + 1) * $j + $i - 1;
                $b = ($this->tubularSegments + 1) * ($j - 1) + $i - 1;
                $c = ($this->tubularSegments + 1) * ($j - 1) + $i;
                $d = ($this->tubularSegments + 1) * $j + $i;

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
