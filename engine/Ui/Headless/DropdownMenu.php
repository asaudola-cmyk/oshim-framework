<?php
declare(strict_types=1);

namespace Oshim\Ui\Headless;

use Oshim\Ui\Headless\Support\Aria;
use Oshim\Ui\Headless\Support\FocusManager;
use Oshim\Ui\Headless\Support\KeyboardNavigation;
use Stringable;

/**
 * Headless Dropdown Menu Primitive.
 * Implements WAI-ARIA APG Menu Pattern.
 * Provides roving tabindex focus management, accessible menu items (standard, checkbox, radio),
 * circular keyboard navigation, and escape key closure.
 */
class DropdownMenu extends HeadlessComponent
{
    protected string $orientation = 'vertical';
    protected bool $loop = true;
    protected int $activeIndex = 0;

    // Trigger
    protected string|Stringable|null $triggerContent = null;
    /** @var array<string, mixed> */
    protected array $triggerAttributes = [];

    /** @var list<array{type: string, id: string, label: string|Stringable, attrs: array, disabled: bool, checked?: bool, value?: string, group?: string}> */
    protected array $items = [];

    /** @var array<string, mixed> */
    protected array $contentAttributes = [];

    protected function boot(): void
    {
        $this->focusManager = FocusManager::make(false)
            ->rovingTabindex(true, 0)
            ->restoreFocus(true)
            ->loop(true);
        $this->keyboard = KeyboardNavigation::forDropdownMenu();
    }

    public static function make(?string $id = null): self
    {
        return new self($id);
    }

    public function orientation(string $orientation = 'vertical'): static
    {
        $this->orientation = in_array($orientation, ['horizontal', 'vertical'], true) ? $orientation : 'vertical';
        return $this;
    }

    public function loop(bool $loop = true): static
    {
        $this->loop = $loop;
        $this->getFocusManager()->loop($loop);
        return $this;
    }

    public function activeIndex(int $index): static
    {
        $this->activeIndex = $index;
        $this->getFocusManager()->setActiveIndex($index);
        return $this;
    }

    /**
     * Define the trigger button element.
     *
     * @param array<string, mixed> $attributes
     */
    public function trigger(string|Stringable $content, array $attributes = []): static
    {
        $this->triggerContent = $content;
        $this->triggerAttributes = $attributes;
        return $this;
    }

    /**
     * Add a standard menu item (role="menuitem").
     *
     * @param array<string, mixed> $attributes
     */
    public function item(string $id, string|Stringable $label, array $attributes = [], bool $disabled = false): static
    {
        $this->items[] = [
            'type'     => 'item',
            'id'       => $id,
            'label'    => $label,
            'attrs'    => $attributes,
            'disabled' => $disabled,
        ];
        return $this;
    }

    /**
     * Add a checkbox menu item (role="menuitemcheckbox").
     *
     * @param array<string, mixed> $attributes
     */
    public function checkboxItem(
        string $id,
        string|Stringable $label,
        bool $checked = false,
        array $attributes = [],
        bool $disabled = false
    ): static {
        $this->items[] = [
            'type'     => 'checkbox',
            'id'       => $id,
            'label'    => $label,
            'checked'  => $checked,
            'attrs'    => $attributes,
            'disabled' => $disabled,
        ];
        return $this;
    }

    /**
     * Add a radio group of items (role="menuitemradio").
     *
     * @param list<array{id: string, label: string|Stringable, value: string, disabled?: bool}> $options
     */
    public function radioGroup(string $groupName, string $selectedVal, array $options): static
    {
        foreach ($options as $opt) {
            $this->radioItem(
                group: $groupName,
                id: (string)$opt['id'],
                label: $opt['label'],
                value: (string)$opt['value'],
                checked: ((string)$opt['value'] === $selectedVal),
                disabled: (bool)($opt['disabled'] ?? false)
            );
        }
        return $this;
    }

    /**
     * Add a single radio menu item (role="menuitemradio").
     *
     * @param array<string, mixed> $attributes
     */
    public function radioItem(
        string $group,
        string $id,
        string|Stringable $label,
        string $value,
        bool $checked = false,
        array $attributes = [],
        bool $disabled = false
    ): static {
        $this->items[] = [
            'type'     => 'radio',
            'group'    => $group,
            'id'       => $id,
            'label'    => $label,
            'value'    => $value,
            'checked'  => $checked,
            'attrs'    => $attributes,
            'disabled' => $disabled,
        ];
        return $this;
    }

    /**
     * Add a menu separator line (role="separator").
     *
     * @param array<string, mixed> $attributes
     */
    public function separator(array $attributes = []): static
    {
        $this->items[] = [
            'type'     => 'separator',
            'id'       => self::generateId('sep'),
            'label'    => '',
            'attrs'    => $attributes,
            'disabled' => true,
        ];
        return $this;
    }

    /**
     * Add a non-interactive menu section header/label.
     *
     * @param array<string, mixed> $attributes
     */
    public function label(string|Stringable $text, array $attributes = []): static
    {
        $this->items[] = [
            'type'     => 'label',
            'id'       => self::generateId('lbl'),
            'label'    => $text,
            'attrs'    => $attributes,
            'disabled' => true,
        ];
        return $this;
    }

