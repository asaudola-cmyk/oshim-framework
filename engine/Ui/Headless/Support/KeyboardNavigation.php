<?php
declare(strict_types=1);

namespace Oshim\Ui\Headless\Support;

/**
 * Keyboard Navigation Semantics according to W3C WAI-ARIA APG patterns.
 * Defines standard key-action maps, shortcut bindings, and event serialization.
 */
class KeyboardNavigation
{
    // Standard DOM Key values
    public const KEY_ESCAPE = 'Escape';
    public const KEY_ENTER = 'Enter';
    public const KEY_SPACE = ' ';
    public const KEY_TAB = 'Tab';
    public const KEY_ARROW_UP = 'ArrowUp';
    public const KEY_ARROW_DOWN = 'ArrowDown';
    public const KEY_ARROW_LEFT = 'ArrowLeft';
    public const KEY_ARROW_RIGHT = 'ArrowRight';
    public const KEY_HOME = 'Home';
    public const KEY_END = 'End';
    public const KEY_PAGE_UP = 'PageUp';
    public const KEY_PAGE_DOWN = 'PageDown';

    // Standard Semantic Actions
    public const ACTION_OPEN = 'open';
    public const ACTION_CLOSE = 'close';
    public const ACTION_TOGGLE = 'toggle';
    public const ACTION_SELECT = 'select';
    public const ACTION_FIRST = 'first';
    public const ACTION_LAST = 'last';
    public const ACTION_NEXT = 'next';
    public const ACTION_PREV = 'prev';

    /** @var array<string, string> */
    protected array $keyBindings = [];

    public function __construct(array $bindings = [])
    {
        $this->keyBindings = $bindings;
    }

    public static function make(array $bindings = []): self
    {
        return new self($bindings);
    }

    public function bind(string $key, string $action): static
    {
        $this->keyBindings[$key] = $action;
        return $this;
    }

    public function getBindings(): array
    {
        return $this->keyBindings;
    }

    /**
     * WAI-ARIA APG Keyboard Contract for Dialog / Modal.
     */
    public static function forDialog(): self
    {
        return new self([
            self::KEY_ESCAPE => self::ACTION_CLOSE,
        ]);
    }

    /**
     * WAI-ARIA APG Keyboard Contract for DropdownMenu.
     */
    public static function forDropdownMenu(): self
    {
        return new self([
            self::KEY_ARROW_DOWN => self::ACTION_NEXT,
            self::KEY_ARROW_UP => self::ACTION_PREV,
            self::KEY_HOME => self::ACTION_FIRST,
            self::KEY_END => self::ACTION_LAST,
            self::KEY_ENTER => self::ACTION_SELECT,
            self::KEY_SPACE => self::ACTION_SELECT,
            self::KEY_ESCAPE => self::ACTION_CLOSE,
        ]);
    }

    /**
     * WAI-ARIA APG Keyboard Contract for Combobox.
     */
    public static function forCombobox(): self
    {
        return new self([
            self::KEY_ARROW_DOWN => self::ACTION_NEXT,
            self::KEY_ARROW_UP => self::ACTION_PREV,
            self::KEY_ENTER => self::ACTION_SELECT,
            self::KEY_ESCAPE => self::ACTION_CLOSE,
            self::KEY_HOME => self::ACTION_FIRST,
            self::KEY_END => self::ACTION_LAST,
        ]);
    }

    /**
     * WAI-ARIA APG Keyboard Contract for Popover.
     */
    public static function forPopover(): self
    {
        return new self([
            self::KEY_ESCAPE => self::ACTION_CLOSE,
        ]);
    }

    /**
     * WAI-ARIA APG Keyboard Contract for Accordion.
     */
    public static function forAccordion(): self
    {
        return new self([
            self::KEY_ARROW_DOWN => self::ACTION_NEXT,
            self::KEY_ARROW_UP => self::ACTION_PREV,
            self::KEY_HOME => self::ACTION_FIRST,
            self::KEY_END => self::ACTION_LAST,
            self::KEY_ENTER => self::ACTION_TOGGLE,
            self::KEY_SPACE => self::ACTION_TOGGLE,
        ]);
    }

    /**
     * Returns JSON representation of key bindings for client DOM data attributes.
     */
    public function toJson(): string
    {
        return json_encode($this->keyBindings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    /**
     * Returns data attribute array.
     *
     * @return array<string, string>
     */
    public function toAttributes(): array
    {
        return [
            'data-headless-keyboard' => $this->toJson(),
        ];
    }
}
