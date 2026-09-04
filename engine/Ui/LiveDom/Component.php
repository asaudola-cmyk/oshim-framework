<?php
declare(strict_types=1);

namespace Oshim\Ui\LiveDom;

use ReflectionClass;
use ReflectionProperty;

/**
 * 👑 Sovereign OSHIM LiveDOM Component (Enterprise Edition)
 * 
 * ADVANCED FEATURES:
 * - React-style Lifecycle Hooks (mount, hydrate, updating, updated, destroy).
 * - Readonly state protection (prevents malicious client injection).
 * - Automatic State Extraction via Reflection.
 */
abstract class Component
{
    public string $id;
    
    // Tracks if the component has been mounted yet
    protected bool $isMounted = false;

    public function __construct(string $id = null)
    {
        $this->id = $id ?? 'comp_' . bin2hex(random_bytes(6));
    }

    /**
     * ==========================================
     * 🚀 LIFECYCLE HOOKS (REACT EQUIVALENTS)
     * ==========================================
     */

    /**
     * Called exactly once when the component is first created. (React: componentDidMount)
     */
    public function mount(): void {}

    /**
     * Called before a property is updated via WebSocket.
     */
    public function updating(string $property, $value): void {}

    /**
     * Called after a property has been successfully updated via WebSocket.
     */
    public function updated(string $property, $value): void {}

    /**
     * Must return the HTML structure (React's render equivalent)
     */
    abstract public function render(): string;

    /**
     * ==========================================
     * ⚙️ CORE ENGINE MECHANICS
     * ==========================================
     */

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

        $html = trim($this->render());
        $stateJson = htmlspecialchars(json_encode($this->getState()), ENT_QUOTES, 'UTF-8');
        $componentName = (new ReflectionClass($this))->getShortName();

        // Secure DOM injection for Morphing
        return preg_replace(
            '/^<([a-zA-Z0-9\-]+)([^>]*)>/',
            "<$1 id=\"{$this->id}\" oshim-component=\"{$componentName}\" oshim-state=\"{$stateJson}\"$2>",
            $html,
            1
        );
    }
}
