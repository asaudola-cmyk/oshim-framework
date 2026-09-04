<?php
declare(strict_types=1);

namespace Oshim\Ui\Headless;

use Oshim\Ui\Headless\Support\Aria;
use Oshim\Ui\Headless\Support\FocusManager;
use Oshim\Ui\Headless\Support\KeyboardNavigation;
use Stringable;

/**
 * Headless Accordion Primitive.
 * Implements WAI-ARIA APG Accordion Pattern.
 * Provides single or multiple panel expansion modes, collapsible toggles,
 * header ARIA levels, region accessibility linkages, and full keyboard navigation.
 */
class Accordion extends HeadlessComponent
{
    protected string $type = 'single'; // 'single' or 'multiple'
    protected bool $collapsible = true;
    protected string $orientation = 'vertical';
    protected int $headerLevel = 3;
    protected bool $loop = true;

    /** @var list<string> Expanded item values */
    protected array $expandedValues = [];

    /** @var list<array{value: string, trigger: string|Stringable, content: string|Stringable, disabled: bool, attrs: array, triggerAttrs: array, contentAttrs: array}> */
    protected array $items = [];

    protected function boot(): void
    {
        $this->focusManager = FocusManager::make(false)->loop(true);
        $this->keyboard = KeyboardNavigation::forAccordion();
    }

    public static function make(?string $id = null): self
    {
        return new self($id);
    }

    /**
     * Set accordion expansion type ('single' or 'multiple').
     */
    public function type(string $type = 'single'): static
    {
        $this->type = in_array($type, ['single', 'multiple'], true) ? $type : 'single';
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * In single mode, whether the active open item can be collapsed.
     */
    public function collapsible(bool $collapsible = true): static
    {
        $this->collapsible = $collapsible;
        return $this;
    }

    public function isCollapsible(): bool
    {
        return $this->collapsible;
    }

    public function orientation(string $orientation = 'vertical'): static
    {
        $this->orientation = in_array($orientation, ['horizontal', 'vertical'], true) ? $orientation : 'vertical';
        return $this;
    }

    public function headerLevel(int $level = 3): static
    {
        $this->headerLevel = max(1, min(6, $level));
        return $this;
    }

    public function getHeaderLevel(): int
    {
        return $this->headerLevel;
    }

    public function loop(bool $loop = true): static
    {
        $this->loop = $loop;
        $this->getFocusManager()->loop($loop);
        return $this;
    }

    /**
     * Set currently expanded item value(s).
     *
     * @param string|list<string> $values
     */
    public function value(string|array $values): static
    {
        $this->expandedValues = is_array($values) ? array_values(array_map('strval', $values)) : [(string)$values];
        return $this;
    }

    /**
     * Check if a specific item value is expanded.
     */
    public function isExpanded(string $value): bool
    {
        return in_array($value, $this->expandedValues, true);
    }

    /**
     * Add an accordion item.
     *
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $triggerAttributes
     * @param array<string, mixed> $contentAttributes
     */
    public function item(
        string $value,
        string|Stringable $trigger,
        string|Stringable $content,
        bool $disabled = false,
        array $attributes = [],
        array $triggerAttributes = [],
        array $contentAttributes = []
    ): static {
        $this->items[] = [
            'value'        => $value,
            'trigger'      => $trigger,
            'content'      => $content,
            'disabled'     => $disabled,
            'attrs'        => $attributes,
            'triggerAttrs' => $triggerAttributes,
            'contentAttrs' => $contentAttributes,
        ];
        return $this;
    }

    public function getTriggerId(string $value): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $value) ?: 'item';
        return $this->id . '-trigger-' . $sanitized;
    }

    public function getContentId(string $value): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $value) ?: 'item';
        return $this->id . '-content-' . $sanitized;
    }

    /**
     * Render an individual item with its header, trigger, and region panel.
     *
     * @param array{value: string, trigger: string|Stringable, content: string|Stringable, disabled: bool, attrs: array, triggerAttrs: array, contentAttrs: array} $item
     */
    public function renderItem(array $item, int $index): string
    {
        $val = $item['value'];
        $isOpen = $this->isExpanded($val);
        $stateStr = $isOpen ? 'open' : 'closed';
        $triggerId = $this->getTriggerId($val);
        $contentId = $this->getContentId($val);

        // Container
        $containerAttrs = array_merge([
            'data-state'    => $stateStr,
            'data-disabled' => Aria::boolString($item['disabled']),
            'data-value'    => $val,
            'data-headless-accordion-item' => $val,
        ], $item['attrs']);
        $containerAttrStr = Aria::compile($containerAttrs);

        // Header
        $headerAttrs = [
            'role'       => Aria::ROLE_HEADING,
            'aria-level' => (string)$this->headerLevel,
        ];
        $headerAttrStr = Aria::compile($headerAttrs);

        // Trigger
        $triggerAttrs = array_merge([
            'id'            => $triggerId,
            'type'          => 'button',
            'aria-expanded' => Aria::boolString($isOpen),
            'aria-controls' => $contentId,
            'data-state'    => $stateStr,
            'data-disabled' => Aria::boolString($item['disabled']),
            'data-headless-accordion-trigger' => $val,
            'data-index'    => (string)$index,
        ], $item['triggerAttrs']);

        if ($item['disabled']) {
            $triggerAttrs['disabled'] = true;
            $triggerAttrs['aria-disabled'] = 'true';
        }

        $triggerAttrStr = Aria::compile($triggerAttrs);

        // Content
        $contentAttrs = array_merge([
            'id'              => $contentId,
            'role'            => Aria::ROLE_REGION,
            'aria-labelledby' => $triggerId,
            'data-state'      => $stateStr,
            'data-headless-accordion-content' => $val,
        ], $item['contentAttrs']);

        if (!$isOpen) {
            $contentAttrs['hidden'] = true;
        }

        $contentAttrStr = Aria::compile($contentAttrs);

        $html = "<div{$containerAttrStr}>";
        $html .= "<h{$this->headerLevel}{$headerAttrStr}>";
        $html .= "<button{$triggerAttrStr}>{$item['trigger']}</button>";
        $html .= "</h{$this->headerLevel}>";
        $html .= "<div{$contentAttrStr}>{$item['content']}</div>";
        $html .= '</div>';

        return $html;
    }

    /**
     * Render the complete accordion component.
     */
    public function render(): string
    {
        $rootAttrs = array_merge([
            'id'               => $this->id,
            'data-headless'    => 'accordion',
            'data-type'        => $this->type,
            'data-orientation' => $this->orientation,
            'data-collapsible' => Aria::boolString($this->collapsible),
        ], $this->getKeyboard()->toAttributes(), $this->attributes);

        $rootAttrStr = Aria::compile($rootAttrs);

        $itemsHtml = '';
        foreach ($this->items as $index => $item) {
            $itemsHtml .= $this->renderItem($item, $index);
        }

        return "<div{$rootAttrStr}>{$itemsHtml}</div>";
    }
}
