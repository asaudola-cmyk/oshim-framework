<?php
declare(strict_types=1);

namespace Oshim\Ui\Dsl;

/**
 * 👑 Sovereign OSHIM UI DSL Base Element (Tailwind & Signal Edition)
 * 
 * ADVANCED: Supports Signals for Fine-Grained Reactivity.
 * ADVANCED: Magic Tailwind Fluent Builder. 
 * Instead of ->classes('p-4 bg-red-500'), write: ->p(4)->bg('red-500')->flex()
 */
class Element
{
    protected string $tag;
    protected array $attributes = [];
    protected array $classes = [];
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

    public function classes(string ...$classes): static
    {
        foreach ($classes as $class) {
            $this->classes = array_merge($this->classes, explode(' ', $class));
        }
        return $this;
    }

    public function attr(string $key, string $value): static
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    /**
     * Accepts static strings OR Reactive Signals!
     */
    public function text(string|Signal $text): static
    {
        // If it's a Signal, we can bind it natively
        if ($text instanceof Signal) {
            $this->attr('oshim-bind', 'true');
        }
        $this->text = (string)$text;
        return $this;
    }

    public function children(array $children): static
    {
        $this->children = $children;
        return $this;
    }
    
    public function child(Element|string|Signal $child): static
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

    /**
     * 🚀 MAGIC TAILWIND BUILDER
     * Allows: $el->p(4)->bg('red-500')->textWhite()->flex()
     */
        public function __call(string $name, array $arguments): static
    {
        // Convert camelCase to kebab-case
        $class = strtolower(preg_replace('/(?<!^)[A-Z]/', '-public function __call(string $name, array $arguments): static
    {
        // Handle utility methods without arguments e.g., ->flex(), ->relative()
        if (empty($arguments)) {
            // Convert camelCase to kebab-case (e.g., textWhite -> text-white)
            $class = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $name));
            $this->classes[] = $class;
            return $this;
        }', $name));
        
        // Handle Tailwind modifiers (e.g., hoverBg -> hover:bg)
        $class = str_replace('hover-', 'hover:', $class);
        $class = str_replace('focus-', 'focus:', $class);
        $class = str_replace('md-', 'md:', $class);
        $class = str_replace('lg-', 'lg:', $class);
        
        if (empty($arguments)) {
            $this->classes[] = $class;
        } else {
            $value = $arguments[0];
            $this->classes[] = "{$class}-{$value}";
        }
        
        return $this;
    }

        // Handle parameterized methods e.g., ->p(4) => 'p-4', ->bg('red-500') => 'bg-red-500'
        $value = $arguments[0];
        $this->classes[] = "{$name}-{$value}";
        
        return $this;
    }

    public function compile(): string
    {
        $html = "<{$this->tag}";
        
        // Compile classes
        if (!empty($this->classes)) {
            $classString = implode(' ', array_unique($this->classes));
            $html .= " class=\"" . htmlspecialchars($classString, ENT_QUOTES) . "\"";
        }

        // Compile attributes
        foreach ($this->attributes as $key => $value) {
            $html .= " {$key}=\"" . htmlspecialchars((string)$value, ENT_QUOTES) . "\"";
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
            } elseif ($child instanceof Signal) {
                $html .= "<span oshim-signal>" . (string)$child->get() . "</span>";
            } elseif (is_string($child)) {
                $html .= $child;
            }
        }

        $html .= "</{$this->tag}>";
        
        return $html;
    }
}
