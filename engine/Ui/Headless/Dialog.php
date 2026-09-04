<?php
declare(strict_types=1);

namespace Oshim\Ui\Headless;

use Oshim\Ui\Headless\Support\Aria;
use Oshim\Ui\Headless\Support\FocusManager;
use Oshim\Ui\Headless\Support\KeyboardNavigation;
use Stringable;

/**
 * Headless Dialog / Modal Primitive.
 * Implements WAI-ARIA APG Dialog (Modal) and Alertdialog patterns.
 * Provides accessible keyboard focus trapping, escape dismissal, and unstyled composability.
 */
class Dialog extends HeadlessComponent
{
    protected bool $isAlert = false;
    protected bool $closeOnEscape = true;
    protected bool $closeOnOverlay = true;

    // Slots and sub-elements
    protected string|Stringable|null $triggerContent = null;
    /** @var array<string, mixed> */
    protected array $triggerAttributes = [];

    /** @var array<string, mixed> */
    protected array $overlayAttributes = [];

    protected string|Stringable|null $titleText = null;
    /** @var array<string, mixed> */
    protected array $titleAttributes = [];

    protected string|Stringable|null $descriptionText = null;
    /** @var array<string, mixed> */
    protected array $descriptionAttributes = [];

    protected string|Stringable|null $closeButtonContent = null;
    /** @var array<string, mixed> */
    protected array $closeButtonAttributes = [];

    /** @var list<string|Stringable> */
    protected array $contentChildren = [];
    /** @var array<string, mixed> */
    protected array $contentAttributes = [];

    protected function boot(): void
    {
        $this->focusManager = FocusManager::make(true)->restoreFocus(true);
        $this->keyboard = KeyboardNavigation::forDialog();
    }

    public static function make(?string $id = null): self
    {
        return new self($id);
    }

    /**
     * Set modal dialog as an Alert Dialog (role="alertdialog") for urgent confirmations.
     */
    public function alert(bool $isAlert = true): static
    {
        $this->isAlert = $isAlert;
        return $this;
    }

    public function isAlert(): bool
    {
        return $this->isAlert;
    }

    public function closeOnEscape(bool $close = true): static
    {
        $this->closeOnEscape = $close;
        if (!$close) {
            $this->keyboard = KeyboardNavigation::make();
        } else {
            $this->keyboard = KeyboardNavigation::forDialog();
        }
        return $this;
    }

    public function closeOnOverlay(bool $close = true): static
    {
        $this->closeOnOverlay = $close;
        return $this;
    }

    /**
     * Define the trigger element (button).
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
     * Configure the backdrop overlay element.
     *
     * @param array<string, mixed> $attributes
     */
    public function overlay(array $attributes = []): static
    {
        $this->overlayAttributes = $attributes;
        return $this;
    }

    /**
     * Define the dialog title (aria-labelledby source).
     *
     * @param array<string, mixed> $attributes
     */
    public function title(string|Stringable $text, array $attributes = []): static
    {
        $this->titleText = $text;
        $this->titleAttributes = $attributes;
        return $this;
    }

    /**
     * Define the dialog description (aria-describedby source).
     *
     * @param array<string, mixed> $attributes
     */
    public function description(string|Stringable $text, array $attributes = []): static
    {
        $this->descriptionText = $text;
        $this->descriptionAttributes = $attributes;
        return $this;
    }

    /**
     * Define the close button.
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
     * Add content elements or child markup into dialog body.
     */
    public function content(string|Stringable ...$children): static
    {
        $this->contentChildren = array_values($children);
        return $this;
    }

    /**
     * Set attributes for the dialog content panel.
     *
     * @param array<string, mixed> $attributes
     */
    public function contentAttributes(array $attributes): static
    {
        $this->contentAttributes = $attributes;
        return $this;
    }

    // IDs for ARIA linkage
    public function getTriggerId(): string
    {
        return $this->id . '-trigger';
    }

