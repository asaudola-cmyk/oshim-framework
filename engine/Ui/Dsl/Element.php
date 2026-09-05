<?php
declare(strict_types=1);

namespace Oshim\Ui\Dsl;

/**
 * 👑 Sovereign OSHIM UI DSL Base Element
 * 
 * WHY: This allows developers to build UI using 100% Object-Oriented PHP.
 * It eliminates the need for messy HTML strings and provides full IDE auto-completion.
 */
class Element
{
    protected string $tag;
    protected array $attributes = [];
    protected array $children = [];
    protected string $text = '';
    protected ?string $textContent = null;
    protected bool $isSelfClosing = false;

    public function __construct(string $tag = 'div')
    {
        $this->tag = $tag;
        $this->isSelfClosing = in_array(strtolower($tag), ['input', 'img', 'br', 'hr', 'meta']);
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

    /**
     * Add one or multiple CSS classes (legacy & modern multi-arg support).
     */
    public function class(string ...$classes): static
    {
        $existing = $this->attributes['class'] ?? '';
        $newClasses = implode(' ', array_filter($classes));
        $this->attributes['class'] = trim($existing . ' ' . $newClasses);
        return $this;
    }

    /**
     * Set CSS classes string (modern LiveDOM fluent convention).
     */
    public function classes(string $classes): static
    {
        $this->attributes['class'] = $classes;
        return $this;
    }

    public function attr(string $key, mixed $value): static
    {
        $this->attributes[$key] = $value;
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
        $this->text = $content;
        return $this;
    }

    public function raw(string $html): static
    {
        $this->children[] = $html;
        return $this;
    }

    public function children(array $children): static
    {
        foreach ($children as $child) {
            $this->child($child);
        }
        return $this;
    }
    
    public function child(Element|string|null $child): static
    {
        if ($child !== null) {
            $this->children[] = $child;
        }
        return $this;
    }

    public function onClick(string $method): static
    {
        $this->attributes['oshim-click'] = $method;
        return $this;
    }

    public function model(string $property): static
    {
        $this->attributes['oshim-model'] = $property;
        return $this;
    }

    public function compile(): string
    {
        return $this->render();
    }

    public function render(): string
    {
        $attrs = '';
        foreach ($this->attributes as $key => $value) {
            if ($value === true) {
                $attrs .= ' ' . htmlspecialchars($key);
            } elseif ($value !== false && $value !== null) {
                $attrs .= " {$key}=\"" . htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\"";
            }
        }
        
        // Self-closing tags
        if (in_array($this->tag, ['input', 'img', 'br', 'hr', 'meta'])) {
            return "<{$this->tag}{$attrs} />";
        }

        $html = "<{$this->tag}{$attrs}>";
        
        if ($this->text !== '') {
            $html .= htmlspecialchars($this->text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        foreach ($this->children as $child) {
            if ($child instanceof Element) {
                $html .= $child->compile();
            } elseif (is_string($child)) {
                $html .= $child;
            }
        }

        $html .= "</{$this->tag}>";
        
        return $html;
    }

    public function __toString(): string
    {
        return $this->render();
    }
}
