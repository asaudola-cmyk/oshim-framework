<?php
declare(strict_types=1);

namespace Oshim\Ui\Dsl;

class Style
{
    private array $rules = [];

    public static function make(): self
    {
        return new self();
    }

    public function set(string $property, string $value): self
    {
        $this->rules[$property] = $value;
        return $this;
    }

    public function bg(string $value): self
    {
        return $this->set('background', $value);
    }

    public function color(string $value): self
    {
        return $this->set('color', $value);
    }

    public function padding(string $value): self
    {
        return $this->set('padding', $value);
    }

    public function margin(string $value): self
    {
        return $this->set('margin', $value);
    }

    public function display(string $value): self
    {
        return $this->set('display', $value);
    }

    public function flex(string $direction = 'row', string $align = 'center', string $justify = 'space-between', string $gap = '0'): self
    {
        $this->display('flex');
        $this->set('flex-direction', $direction);
        $this->set('align-items', $align);
        $this->set('justify-content', $justify);
        if ($gap !== '0') {
            $this->set('gap', $gap);
        }
        return $this;
    }

    public function grid(string $columns = 'repeat(auto-fit, minmax(280px, 1fr))', string $gap = '1.5rem'): self
    {
        $this->display('grid');
        $this->set('grid-template-columns', $columns);
        $this->set('gap', $gap);
        return $this;
    }

    public function border(string $value): self
    {
        return $this->set('border', $value);
    }

    public function radius(string $value): self
    {
        return $this->set('border-radius', $value);
    }

    public function width(string $value): self
    {
        return $this->set('width', $value);
    }

    public function height(string $value): self
    {
        return $this->set('height', $value);
    }

    public function font(string $size, string $weight = 'normal'): self
    {
        $this->set('font-size', $size);
        $this->set('font-weight', $weight);
        return $this;
    }

    public function render(): string
    {
        $css = '';
        foreach ($this->rules as $prop => $val) {
            $css .= "{$prop}: {$val}; ";
        }
        return trim($css);
    }

    public function __toString(): string
    {
        return $this->render();
    }
}
