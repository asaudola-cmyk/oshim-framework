<?php
declare(strict_types=1);

namespace Oshim\Ui\Headless;

use Oshim\Ui\Headless\Support\Aria;
use Oshim\Ui\Headless\Support\FocusManager;
use Oshim\Ui\Headless\Support\KeyboardNavigation;
use Stringable;

/**
 * Headless Popover Primitive.
 * Implements WAI-ARIA APG Non-modal Dialog / Disclosure Pattern.
 * Provides accessible positioning semantics (side, align, offset), light dismiss,
 * focus return, and escape key closure.
 */
class Popover extends HeadlessComponent
{
    protected string $side = 'bottom';
    protected string $align = 'center';
    protected int $sideOffset = 4;
    protected int $alignOffset = 0;
    protected bool $closeOnEscape = true;
    protected bool $closeOnOutsideClick = true;

    // Sub-elements
    protected string|Stringable|null $triggerContent = null;
    /** @var array<string, mixed> */
    protected array $triggerAttributes = [];

    protected string|Stringable|null $anchorContent = null;
    /** @var array<string, mixed> */
    protected array $anchorAttributes = [];

    /** @var list<string|Stringable> */
    protected array $contentChildren = [];
    /** @var array<string, mixed> */
    protected array $contentAttributes = [];

    protected string|Stringable|null $closeButtonContent = null;
    /** @var array<string, mixed> */
    protected array $closeButtonAttributes = [];

    protected bool $hasArrow = false;
    /** @var array<string, mixed> */
    protected array $arrowAttributes = [];

    protected function boot(): void
    {
        $this->focusManager = FocusManager::make(false)->restoreFocus(true);
        $this->keyboard = KeyboardNavigation::forPopover();
    }

    public static function make(?string $id = null): self
    {
        return new self($id);
    }

    /**
     * Configure side placement (top, bottom, left, right).
     */
    public function side(string $side): static
    {
        $this->side = in_array($side, ['top', 'bottom', 'left', 'right'], true) ? $side : 'bottom';
        return $this;
    }

    public function getSide(): string
    {
        return $this->side;
    }

    /**
     * Configure alignment (start, center, end).
     */
    public function align(string $align): static
    {
        $this->align = in_array($align, ['start', 'center', 'end'], true) ? $align : 'center';
        return $this;
    }

    public function getAlign(): string
    {
        return $this->align;
    }

    /**
     * Configure placement offsets.
     */
    public function offset(int $sideOffset = 4, int $alignOffset = 0): static
    {
        $this->sideOffset = $sideOffset;
        $this->alignOffset = $alignOffset;
        return $this;
    }

    public function closeOnEscape(bool $close = true): static
    {
        $this->closeOnEscape = $close;
        if (!$close) {
            $this->keyboard = KeyboardNavigation::make();
        } else {
            $this->keyboard = KeyboardNavigation::forPopover();
        }
        return $this;
    }

    public function closeOnOutsideClick(bool $close = true): static
    {
        $this->closeOnOutsideClick = $close;
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
     * Define a separate anchor element for positioning if distinct from trigger.
     *
     * @param array<string, mixed> $attributes
     */
    public function anchor(string|Stringable $content, array $attributes = []): static
    {
        $this->anchorContent = $content;
        $this->anchorAttributes = $attributes;
        return $this;
    }

    /**
     * Add content elements or child markup into popover panel.
     */
    public function content(string|Stringable ...$children): static
    {
        $this->contentChildren = array_values($children);
        return $this;
    }

    public function contentAttributes(array $attributes): static
    {
        $this->contentAttributes = $attributes;
        return $this;
    }

    /**
     * Configure a close button inside the popover.
     *
     * @param array<string, mixed> $attributes
     */
    public function closeButton(string|Stringable $content = 'Close', array $attributes = []): static
    {
        $this->closeButtonContent = $content;
        $this->closeButtonAttributes = $attributes;
        return $this;
    }

    /**
     * Enable arrow pointer element.
     *
     * @param array<string, mixed> $attributes
     */
    public function arrow(bool $enable = true, array $attributes = []): static
    {
        $this->hasArrow = $enable;
        $this->arrowAttributes = $attributes;
        return $this;
    }

    public function getTriggerId(): string
    {
        return $this->id . '-trigger';
    }

    public function getContentId(): string
    {
        return $this->id . '-content';
    }

    public function getAnchorId(): string
    {
        return $this->id . '-anchor';
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
            'aria-haspopup' => 'dialog',
            'aria-expanded' => Aria::boolString($this->open),
            'aria-controls' => $this->getContentId(),
            'data-state'    => $this->open ? 'open' : 'closed',
            'data-headless-trigger' => $this->id,
        ], $this->triggerAttributes, $overrideAttrs);

