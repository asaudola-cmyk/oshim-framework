<?php
declare(strict_types=1);

namespace Oshim\Ui\LiveDom;

use ReflectionClass;
use ReflectionProperty;

/**
 * 👑 Sovereign OSHIM LiveDOM Component
 * 
 * WHY: This gives developers the exact "React" feel in pure PHP.
 * Developers extend this class, define public properties (State), 
 * methods (Actions), and a render() method returning HTML.
 */
abstract class Component
{
    public string $id;

    public function __construct(string $id = null)
    {
        $this->id = $id ?? 'comp_' . bin2hex(random_bytes(4));
    }

    /**
     * React-style Mount lifecycle hook
     */
    public function mount(): void
    {
        // Override to initialize state
    }

    /**
     * Must return the HTML structure (React's render equivalent)
     */
    abstract public function render(): string;

    /**
     * Extracts all public properties to represent the "State"
     */
    public function getState(): array
    {
        $state = [];
        $reflection = new ReflectionClass($this);
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            // Don't sync internal ID as a normal state variable
            if ($name !== 'id') {
                $state[$name] = $this->{$name};
            }
        }
        return $state;
    }

    /**
     * Hydrates the component with incoming state from the WebSocket client
     */
    public function hydrate(array $state): void
    {
        foreach ($state as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    /**
     * Compiles the component into the final Morphable DOM Node
     */
    public function compile(): string
    {
        $html = trim($this->render());
        $stateJson = htmlspecialchars(json_encode($this->getState()), ENT_QUOTES, 'UTF-8');
        $componentName = (new ReflectionClass($this))->getShortName();

        // Wrap the HTML with tracking attributes required by oshim-livedom.js
        // We inject them into the very first HTML tag of the render output.
        $wrapped = preg_replace(
            '/^<([a-zA-Z0-9\-]+)([^>]*)>/',
            "<$1 id=\"{$this->id}\" oshim-component=\"{$componentName}\" oshim-state=\"{$stateJson}\"$2>",
            $html,
            1
        );

        return $wrapped;
    }
}
