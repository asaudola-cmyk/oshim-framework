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
     * Allows: $el->p(4)->bg('red-500')->textWhite()->flex()->hoverBg('blue-600')
     * 
     * WHY: Eliminates manual string concatenation for Tailwind utility classes.
     * Maps camelCase method names and parameters directly into valid Tailwind classes.
     */
    public function __call(string $name, array $arguments): static
    {
        // 1. Convert camelCase to kebab-case, inserting hyphens before capital letters and digit clusters
        // WHY: Handles standard utility flags like textWhite -> text-white AND scale indicators like text2xl -> text-2xl
        $class = strtolower((string)preg_replace('/(?<!^)(?:[A-Z]|\d+)/', '-$0', $name));
        
        // 2. Map standard Tailwind pseudo-class & responsive prefixes from hyphens to colons
        $prefixes = ['hover-', 'focus-', 'active-', 'disabled-', 'sm-', 'md-', 'lg-', 'xl-', '2xl-', 'dark-'];
        foreach ($prefixes as $prefix) {
            if (str_starts_with($class, $prefix)) {
                $replacement = substr($prefix, 0, -1) . ':';
                $class = $replacement . substr($class, strlen($prefix));
                break;
            }
        }
        
        // 3. Handle zero-argument utility flags vs parameterized utilities
        if (empty($arguments)) {
            $this->classes[] = $class;
        } else {
            $val = (string)$arguments[0];
            $this->classes[] = "{$class}-{$val}";
        }
        
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
