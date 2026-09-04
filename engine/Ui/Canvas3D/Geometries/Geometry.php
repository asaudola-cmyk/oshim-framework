<?php
declare(strict_types=1);

namespace Oshim\Ui\Canvas3D\Geometries;

/**
 * Base 3D Geometry holding vertex attributes (positions, normals, uvs, indices).
 */
abstract class Geometry
{
    public string $uuid;
    public string $name;

    /** @var list<float> */
    public array $vertices = [];

    /** @var list<float> */
    public array $normals = [];

    /** @var list<float> */
    public array $uvs = [];

    /** @var list<int> */
    public array $indices = [];

    public function __construct(string $name = 'Geometry')
    {
        $this->name = $name;
        $this->uuid = 'geom_' . bin2hex(random_bytes(8));
        $this->generate();
    }

    /**
     * Compute vertex positions, normals, uvs, and indices.
     */
    abstract protected function generate(): void;

    public function getVertexCount(): int
    {
        return (int) (count($this->vertices) / 3);
    }

    public function getFaceCount(): int
    {
        if (!empty($this->indices)) {
            return (int) (count($this->indices) / 3);
        }
        return (int) (count($this->vertices) / 9);
    }

    /**
     * Computes the axis-aligned bounding box [minX, minY, minZ, maxX, maxY, maxZ].
     * @return array{min: array<float>, max: array<float>}
     */
    public function computeBoundingBox(): array
    {
        if (empty($this->vertices)) {
            return ['min' => [0.0, 0.0, 0.0], 'max' => [0.0, 0.0, 0.0]];
        }

        $minX = $maxX = $this->vertices[0];
        $minY = $maxY = $this->vertices[1];
        $minZ = $maxZ = $this->vertices[2];

        $len = count($this->vertices);
        for ($i = 0; $i < $len; $i += 3) {
            $x = $this->vertices[$i];
            $y = $this->vertices[$i + 1];
            $z = $this->vertices[$i + 2];

            if ($x < $minX) $minX = $x;
            if ($x > $maxX) $maxX = $x;
            if ($y < $minY) $minY = $y;
            if ($y > $maxY) $maxY = $y;
            if ($z < $minZ) $minZ = $z;
            if ($z > $maxZ) $maxZ = $z;
        }

        return [
            'min' => [$minX, $minY, $minZ],
            'max' => [$maxX, $maxY, $maxZ],
        ];
    }

    /**
     * Serializes geometry into Three.js BufferGeometry schema format.
     */
    public function toThreeJsData(): array
    {
        $data = [
            'uuid' => $this->uuid,
            'type' => 'BufferGeometry',
            'data' => [
                'attributes' => [
                    'position' => [
                        'itemSize' => 3,
                        'type' => 'Float32Array',
                        'array' => $this->vertices,
                    ],
                    'normal' => [
                        'itemSize' => 3,
                        'type' => 'Float32Array',
                        'array' => $this->normals,
                    ],
                    'uv' => [
                        'itemSize' => 2,
                        'type' => 'Float32Array',
                        'array' => $this->uvs,
                    ],
                ],
            ],
        ];

        if (!empty($this->indices)) {
            $data['data']['index'] = [
                'type' => 'Uint16Array',
                'array' => $this->indices,
            ];
        }

        return $data;
    }
}
