<?php
declare(strict_types=1);

namespace Oshim\Ui;

use Oshim\Ui\Exceptions\ComponentNotFoundException;

class ComponentRegistry
{
    /** @var array<string, class-string<Component>> */
    protected array $components = [];

    public function __construct()
    {
        $this->registerCoreComponents();
    }

    protected function registerCoreComponents(): void
    {
        $core = [
            'button'       => \Oshim\Ui\Components\Button::class,
            'card'         => \Oshim\Ui\Components\Card::class,
            'table'        => \Oshim\Ui\Components\Table::class,
            'chart'        => \Oshim\Ui\Components\Chart::class,
            'modal'        => \Oshim\Ui\Components\Modal::class,
            'form'         => \Oshim\Ui\Components\Form::class,
            'sidebar'      => \Oshim\Ui\Components\Sidebar::class,
            'navbar'       => \Oshim\Ui\Components\Navbar::class,
            'terminal'     => \Oshim\Ui\Components\Terminal::class,
            'datagrid'     => \Oshim\Ui\Components\DataGrid::class,
            'status-badge' => \Oshim\Ui\Components\StatusBadge::class,
            'statusbadge'  => \Oshim\Ui\Components\StatusBadge::class,
        ];

        foreach ($core as $alias => $class) {
            $this->register($alias, $class);
        }
    }

    public function register(string $name, string $className): static
    {
        $this->components[strtolower($name)] = $className;
        return $this;
    }

    public function has(string $name): bool
    {
        $normalized = strtolower(str_replace(['-', '_'], '', $name));
        $exact = strtolower($name);

        if (isset($this->components[$exact]) || isset($this->components[$normalized])) {
            return true;
        }

        $appClass = 'App\\Components\\' . ucfirst($name);
        return class_exists($appClass) && is_subclass_of($appClass, Component::class);
    }

    public function get(string $name): string
    {
        $exact = strtolower($name);
        if (isset($this->components[$exact])) {
            return $this->components[$exact];
        }

        $normalized = strtolower(str_replace(['-', '_'], '', $name));
        if (isset($this->components[$normalized])) {
            return $this->components[$normalized];
        }

        $appClass = 'App\\Components\\' . ucfirst($name);
        if (class_exists($appClass) && is_subclass_of($appClass, Component::class)) {
            return $appClass;
        }

        throw new ComponentNotFoundException("UI Component [{$name}] is not registered.");
    }

    public function resolve(string $name, array $props = [], ?string $id = null): Component
    {
        /** @var class-string<Component> $class */
        $class = $this->get($name);
        return new $class($props, $id);
    }

    public function all(): array
    {
        return $this->components;
    }
}
