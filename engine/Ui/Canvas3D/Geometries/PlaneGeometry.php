<?php
declare(strict_types=1);

namespace Oshim\Ui\Canvas3D\Geometries;

/**
 * 2D/3D Quad Grid Plane Geometry.
 */
class PlaneGeometry extends Geometry
{
    public function __construct(
        public float $width = 1.0,
        public float $height = 1.0,
        public int $widthSegments = 1,
        public int $heightSegments = 1
    ) {
        $this->widthSegments = max(1, $this->widthSegments);
        $this->heightSegments = max(1, $this->heightSegments);
        parent::__construct('PlaneGeometry');
    }

    protected function generate(): void
    {
        $this->vertices = [];
        $this->normals = [];
        $this->uvs = [];
        $this->indices = [];

        $wHalf = $this->width / 2.0;
        $hHalf = $this->height / 2.0;

        $gridX = $this->widthSegments;
        $gridY = $this->heightSegments;

        $segmentWidth = $this->width / $gridX;
        $segmentHeight = $this->height / $gridY;

        for ($iy = 0; $iy <= $gridY; $iy++) {
            $y = ($iy * $segmentHeight) - $hHalf;
            for ($ix = 0; $ix <= $gridX; $ix++) {
                $x = ($ix * $segmentWidth) - $wHalf;

                $this->vertices[] = $x;
                $this->vertices[] = -$y;
                $this->vertices[] = 0.0;

                $this->normals[] = 0.0;
                $this->normals[] = 0.0;
                $this->normals[] = 1.0;

                $this->uvs[] = $ix / $gridX;
                $this->uvs[] = 1.0 - ($iy / $gridY);
            }
        }

        for ($iy = 0; $iy < $gridY; $iy++) {
            for ($ix = 0; $ix < $gridX; $ix++) {
                $a = $ix + (($gridX + 1) * $iy);
                $b = $ix + (($gridX + 1) * ($iy + 1));
                $c = ($ix + 1) + (($gridX + 1) * ($iy + 1));
                $d = ($ix + 1) + (($gridX + 1) * $iy);

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
