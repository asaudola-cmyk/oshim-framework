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

    public function __construct(string $tag)
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

    public function classes(string $classes): static
    {
        $this->attributes['class'] = $classes;
        return $this;
    }

    public function attr(string $key, string $value): static
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    public function text(string $text): static
    {
        $this->text = $text;
        return $this;
    }

    public function children(array $children): static
    {
        $this->children = $children;
        return $this;
    }
    
    public function child(Element $child): static
    {
        $this->children[] = $child;
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
        $html = "<{$this->tag}";
        foreach ($this->attributes as $key => $value) {
            $html .= " {$key}=\"" . htmlspecialchars($value, ENT_QUOTES) . "\"";
        }
        
        // Self-closing tags
        if (in_array($this->tag, ['input', 'img', 'br', 'hr', 'meta'])) {
            return $html . " />";
        }

        $html .= ">";
        
        if ($this->text !== '') {
            $html .= $this->text;
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
}
