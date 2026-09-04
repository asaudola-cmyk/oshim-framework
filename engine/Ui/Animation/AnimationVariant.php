<?php
declare(strict_types=1);

namespace Oshim\Ui\Animation;

use JsonSerializable;

/**
 * Visual State Variant for Declarative Motion.
 *
 * Encapsulates CSS transforms, styling properties, and physics transition
 * parameters for named states (e.g., initial, animate, hover, tap, exit).
 */
class AnimationVariant implements JsonSerializable
{
    protected string $name;
    /** @var array<string, mixed> */
    protected array $properties = [];
    protected ?Spring $spring = null;

    public function __construct(string $name, array $properties = [], ?Spring $spring = null)
    {
        $this->name = $name;
        $this->properties = $properties;
        $this->spring = $spring;
    }

    public static function make(string $name = 'state', array $properties = []): self
    {
        return new self($name, $properties);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getProperty(string $name, mixed $default = null): mixed
    {
        return $this->properties[$name] ?? $default;
    }

    public function hasProperty(string $name): bool
    {
        return array_key_exists($name, $this->properties);
    }

    public function set(string $property, mixed $value): self
    {
        $this->properties[$property] = $value;
        return $this;
    }

    public function opacity(float $value): self
    {
        $this->properties['opacity'] = max(0.0, min(1.0, $value));
        return $this;
    }

    public function x(float|int|string $value): self
    {
        $this->properties['x'] = is_numeric($value) ? (float)$value : $value;
        return $this;
    }

    public function y(float|int|string $value): self
    {
        $this->properties['y'] = is_numeric($value) ? (float)$value : $value;
        return $this;
    }

    public function z(float|int|string $value): self
    {
        $this->properties['z'] = is_numeric($value) ? (float)$value : $value;
        return $this;
    }

    public function scale(float $value): self
    {
        $this->properties['scale'] = $value;
        return $this;
    }

    public function scaleX(float $value): self
    {
        $this->properties['scaleX'] = $value;
        return $this;
    }

    public function scaleY(float $value): self
    {
        $this->properties['scaleY'] = $value;
        return $this;
    }

    public function rotate(float|int|string $value): self
    {
        $this->properties['rotate'] = is_numeric($value) ? (float)$value : $value;
        return $this;
    }

    public function rotateX(float|int|string $value): self
    {
        $this->properties['rotateX'] = is_numeric($value) ? (float)$value : $value;
        return $this;
    }

    public function rotateY(float|int|string $value): self
    {
        $this->properties['rotateY'] = is_numeric($value) ? (float)$value : $value;
        return $this;
    }

    public function skewX(float|int|string $value): self
    {
        $this->properties['skewX'] = is_numeric($value) ? (float)$value : $value;
        return $this;
    }

    public function skewY(float|int|string $value): self
    {
        $this->properties['skewY'] = is_numeric($value) ? (float)$value : $value;
        return $this;
    }

    public function filter(string $filter): self
    {
        $this->properties['filter'] = $filter;
        return $this;
    }

    public function background(string $background): self
    {
        $this->properties['background'] = $background;
        return $this;
    }

    public function color(string $color): self
    {
        $this->properties['color'] = $color;
        return $this;
    }

    public function spring(?Spring $spring): self
    {
        $this->spring = $spring;
        return $this;
    }

    public function transition(?Spring $spring): self
    {
        $this->spring = $spring;
        return $this;
    }

    public function getSpring(): ?Spring
    {
        return $this->spring;
    }

    /**
     * Compute CSS transform declaration from individual transform coordinates.
     */
    public function toTransformString(): string
    {
        $transforms = [];

        if (isset($this->properties['x'])) {
            $val = $this->properties['x'];
            $unit = is_numeric($val) ? 'px' : '';
            $transforms[] = "translateX({$val}{$unit})";
        }
        if (isset($this->properties['y'])) {
            $val = $this->properties['y'];
            $unit = is_numeric($val) ? 'px' : '';
            $transforms[] = "translateY({$val}{$unit})";
        }
        if (isset($this->properties['z'])) {
            $val = $this->properties['z'];
            $unit = is_numeric($val) ? 'px' : '';
            $transforms[] = "translateZ({$val}{$unit})";
        }
        if (isset($this->properties['scale'])) {
            $transforms[] = "scale({$this->properties['scale']})";
        }
        if (isset($this->properties['scaleX'])) {
            $transforms[] = "scaleX({$this->properties['scaleX']})";
        }
        if (isset($this->properties['scaleY'])) {
            $transforms[] = "scaleY({$this->properties['scaleY']})";
        }
        if (isset($this->properties['rotate'])) {
            $val = $this->properties['rotate'];
            $unit = is_numeric($val) ? 'deg' : '';
            $transforms[] = "rotate({$val}{$unit})";
        }
        if (isset($this->properties['rotateX'])) {
            $val = $this->properties['rotateX'];
            $unit = is_numeric($val) ? 'deg' : '';
            $transforms[] = "rotateX({$val}{$unit})";
        }
        if (isset($this->properties['rotateY'])) {
            $val = $this->properties['rotateY'];
            $unit = is_numeric($val) ? 'deg' : '';
            $transforms[] = "rotateY({$val}{$unit})";
        }
        if (isset($this->properties['skewX'])) {
            $val = $this->properties['skewX'];
            $unit = is_numeric($val) ? 'deg' : '';
            $transforms[] = "skewX({$val}{$unit})";
        }
        if (isset($this->properties['skewY'])) {
            $val = $this->properties['skewY'];
            $unit = is_numeric($val) ? 'deg' : '';
            $transforms[] = "skewY({$val}{$unit})";
        }

        if (isset($this->properties['transform'])) {
            $transforms[] = (string)$this->properties['transform'];
        }

        return implode(' ', $transforms);
    }

    /**
     * Generate inline CSS styles for this variant state.
     */
    public function toStyleString(): string
    {
        $rules = [];

        foreach ($this->properties as $key => $value) {
            if (in_array($key, ['x', 'y', 'z', 'scale', 'scaleX', 'scaleY', 'rotate', 'rotateX', 'rotateY', 'skewX', 'skewY', 'transform'], true)) {
                continue;
            }
            $rules[] = sprintf('%s: %s;', $key, (string)$value);
        }

        $transform = $this->toTransformString();
        if ($transform !== '') {
            $rules[] = sprintf('transform: %s;', $transform);
        }

        return implode(' ', $rules);
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'properties' => $this->properties,
            'spring' => $this->spring?->toArray(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
