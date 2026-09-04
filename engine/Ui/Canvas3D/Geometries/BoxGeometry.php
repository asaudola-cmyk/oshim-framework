<?php
declare(strict_types=1);

namespace Oshim\Ui\Canvas3D\Geometries;

/**
 * 3D Box / Cuboid Geometry with explicit vertex normals and UV mappings.
 */
class BoxGeometry extends Geometry
{
    public function __construct(
        public float $width = 1.0,
        public float $height = 1.0,
        public float $depth = 1.0
    ) {
        parent::__construct('BoxGeometry');
    }

    protected function generate(): void
    {
        $w = $this->width / 2.0;
        $h = $this->height / 2.0;
        $d = $this->depth / 2.0;

        // 6 faces: +Z, -Z, +X, -X, +Y, -Y
        $this->vertices = [
            // Front (+Z)
            -$w, -$h,  $d,   $w, -$h,  $d,   $w,  $h,  $d,  -$w,  $h,  $d,
            // Back (-Z)
             $w, -$h, -$d,  -$w, -$h, -$d,  -$w,  $h, -$d,   $w,  $h, -$d,
            // Top (+Y)
            -$w,  $h,  $d,   $w,  $h,  $d,   $w,  $h, -$d,  -$w,  $h, -$d,
            // Bottom (-Y)
            -$w, -$h, -$d,   $w, -$h, -$d,   $w, -$h,  $d,  -$w, -$h,  $d,
            // Right (+X)
             $w, -$h,  $d,   $w, -$h, -$d,   $w,  $h, -$d,   $w,  $h,  $d,
            // Left (-X)
            -$w, -$h, -$d,  -$w, -$h,  $d,  -$w,  $h,  $d,  -$w,  $h, -$d,
        ];

        $this->normals = [
            // Front
             0.0,  0.0,  1.0,   0.0,  0.0,  1.0,   0.0,  0.0,  1.0,   0.0,  0.0,  1.0,
            // Back
             0.0,  0.0, -1.0,   0.0,  0.0, -1.0,   0.0,  0.0, -1.0,   0.0,  0.0, -1.0,
            // Top
             0.0,  1.0,  0.0,   0.0,  1.0,  0.0,   0.0,  1.0,  0.0,   0.0,  1.0,  0.0,
            // Bottom
             0.0, -1.0,  0.0,   0.0, -1.0,  0.0,   0.0, -1.0,  0.0,   0.0, -1.0,  0.0,
            // Right
             1.0,  0.0,  0.0,   1.0,  0.0,  0.0,   1.0,  0.0,  0.0,   1.0,  0.0,  0.0,
            // Left
            -1.0,  0.0,  0.0,  -1.0,  0.0,  0.0,  -1.0,  0.0,  0.0,  -1.0,  0.0,  0.0,
        ];

        $this->uvs = [
            0.0, 0.0,  1.0, 0.0,  1.0, 1.0,  0.0, 1.0,
            0.0, 0.0,  1.0, 0.0,  1.0, 1.0,  0.0, 1.0,
            0.0, 0.0,  1.0, 0.0,  1.0, 1.0,  0.0, 1.0,
            0.0, 0.0,  1.0, 0.0,  1.0, 1.0,  0.0, 1.0,
            0.0, 0.0,  1.0, 0.0,  1.0, 1.0,  0.0, 1.0,
            0.0, 0.0,  1.0, 0.0,  1.0, 1.0,  0.0, 1.0,
        ];

        $this->indices = [];
        for ($face = 0; $face < 6; $face++) {
            $offset = $face * 4;
            $this->indices[] = $offset;
            $this->indices[] = $offset + 1;
            $this->indices[] = $offset + 2;
            $this->indices[] = $offset;
            $this->indices[] = $offset + 2;
            $this->indices[] = $offset + 3;
        }
    }
}
