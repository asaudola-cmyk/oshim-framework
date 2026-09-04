<?php
declare(strict_types=1);

namespace Oshim\Ui\Canvas3D\Serialization;

use Oshim\Ui\Canvas3D\Core\Scene3D;

/**
 * Serializes pure PHP 3D Scene Graphs into standard Three.js Object Loader JSON format (v4.5+).
 */
class ThreeJsSerializer
{
    /**
     * Convert a Scene3D to an array matching Three.js Object/Scene JSON format.
     *
     * @return array<string, mixed>
     */
    public static function serialize(Scene3D $scene): array
    {
        $geometriesData = [];
        foreach ($scene->getAllGeometries() as $geom) {
            $geometriesData[] = $geom->toThreeJsData();
        }

        $materialsData = [];
        foreach ($scene->getAllMaterials() as $mat) {
            $materialsData[] = $mat->toThreeJsData();
        }

        $objectData = $scene->toThreeJsObject();

        return [
            'metadata' => [
                'version' => 4.5,
                'type' => 'Object',
                'generator' => 'Oshim Canvas3D Sovereign Engine',
            ],
            'geometries' => $geometriesData,
            'materials' => $materialsData,
            'object' => $objectData,
        ];
    }

    /**
     * Export Scene3D directly to formatted JSON.
     */
    public static function toJson(Scene3D $scene, bool $pretty = false): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }
        return (string) json_encode(self::serialize($scene), $flags);
    }
}
