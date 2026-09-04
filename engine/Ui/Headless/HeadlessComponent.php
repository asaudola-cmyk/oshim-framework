<?php
declare(strict_types=1);

namespace Oshim\Ui\Headless;

use Oshim\Ui\Headless\Support\Aria;
use Oshim\Ui\Headless\Support\FocusManager;
use Oshim\Ui\Headless\Support\KeyboardNavigation;
use Stringable;

/**
 * Base Abstract Class for All Headless UI Primitives.
 * Provides unstyled accessible foundation, state management, ARIA compilation,
 * focus trapping contracts, and keyboard navigation semantics.
 */
abstract class HeadlessComponent implements Stringable
{
    protected string $id;
    protected bool $open = false;
    /** @var array<string, mixed> */
    protected array $attributes = [];
    protected ?FocusManager $focusManager = null;
    protected ?KeyboardNavigation $keyboard = null;

    public function __construct(?string $id = null)
    {
        $this->id = $id ?? self::generateId(strtolower((new \ReflectionClass($this))->getShortName()));
        $this->boot();
    }

    /**
     * Component boot hook to initialize default focus and keyboard contracts.
     */
    protected function boot(): void
    {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function id(string $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function isOpen(): bool
    {
        return $this->open;
    }

    public function open(bool $open = true): static
    {
        $this->open = $open;
        return $this;
    }

    public function close(): static
    {
        $this->open = false;
        return $this;
    }

    public function toggle(): static
    {
        $this->open = !$this->open;
        return $this;
    }

    public function attr(string $name, mixed $value): static
    {
        $this->attributes[$name] = $value;
        return $this;
    }

    public function class(string ...$classes): static
    {
        $existing = $this->attributes['class'] ?? '';
        $new = implode(' ', array_filter($classes));
        $this->attributes['class'] = trim($existing . ' ' . $new);
        return $this;
    }

    public function aria(string $name, mixed $value): static
    {
        $key = str_starts_with($name, 'aria-') ? $name : 'aria-' . $name;
        $this->attributes[$key] = $value;
        return $this;
    }

    public function data(string $name, mixed $value): static
    {
        $key = str_starts_with($name, 'data-') ? $name : 'data-' . $name;
        $this->attributes[$key] = $value;
        return $this;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getFocusManager(): FocusManager
    {
        if ($this->focusManager === null) {
            $this->focusManager = new FocusManager();
        }
        return $this->focusManager;
    }

    public function getKeyboard(): KeyboardNavigation
    {
        if ($this->keyboard === null) {
            $this->keyboard = new KeyboardNavigation();
        }
        return $this->keyboard;
    }

    /**
     * Render compiled attributes including base attributes, focus contracts, and overrides.
     */
    public function renderAttributes(array $overrides = []): string
    {
        $merged = array_merge($this->attributes, $overrides);
        return Aria::compile($merged);
    }

    protected static function generateId(string $prefix = 'hl'): string
    {
        return $prefix . '_' . substr(bin2hex(random_bytes(6)), 0, 8);
    }

    public function __toString(): string
    {
        return $this->render();
    }

    abstract public function render(): string;
}
