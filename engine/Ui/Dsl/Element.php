<?php
declare(strict_types=1);

namespace Oshim\Ui\Dsl;

class Element
{
    protected string $tag = 'div';
    protected array $attributes = [];
    protected array $children = [];
    protected ?string $textContent = null;
    protected bool $isSelfClosing = false;

    public function __construct(string $tag = 'div')
    {
        $this->tag = $tag;
    }

    public static function make(string $tag = 'div'): static
    {
        return new static($tag);
    }

    public function id(string $id): static
    {
        $this->attributes['id'] = $id;
        return $this;
    }

    public function class(string ...$classes): static
    {
        $existing = $this->attributes['class'] ?? '';
        $newClasses = implode(' ', array_filter($classes));
        $this->attributes['class'] = trim($existing . ' ' . $newClasses);
        return $this;
    }

    public function attr(string $name, mixed $value): static
    {
        $this->attributes[$name] = $value;
        return $this;
    }

    public function style(Style|string $style): static
    {
        $existing = $this->attributes['style'] ?? '';
        $styleStr = (string)$style;
        $this->attributes['style'] = trim($existing . ' ' . $styleStr);
        return $this;
    }

    public function text(string $content): static
    {
        $this->textContent = htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return $this;
    }

    public function raw(string $html): static
    {
        $this->textContent = $html;
        return $this;
    }

    public function child(Element|string|null $child): static
    {
        if ($child !== null) {
            $this->children[] = $child;
        }
        return $this;
    }

    public function children(array $children): static
    {
        foreach ($children as $child) {
            $this->child($child);
        }
        return $this;
    }

    public function render(): string
    {
        $attrs = '';
        foreach ($this->attributes as $name => $value) {
            if ($value === true) {
                $attrs .= ' ' . htmlspecialchars($name);
            } elseif ($value !== false && $value !== null) {
                $attrs .= ' ' . htmlspecialchars($name) . '="' . htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
            }
        }

        if ($this->isSelfClosing) {
            return "<{$this->tag}{$attrs} />";
        }

        $inner = '';
        if ($this->textContent !== null) {
            $inner .= $this->textContent;
        }

        foreach ($this->children as $child) {
            if ($child instanceof Element) {
                $inner .= $child->render();
            } elseif (is_string($child)) {
                $inner .= $child;
            }
        }

        return "<{$this->tag}{$attrs}>{$inner}</{$this->tag}>";
    }

    public function __toString(): string
    {
        return $this->render();
    }
}
