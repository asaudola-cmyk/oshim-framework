<?php
declare(strict_types=1);

namespace Oshim\Ui\Headless;

use Oshim\Ui\Headless\Support\Aria;
use Oshim\Ui\Headless\Support\FocusManager;
use Oshim\Ui\Headless\Support\KeyboardNavigation;
use Stringable;

/**
 * Headless Combobox (Autocomplete / Select with Search) Primitive.
 * Implements WAI-ARIA APG Combobox Pattern.
 * Provides virtual focus management via aria-activedescendant, live query filtering,
 * accessible options, groups, and keyboard selection semantics.
 */
class Combobox extends HeadlessComponent
{
    protected string $autocomplete = 'list';
    protected ?string $selectedValue = null;
    protected ?string $activeOptionId = null;
    protected ?string $searchQuery = null;

    // Sub-elements
    protected string $inputPlaceholder = '';
    protected ?string $inputValue = null;
    /** @var array<string, mixed> */
    protected array $inputAttributes = [];

    protected string|Stringable|null $triggerContent = null;
    /** @var array<string, mixed> */
    protected array $triggerAttributes = [];

    /** @var list<array{value: string, label: string|Stringable, disabled: bool, group?: string, attrs: array}> */
    protected array $options = [];

    protected string|Stringable|null $emptyMessage = 'No results found.';
    /** @var array<string, mixed> */
    protected array $emptyAttributes = [];

    /** @var array<string, mixed> */
    protected array $listboxAttributes = [];

    protected function boot(): void
    {
        $this->focusManager = FocusManager::make(false)->restoreFocus(true);
        $this->keyboard = KeyboardNavigation::forCombobox();
    }

    public static function make(?string $id = null): self
    {
        return new self($id);
    }

    public function autocomplete(string $type = 'list'): static
    {
        $this->autocomplete = in_array($type, ['list', 'both', 'none', 'inline'], true) ? $type : 'list';
        return $this;
    }

    public function selected(?string $value): static
    {
        $this->selectedValue = $value;
        return $this;
    }

    public function getSelected(): ?string
    {
        return $this->selectedValue;
    }

    public function activeDescendant(?string $optionId): static
    {
        $this->activeOptionId = $optionId;
        return $this;
    }

    public function getActiveDescendant(): ?string
    {
        return $this->activeOptionId;
    }

    public function query(?string $search): static
    {
        $this->searchQuery = $search;
        return $this;
    }

    /**
     * Configure text input field (role="combobox").
     *
     * @param array<string, mixed> $attributes
     */
    public function input(string $placeholder = '', ?string $value = null, array $attributes = []): static
    {
        $this->inputPlaceholder = $placeholder;
        $this->inputValue = $value;
        $this->inputAttributes = $attributes;
        return $this;
    }

    /**
     * Configure the trigger toggle button (e.g. chevron).
     *
     * @param array<string, mixed> $attributes
     */
    public function trigger(string|Stringable $content = '▼', array $attributes = []): static
    {
        $this->triggerContent = $content;
        $this->triggerAttributes = $attributes;
        return $this;
    }

    /**
     * Add a single selectable option (role="option").
     *
     * @param array<string, mixed> $attributes
     */
    public function option(
        string $value,
        string|Stringable $label,
        bool $disabled = false,
        ?string $group = null,
        array $attributes = []
    ): static {
        $this->options[] = [
            'value'    => $value,
            'label'    => $label,
            'disabled' => $disabled,
            'group'    => $group,
            'attrs'    => $attributes,
        ];
        return $this;
    }

    /**
     * Bulk add array of options.
     *
     * @param array<string, string|Stringable>|list<array{value: string, label: string|Stringable, disabled?: bool}> $options
     */
    public function options(array $options, ?string $selectedValue = null): static
    {
        if ($selectedValue !== null) {
            $this->selectedValue = $selectedValue;
        }

        foreach ($options as $key => $val) {
            if (is_array($val)) {
                $value = (string)($val['value'] ?? $key);
                $label = $val['label'] ?? $value;
                $disabled = (bool)($val['disabled'] ?? false);
                $this->option($value, $label, $disabled);
            } else {
                $this->option((string)$key, $val);
            }
        }

        return $this;
    }

    /**
     * Add an option group.
     *
     * @param array<string, string|Stringable> $options
     */
    public function group(string $heading, array $options): static
    {
        foreach ($options as $value => $label) {
            $this->option((string)$value, $label, false, $heading);
        }
        return $this;
    }

    /**
     * Define the empty state content when no options match search.
     *
     * @param array<string, mixed> $attributes
     */
    public function empty(string|Stringable $message = 'No results found.', array $attributes = []): static
    {
        $this->emptyMessage = $message;
        $this->emptyAttributes = $attributes;
        return $this;
    }

    public function listboxAttributes(array $attributes): static
    {
        $this->listboxAttributes = $attributes;
        return $this;
    }

    public function getInputId(): string
    {
        return $this->id . '-input';
    }

    public function getListboxId(): string
    {
        return $this->id . '-listbox';
    }

    public function getTriggerId(): string
    {
        return $this->id . '-trigger';
    }

