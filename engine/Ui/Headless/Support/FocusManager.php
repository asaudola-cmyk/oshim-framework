<?php
declare(strict_types=1);

namespace Oshim\Ui\Headless\Support;

/**
 * Focus Management and Focus Trapping Support for Headless UI.
 * Coordinates initial autofocus, boundary containment (traps), roving tabindex,
 * and focus restoration according to WAI-ARIA APG standards.
 */
class FocusManager
{
    public const FOCUSABLE_SELECTOR = 'a[href], area[href], input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), iframe, object, embed, [tabindex]:not([tabindex="-1"]), [contenteditable]';

    protected bool $trap = false;
    protected bool $restoreFocus = true;
    protected ?string $initialFocus = null;
    protected ?string $restoreTarget = null;
    protected bool $rovingTabindex = false;
    protected int $activeIndex = 0;
    protected bool $loop = true;

    public function __construct(bool $trap = false)
    {
        $this->trap = $trap;
    }

    public static function make(bool $trap = false): self
    {
        return new self($trap);
    }

    /**
     * Enable or disable focus trap (keeps Tab / Shift+Tab inside the element).
     */
    public function trap(bool $trap = true): static
    {
        $this->trap = $trap;
        return $this;
    }

    public function isTrapped(): bool
    {
        return $this->trap;
    }

    /**
     * Specify element ID or CSS selector to receive initial focus upon activation.
     */
    public function initialFocus(?string $selectorOrId): static
    {
        $this->initialFocus = $selectorOrId;
        return $this;
    }

    public function getInitialFocus(): ?string
    {
        return $this->initialFocus;
    }

    /**
     * Enable or disable restoring focus to the trigger element when closed.
     */
    public function restoreFocus(bool $restore = true, ?string $targetElementId = null): static
    {
        $this->restoreFocus = $restore;
        $this->restoreTarget = $targetElementId;
        return $this;
    }

    public function shouldRestoreFocus(): bool
    {
        return $this->restoreFocus;
    }

    public function getRestoreTarget(): ?string
    {
        return $this->restoreTarget;
    }

    /**
     * Configure roving tabindex for list/menu items.
     */
    public function rovingTabindex(bool $enabled = true, int $activeIndex = 0): static
    {
        $this->rovingTabindex = $enabled;
        $this->activeIndex = $activeIndex;
        return $this;
    }

    public function isRovingTabindex(): bool
    {
        return $this->rovingTabindex;
    }

    public function getActiveIndex(): int
    {
        return $this->activeIndex;
    }

    public function setActiveIndex(int $index): static
    {
        $this->activeIndex = $index;
        return $this;
    }

    /**
     * Whether keyboard navigation loops around at boundaries.
     */
    public function loop(bool $loop = true): static
    {
        $this->loop = $loop;
        return $this;
    }

    public function shouldLoop(): bool
    {
        return $this->loop;
    }

    /**
     * Returns the tabindex for an item at a given index when using roving tabindex.
     */
    public function getItemTabindex(int $index): int
    {
        if (!$this->rovingTabindex) {
            return -1;
        }

        return ($index === $this->activeIndex) ? 0 : -1;
    }

    /**
     * Compiles focus configuration into data attributes for DOM / client runtime.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        $attrs = [];

        if ($this->trap) {
            $attrs['data-headless-focus-trap'] = 'true';
        }

        if ($this->restoreFocus) {
            $attrs['data-headless-restore-focus'] = $this->restoreTarget ?? 'true';
        } else {
            $attrs['data-headless-restore-focus'] = 'false';
        }

        if ($this->initialFocus !== null) {
            $attrs['data-headless-initial-focus'] = $this->initialFocus;
        }

        if ($this->rovingTabindex) {
            $attrs['data-headless-roving-tabindex'] = 'true';
            $attrs['data-headless-active-index'] = (string)$this->activeIndex;
        }

        if (!$this->loop) {
            $attrs['data-headless-focus-loop'] = 'false';
        }

        return $attrs;
    }
}
