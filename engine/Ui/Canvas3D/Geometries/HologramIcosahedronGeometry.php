<?php
declare(strict_types=1);

namespace Oshim\Ui\Canvas3D\Geometries;

/**
 * 3D Holographic Icosahedron Geometry (20-faced polyhedral quantum hologram crystal).
 */
class HologramIcosahedronGeometry extends Geometry
{
    public function __construct(
        public float $radius = 1.0,
        public int $detail = 0
    ) {
        parent::__construct('HologramIcosahedronGeometry');
    }

    protected function generate(): void
    {
        $t = (1.0 + sqrt(5.0)) / 2.0;

        $rawVertices = [
            [-1,  $t,  0],
            [ 1,  $t,  0],
            [-1, -$t,  0],
            [ 1, -$t,  0],

            [ 0, -1,  $t],
            [ 0,  1,  $t],
            [ 0, -1, -$t],
            [ 0,  1, -$t],

            [ $t,  0, -1],
            [ $t,  0,  1],
            [-$t,  0, -1],
            [-$t,  0,  1],
        ];

        // 20 triangular faces
        $rawIndices = [
            0, 11, 5,   0, 5, 1,    0, 1, 7,    0, 7, 10,   0, 10, 11,
            1, 5, 9,    5, 11, 4,   11, 10, 2,  10, 7, 6,   7, 1, 8,
            3, 9, 4,    3, 4, 2,    3, 2, 6,    3, 6, 8,    3, 8, 9,
            4, 9, 5,    2, 4, 11,   6, 2, 10,   8, 6, 7,    9, 8, 1,
        ];

        // Normalize vertices to sphere of given radius
        $this->vertices = [];
        $this->normals = [];
        $this->uvs = [];

        foreach ($rawVertices as $v) {
            $len = sqrt(($v[0] * $v[0]) + ($v[1] * $v[1]) + ($v[2] * $v[2]));
            $nx = $v[0] / $len;
            $ny = $v[1] / $len;
            $nz = $v[2] / $len;

            $this->vertices[] = $nx * $this->radius;
            $this->vertices[] = $ny * $this->radius;
            $this->vertices[] = $nz * $this->radius;

            $this->normals[] = $nx;
            $this->normals[] = $ny;
            $this->normals[] = $nz;

            // UV mapping from spherical coordinates
            $u = 0.5 + (atan2($nz, $nx) / (2.0 * M_PI));
            $vCoord = 0.5 - (asin($ny) / M_PI);
            $this->uvs[] = $u;
            $this->uvs[] = $vCoord;
        }

        $this->indices = $rawIndices;
    }
}