    public function getContentId(): string
    {
        return $this->id . '-content';
    }

    public function getOverlayId(): string
    {
        return $this->id . '-overlay';
    }

    public function getTitleId(): string
    {
        return $this->id . '-title';
    }

    public function getDescriptionId(): string
    {
        return $this->id . '-desc';
    }

    /**
     * Render the trigger button markup.
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
     * Render the backdrop overlay markup.
     */
    public function renderOverlay(array $overrideAttrs = []): string
    {
        $attrs = array_merge([
            'id'          => $this->getOverlayId(),
            'aria-hidden' => 'true',
            'data-state'  => $this->open ? 'open' : 'closed',
            'data-headless-overlay' => $this->id,
        ], $this->overlayAttributes, $overrideAttrs);

        if (!$this->open) {
            $attrs['hidden'] = true;
        }

        if ($this->closeOnOverlay) {
            $attrs['data-headless-close-overlay'] = 'true';
        }

        $attrStr = Aria::compile($attrs);
        return "<div{$attrStr}></div>";
    }

    /**
     * Render the dialog title heading.
     */
    public function renderTitle(array $overrideAttrs = []): string
    {
        if ($this->titleText === null) {
            return '';
        }

        $attrs = array_merge([
            'id' => $this->getTitleId(),
        ], $this->titleAttributes, $overrideAttrs);

        $attrStr = Aria::compile($attrs);
        return "<h2{$attrStr}>{$this->titleText}</h2>";
    }

    /**
     * Render the dialog description paragraph.
     */
    public function renderDescription(array $overrideAttrs = []): string
    {
        if ($this->descriptionText === null) {
            return '';
        }

        $attrs = array_merge([
            'id' => $this->getDescriptionId(),
        ], $this->descriptionAttributes, $overrideAttrs);

        $attrStr = Aria::compile($attrs);
        return "<p{$attrStr}>{$this->descriptionText}</p>";
    }

    /**
     * Render the close button.
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
     * Render the dialog content panel markup.
     */
    public function renderContent(array $overrideAttrs = []): string
    {
        $role = $this->isAlert ? Aria::ROLE_ALERTDIALOG : Aria::ROLE_DIALOG;

        $attrs = [
            'id'          => $this->getContentId(),
            'role'        => $role,
            'aria-modal'  => 'true',
            'tabindex'    => '-1',
            'data-state'  => $this->open ? 'open' : 'closed',
            'data-headless-content' => $this->id,
        ];

        if ($this->titleText !== null) {
            $attrs['aria-labelledby'] = $this->getTitleId();
        }

        if ($this->descriptionText !== null) {
            $attrs['aria-describedby'] = $this->getDescriptionId();
        }

        // Merge focus manager and keyboard data attributes
        $attrs = array_merge($attrs, $this->getFocusManager()->toAttributes(), $this->getKeyboard()->toAttributes());

        if (!$this->open) {
            $attrs['hidden'] = true;
        }

        $merged = array_merge($attrs, $this->contentAttributes, $overrideAttrs);
        $attrStr = Aria::compile($merged);

        $body = '';
        if ($this->titleText !== null) {
            $body .= $this->renderTitle();
        }
        if ($this->descriptionText !== null) {
            $body .= $this->renderDescription();
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
     * Render complete compound dialog element.
     */
    public function render(): string
    {
        $rootAttrs = array_merge([
            'id'            => $this->id,
            'data-headless' => 'dialog',
            'data-state'    => $this->open ? 'open' : 'closed',
        ], $this->attributes);

        $rootAttrStr = Aria::compile($rootAttrs);

        $html = "<div{$rootAttrStr}>";

        if ($this->triggerContent !== null) {
            $html .= $this->renderTrigger();
        }

        $html .= $this->renderOverlay();
        $html .= $this->renderContent();
        $html .= '</div>';

        return $html;
    }
}