        $attrStr = Aria::compile($attrs);
        return "<button{$attrStr}>{$this->triggerContent}</button>";
    }

    /**
     * Render the anchor element if defined.
     */
    public function renderAnchor(array $overrideAttrs = []): string
    {
        if ($this->anchorContent === null) {
            return '';
        }

        $attrs = array_merge([
            'id'                  => $this->getAnchorId(),
            'data-headless-anchor'=> $this->id,
        ], $this->anchorAttributes, $overrideAttrs);

        $attrStr = Aria::compile($attrs);
        return "<div{$attrStr}>{$this->anchorContent}</div>";
    }

    /**
     * Render the arrow pointer element if enabled.
     */
    public function renderArrow(array $overrideAttrs = []): string
    {
        if (!$this->hasArrow) {
            return '';
        }

        $attrs = array_merge([
            'data-headless-arrow' => 'true',
            'aria-hidden'         => 'true',
        ], $this->arrowAttributes, $overrideAttrs);

        return '<div' . Aria::compile($attrs) . '></div>';
    }

    /**
     * Render the close button inside the popover.
     */
    public function renderClose(array $overrideAttrs = []): string
    {
        if ($this->closeButtonContent === null) {
            return '';
        }

        $attrs = array_merge([
            'type'                => 'button',
            'aria-label'          => 'Close',
            'data-headless-close' => $this->id,
        ], $this->closeButtonAttributes, $overrideAttrs);

        $attrStr = Aria::compile($attrs);
        return "<button{$attrStr}>{$this->closeButtonContent}</button>";
    }

    /**
     * Render the popover content panel.
     */
    public function renderContent(array $overrideAttrs = []): string
    {
        $attrs = [
            'id'                   => $this->getContentId(),
            'role'                 => Aria::ROLE_DIALOG,
            'aria-labelledby'      => $this->getTriggerId(),
            'tabindex'             => '-1',
            'data-state'           => $this->open ? 'open' : 'closed',
            'data-side'            => $this->side,
            'data-align'           => $this->align,
            'data-side-offset'     => (string)$this->sideOffset,
            'data-align-offset'    => (string)$this->alignOffset,
            'data-headless-content'=> $this->id,
        ];

        if ($this->closeOnOutsideClick) {
            $attrs['data-headless-dismiss-outside'] = 'true';
        }

        // Merge focus manager and keyboard data attributes
        $attrs = array_merge($attrs, $this->getFocusManager()->toAttributes(), $this->getKeyboard()->toAttributes());

        if (!$this->open) {
            $attrs['hidden'] = true;
        }

        $merged = array_merge($attrs, $this->contentAttributes, $overrideAttrs);
        $attrStr = Aria::compile($merged);

        $body = '';
        if ($this->hasArrow) {
            $body .= $this->renderArrow();
        }

        foreach ($this->contentChildren as $child) {
            $body .= (string)$child;
        }

        if ($this->closeButtonContent !== null) {
            $body .= $this->renderClose();
        }

        return "<div{$attrStr}>{$body}</div>";
    }

    /**
     * Render complete compound popover element.
     */
    public function render(): string
    {
        $rootAttrs = array_merge([
            'id'            => $this->id,
            'data-headless' => 'popover',
            'data-state'    => $this->open ? 'open' : 'closed',
        ], $this->attributes);

        $rootAttrStr = Aria::compile($rootAttrs);

        $html = "<div{$rootAttrStr}>";

        if ($this->anchorContent !== null) {
            $html .= $this->renderAnchor();
        }

        if ($this->triggerContent !== null) {
            $html .= $this->renderTrigger();
        }

        $html .= $this->renderContent();
        $html .= '</div>';

        return $html;
    }
}