    public function contentAttributes(array $attributes): static
    {
        $this->contentAttributes = $attributes;
        return $this;
    }

    public function getTriggerId(): string
    {
        return $this->id . '-trigger';
    }

    public function getMenuId(): string
    {
        return $this->id . '-menu';
    }

    /**
     * Render the trigger button.
     */
    public function renderTrigger(array $overrideAttrs = []): string
    {
        if ($this->triggerContent === null) {
            return '';
        }

        $attrs = array_merge([
            'id'            => $this->getTriggerId(),
            'type'          => 'button',
            'aria-haspopup' => 'menu',
            'aria-expanded' => Aria::boolString($this->open),
            'aria-controls' => $this->getMenuId(),
            'data-state'    => $this->open ? 'open' : 'closed',
            'data-headless-trigger' => $this->id,
        ], $this->triggerAttributes, $overrideAttrs);

        $attrStr = Aria::compile($attrs);
        return "<button{$attrStr}>{$this->triggerContent}</button>";
    }

    /**
     * Render the menu items content panel.
     */
    public function renderContent(array $overrideAttrs = []): string
    {
        $attrs = [
            'id'               => $this->getMenuId(),
            'role'             => Aria::ROLE_MENU,
            'aria-orientation' => $this->orientation,
            'aria-labelledby'  => $this->getTriggerId(),
            'tabindex'         => '-1',
            'data-state'       => $this->open ? 'open' : 'closed',
            'data-headless-content' => $this->id,
        ];

        // Merge focus manager and keyboard data attributes
        $attrs = array_merge($attrs, $this->getFocusManager()->toAttributes(), $this->getKeyboard()->toAttributes());

        if (!$this->open) {
            $attrs['hidden'] = true;
        }

        $merged = array_merge($attrs, $this->contentAttributes, $overrideAttrs);
        $attrStr = Aria::compile($merged);

        $interactiveIndex = 0;
        $itemsHtml = '';

        foreach ($this->items as $item) {
            $type = $item['type'];

            if ($type === 'separator') {
                $sepAttrs = array_merge([
                    'role'             => Aria::ROLE_SEPARATOR,
                    'aria-orientation' => 'horizontal',
                ], $item['attrs']);
                $itemsHtml .= '<div' . Aria::compile($sepAttrs) . '></div>';
                continue;
            }

            if ($type === 'label') {
                $lblAttrs = array_merge([
                    'role' => Aria::ROLE_NONE,
                ], $item['attrs']);
                $itemsHtml .= '<div' . Aria::compile($lblAttrs) . ">{$item['label']}</div>";
                continue;
            }

            // Interactive items
            $itemId = $this->id . '-item-' . $item['id'];
            $isHighlighted = ($interactiveIndex === $this->activeIndex);
            $tabindex = $this->getFocusManager()->getItemTabindex($interactiveIndex);

            $itemAttrs = [
                'id'                 => $itemId,
                'tabindex'           => (string)$tabindex,
                'data-highlighted'   => Aria::boolString($isHighlighted),
                'data-disabled'      => Aria::boolString($item['disabled']),
                'aria-disabled'      => Aria::boolString($item['disabled']),
                'data-headless-item' => $item['id'],
                'data-index'         => (string)$interactiveIndex,
            ];

            if ($type === 'item') {
                $itemAttrs['role'] = Aria::ROLE_MENUITEM;
            } elseif ($type === 'checkbox') {
                $itemAttrs['role'] = Aria::ROLE_MENUITEMCHECKBOX;
                $itemAttrs['aria-checked'] = Aria::boolString((bool)($item['checked'] ?? false));
            } elseif ($type === 'radio') {
                $itemAttrs['role'] = Aria::ROLE_MENUITEMRADIO;
                $itemAttrs['aria-checked'] = Aria::boolString((bool)($item['checked'] ?? false));
                $itemAttrs['data-radio-group'] = $item['group'] ?? '';
                $itemAttrs['data-value'] = $item['value'] ?? '';
            }

            $mergedItemAttrs = array_merge($itemAttrs, $item['attrs']);
            $itemsHtml .= '<div' . Aria::compile($mergedItemAttrs) . ">{$item['label']}</div>";

            if (!$item['disabled']) {
                $interactiveIndex++;
            }
        }

        return "<div{$attrStr}>{$itemsHtml}</div>";
    }

    /**
     * Render the complete dropdown menu component.
     */
    public function render(): string
    {
        $rootAttrs = array_merge([
            'id'            => $this->id,
            'data-headless' => 'dropdown-menu',
            'data-state'    => $this->open ? 'open' : 'closed',
        ], $this->attributes);

        $rootAttrStr = Aria::compile($rootAttrs);

        $html = "<div{$rootAttrStr}>";

        if ($this->triggerContent !== null) {
            $html .= $this->renderTrigger();
        }

        $html .= $this->renderContent();
        $html .= '</div>';

        return $html;
    }
}