    public function getOptionId(string $value): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $value) ?: 'opt';
        return $this->id . '-opt-' . $sanitized;
    }

    /**
     * Render the input textbox.
     */
    public function renderInput(array $overrideAttrs = []): string
    {
        $attrs = [
            'id'                 => $this->getInputId(),
            'type'               => 'text',
            'role'               => Aria::ROLE_COMBOBOX,
            'aria-autocomplete'  => $this->autocomplete,
            'aria-expanded'      => Aria::boolString($this->open),
            'aria-haspopup'      => 'listbox',
            'aria-controls'      => $this->getListboxId(),
            'data-headless-input'=> $this->id,
            'autocomplete'       => 'off',
        ];

        if ($this->inputPlaceholder !== '') {
            $attrs['placeholder'] = $this->inputPlaceholder;
        }

        if ($this->inputValue !== null) {
            $attrs['value'] = $this->inputValue;
        }

        if ($this->activeOptionId !== null) {
            $attrs['aria-activedescendant'] = $this->activeOptionId;
        }

        $merged = array_merge($attrs, $this->inputAttributes, $this->getKeyboard()->toAttributes(), $overrideAttrs);
        $attrStr = Aria::compile($merged);

        return "<input{$attrStr}/>";
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
            'tabindex'      => '-1',
            'aria-haspopup' => 'listbox',
            'aria-expanded' => Aria::boolString($this->open),
            'aria-controls' => $this->getListboxId(),
            'data-headless-trigger' => $this->id,
        ], $this->triggerAttributes, $overrideAttrs);

        $attrStr = Aria::compile($attrs);
        return "<button{$attrStr}>{$this->triggerContent}</button>";
    }

    /**
     * Render the options listbox.
     */
    public function renderContent(array $overrideAttrs = []): string
    {
        $attrs = [
            'id'              => $this->getListboxId(),
            'role'            => Aria::ROLE_LISTBOX,
            'aria-labelledby' => $this->getInputId(),
            'tabindex'        => '-1',
            'data-state'      => $this->open ? 'open' : 'closed',
            'data-headless-content' => $this->id,
        ];

        if (!$this->open) {
            $attrs['hidden'] = true;
        }

        $merged = array_merge($attrs, $this->listboxAttributes, $overrideAttrs);
        $attrStr = Aria::compile($merged);

        // Filter options if searchQuery is provided
        $filteredOptions = $this->options;
        if ($this->searchQuery !== null && trim($this->searchQuery) !== '') {
            $needle = mb_strtolower(trim($this->searchQuery));
            $filteredOptions = array_filter($this->options, function ($opt) use ($needle) {
                $lbl = mb_strtolower((string)$opt['label']);
                $val = mb_strtolower((string)$opt['value']);
                return str_contains($lbl, $needle) || str_contains($val, $needle);
            });
        }

        if (empty($filteredOptions)) {
            $emptyAttrs = array_merge([
                'role'                => Aria::ROLE_PRESENTATION,
                'data-headless-empty' => 'true',
            ], $this->emptyAttributes);
            return "<div{$attrStr}><div" . Aria::compile($emptyAttrs) . ">{$this->emptyMessage}</div></div>";
        }

        // Group options if applicable
        $groups = [];
        foreach ($filteredOptions as $opt) {
            $groupName = $opt['group'] ?? '';
            $groups[$groupName][] = $opt;
        }

        $optionsHtml = '';
        foreach ($groups as $groupName => $items) {
            $groupContent = '';
            foreach ($items as $opt) {
                $val = $opt['value'];
                $optId = $this->getOptionId($val);
                $isSelected = ($this->selectedValue !== null && (string)$this->selectedValue === (string)$val);
                $isHighlighted = ($this->activeOptionId === $optId);

                $optAttrs = [
                    'id'                  => $optId,
                    'role'                => Aria::ROLE_OPTION,
                    'aria-selected'       => Aria::boolString($isSelected),
                    'aria-disabled'       => Aria::boolString($opt['disabled']),
                    'data-disabled'       => Aria::boolString($opt['disabled']),
                    'data-highlighted'    => Aria::boolString($isHighlighted),
                    'data-value'          => $val,
                    'data-headless-option'=> $val,
                ];

                $mergedOptAttrs = array_merge($optAttrs, $opt['attrs']);
                $groupContent .= '<div' . Aria::compile($mergedOptAttrs) . ">{$opt['label']}</div>";
            }

            if ($groupName !== '') {
                $grpId = $this->id . '-grp-' . md5($groupName);
                $optionsHtml .= "<div role=\"" . Aria::ROLE_GROUP . "\" aria-labelledby=\"{$grpId}\">";
                $optionsHtml .= "<div id=\"{$grpId}\" role=\"" . Aria::ROLE_PRESENTATION . "\">" . htmlspecialchars($groupName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
                $optionsHtml .= $groupContent;
                $optionsHtml .= '</div>';
            } else {
                $optionsHtml .= $groupContent;
            }
        }

        return "<div{$attrStr}>{$optionsHtml}</div>";
    }

    /**
     * Render the complete combobox component.
     */
    public function render(): string
    {
        $rootAttrs = array_merge([
            'id'            => $this->id,
            'data-headless' => 'combobox',
            'data-state'    => $this->open ? 'open' : 'closed',
        ], $this->attributes);

        $rootAttrStr = Aria::compile($rootAttrs);

        $html = "<div{$rootAttrStr}>";
        $html .= $this->renderInput();

        if ($this->triggerContent !== null) {
            $html .= $this->renderTrigger();
        }

        $html .= $this->renderContent();
        $html .= '</div>';

        return $html;
    }
}
