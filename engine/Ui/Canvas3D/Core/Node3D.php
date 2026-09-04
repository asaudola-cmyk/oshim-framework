<?php
declare(strict_types=1);

namespace Oshim\Ui\Canvas3D\Core;

use Oshim\Ui\Canvas3D\Math\Euler;
use Oshim\Ui\Canvas3D\Math\Matrix4;
use Oshim\Ui\Canvas3D\Math\Vector3;

/**
 * Base Scene Graph Node representing any 3D entity in the world hierarchy.
 */
class Node3D
{
    public string $uuid;
    public string $name;
    public string $type;
    public Vector3 $position;
    public Euler $rotation;
    public Vector3 $scale;
    public bool $visible = true;
    public bool $castShadow = false;
    public bool $receiveShadow = false;
    public array $userData = [];

    public ?Node3D $parent = null;

    /** @var list<Node3D> */
    public array $children = [];

    public function __construct(string $name = 'Node3D', string $type = 'Object3D')
    {
        $this->name = $name;
        $this->type = $type;
        $this->uuid = 'obj_' . bin2hex(random_bytes(8));
        $this->position = new Vector3(0.0, 0.0, 0.0);
        $this->rotation = new Euler(0.0, 0.0, 0.0);
        $this->scale = new Vector3(1.0, 1.0, 1.0);
    }

    public function add(Node3D $child): self
    {
        if ($child === $this) {
            return $this;
        }

        if ($child->parent !== null) {
            $child->parent->remove($child);
        }

        $child->parent = $this;
        $this->children[] = $child;
        return $this;
    }

    public function remove(Node3D $child): self
    {
        $index = array_search($child, $this->children, true);
        if ($index !== false) {
            $child->parent = null;
            array_splice($this->children, $index, 1);
        }
        return $this;
    }

    public function setPosition(float $x, float $y, float $z): self
    {
        $this->position->set($x, $y, $z);
        return $this;
    }

    public function setRotation(float $degX, float $degY, float $degZ): self
    {
        $this->rotation = Euler::fromDegrees($degX, $degY, $degZ);
        return $this;
    }

    public function setScale(float $x, float $y, float $z): self
    {
        $this->scale->set($x, $y, $z);
        return $this;
    }

    public function getLocalMatrix(): Matrix4
    {
        return Matrix4::compose($this->position, $this->rotation, $this->scale);
    }

    public function getWorldMatrix(): Matrix4
    {
        $local = $this->getLocalMatrix();
        if ($this->parent !== null) {
            return $this->parent->getWorldMatrix()->multiply($local);
        }
        return $local;
    }

    public function traverse(callable $callback): void
    {
        $callback($this);
        foreach ($this->children as $child) {
            $child->traverse($callback);
        }
    }

    public function findByName(string $name): ?Node3D
    {
        if ($this->name === $name) {
            return $this;
        }
        foreach ($this->children as $child) {
            $found = $child->findByName($name);
            if ($found !== null) {
                return $found;
            }
        }
        return null;
    }

    public function findByUuid(string $uuid): ?Node3D
    {
        if ($this->uuid === $uuid) {
            return $this;
        }
        foreach ($this->children as $child) {
            $found = $child->findByUuid($uuid);
            if ($found !== null) {
                return $found;
            }
        }
        return null;
    }

    public function toThreeJsObject(): array
    {
        $matrix = $this->getLocalMatrix();

        $data = [
            'uuid' => $this->uuid,
            'type' => $this->type,
            'name' => $this->name,
            'matrix' => $matrix->toArray(),
            'visible' => $this->visible,
            'castShadow' => $this->castShadow,
            'receiveShadow' => $this->receiveShadow,
            'userData' => $this->userData,
            'children' => [],
        ];

        foreach ($this->children as $child) {
            $data['children'][] = $child->toThreeJsObject();
        }

        return $data;
    }
}
