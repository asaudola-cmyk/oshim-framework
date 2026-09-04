<?php
declare(strict_types=1);

namespace Oshim\Ui\Canvas3D\Core;

use Oshim\Ui\Canvas3D\Geometries\Geometry;
use Oshim\Ui\Canvas3D\Materials\Material;
use Oshim\Ui\Canvas3D\Math\Color;

/**
 * Root 3D Scene Graph containing meshes, lights, cameras, fog, and environmental state.
 */
class Scene3D extends Node3D
{
    public ?Color $background = null;
    public ?array $fog = null;
    public ?Camera3D $activeCamera = null;

    public function __construct(string $name = 'Scene3D')
    {
        parent::__construct($name, 'Scene');
        $this->background = Color::fromHex('#0a0c16'); // Default sleek dark space
    }

    public static function create(string $name = 'Scene3D'): self
    {
        return new self($name);
    }

    public function setBackground(Color|string|null $color): self
    {
        if (is_string($color)) {
            $this->background = Color::fromHex($color);
        } else {
            $this->background = $color;
        }
        return $this;
    }

    public function setFog(Color|string $color, float $near = 1.0, float $far = 100.0): self
    {
        $fogColor = is_string($color) ? Color::fromHex($color) : $color;
        $this->fog = [
            'type' => 'Fog',
            'color' => $fogColor,
            'near' => $near,
            'far' => $far,
        ];
        return $this;
    }

    public function setCamera(Camera3D $camera): self
    {
        $this->activeCamera = $camera;
        if (!in_array($camera, $this->children, true)) {
            $this->add($camera);
        }
        return $this;
    }

    public function getCamera(): Camera3D
    {
        if ($this->activeCamera !== null) {
            return $this->activeCamera;
        }

        // Search in children
        foreach ($this->children as $child) {
            if ($child instanceof Camera3D) {
                $this->activeCamera = $child;
                return $child;
            }
        }

        // Default perspective camera
        $defaultCam = new PerspectiveCamera();
        $this->setCamera($defaultCam);
        return $defaultCam;
    }

    /**
     * @return list<Mesh3D>
     */
    public function getAllMeshes(): array
    {
        $meshes = [];
        $this->traverse(function (Node3D $node) use (&$meshes) {
            if ($node instanceof Mesh3D) {
                $meshes[] = $node;
            }
        });
        return $meshes;
    }

    /**
     * @return list<Light3D>
     */
    public function getAllLights(): array
    {
        $lights = [];
        $this->traverse(function (Node3D $node) use (&$lights) {
            if ($node instanceof Light3D) {
                $lights[] = $node;
            }
        });
        return $lights;
    }

    /**
     * @return list<Geometry>
     */
    public function getAllGeometries(): array
    {
        $geometries = [];
        $seen = [];
        foreach ($this->getAllMeshes() as $mesh) {
            if (!isset($seen[$mesh->geometry->uuid])) {
                $seen[$mesh->geometry->uuid] = true;
                $geometries[] = $mesh->geometry;
            }
        }
        return $geometries;
    }

    /**
     * @return list<Material>
     */
    public function getAllMaterials(): array
    {
        $materials = [];
        $seen = [];
        foreach ($this->getAllMeshes() as $mesh) {
            if (!isset($seen[$mesh->material->uuid])) {
                $seen[$mesh->material->uuid] = true;
                $materials[] = $mesh->material;
            }
        }
        return $materials;
    }

    public function toThreeJsObject(): array
    {
        $data = parent::toThreeJsObject();
        if ($this->background !== null) {
            $data['background'] = $this->background->toHexInt();
        }
        if ($this->fog !== null) {
            $data['fog'] = [
                'type' => $this->fog['type'],
                'color' => $this->fog['color']->toHexInt(),
                'near' => $this->fog['near'],
                'far' => $this->fog['far'],
            ];
        }
        return $data;
    }
}
