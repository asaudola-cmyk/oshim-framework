<?php
declare(strict_types=1);

namespace Oshim\Ui\LiveDom;

use ReflectionClass;
use ReflectionProperty;
use Oshim\Ui\Dsl\Element;

/**
 * 👑 Sovereign OSHIM LiveDOM Component (Option 2 & 3 Edition)
 * 
 * ADVANCED FEATURES:
 * - NO MORE UGLY HTML STRINGS!
 * - render() now strictly returns a Fluent DSL Element.
 * - Supports being the target of the PHP-JSX Transpiler.
 */
abstract class Component
{
    public string $id;
    protected bool $isMounted = false;

    public function __construct(string $id = null)
    {
        $this->id = $id ?? 'comp_' . bin2hex(random_bytes(6));
    }

    public function mount(): void {}
    public function updating(string $property, $value): void {}
    public function updated(string $property, $value): void {}

    /**
     * 🚀 NEW ARCHITECTURE: Must return a DSL Element instead of an HTML string.
     */
    abstract public function render(): Element;

    public function getState(): array
    {
        $state = [];
        $reflection = new ReflectionClass($this);
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            if ($name !== 'id') {
                $state[$name] = $this->{$name};
            }
        }
        return $state;
    }

    public function hydrate(array $state): void
    {
        foreach ($state as $key => $value) {
            if (property_exists($this, $key)) {
                $this->updating($key, $value);
                $this->{$key} = $value;
                $this->updated($key, $value);
            }
        }
    }

    public function compile(): string
    {
        if (!$this->isMounted) {
            $this->mount();
            $this->isMounted = true;
        }

        // 1. Get the Fluent DSL Element Tree
        $elementTree = $this->render();
        
        $stateJson = htmlspecialchars(json_encode($this->getState()), ENT_QUOTES, 'UTF-8');
        $componentName = (new ReflectionClass($this))->getShortName();

        // 2. Inject tracking attributes to the Root Element
        $elementTree->id($this->id)
                   ->attr('oshim-component', $componentName)
                   ->attr('oshim-state', $stateJson);

        // 3. Compile the DSL to HTML string internally for the browser
        return $elementTree->compile();
    }
}
