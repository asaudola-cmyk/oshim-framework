<?php
declare(strict_types=1);

namespace Oshim\Ui\Components;

use Oshim\Ui\Component;
use Oshim\Security\Sanitizer;

class Button extends Component
{
    protected string $label = '';
    protected string $variant = 'primary'; // primary, secondary, danger, ghost, glass, success, warning
    protected string $size = 'md';         // sm, md, lg
    protected string $type = 'button';     // button, submit, reset
    protected bool $loading = false;
    protected bool $disabled = false;
    protected bool $fullWidth = false;
    protected ?string $icon = null;
    protected string $iconPosition = 'left'; // left, right
    protected ?string $action = null;
    protected mixed $payload = null;
    protected string $class = '';
    protected array $attributes = [];

    public function mount(array $props): void
    {
        $this->label = (string)($props['label'] ?? '');
        $this->variant = in_array($props['variant'] ?? '', ['primary', 'secondary', 'danger', 'ghost', 'glass', 'success', 'warning'], true) ? $props['variant'] : 'primary';
        $this->size = in_array($props['size'] ?? '', ['sm', 'md', 'lg', 'xs'], true) ? $props['size'] : 'md';
        $this->type = in_array($props['type'] ?? '', ['button', 'submit', 'reset'], true) ? $props['type'] : 'button';
        $this->loading = (bool)($props['loading'] ?? false);
        $this->disabled = (bool)($props['disabled'] ?? false);
        $this->fullWidth = (bool)($props['fullWidth'] ?? false);
        $this->icon = $props['icon'] ?? null;
        $this->iconPosition = ($props['iconPosition'] ?? 'left') === 'right' ? 'right' : 'left';
        $this->action = $props['action'] ?? ($props['onClick'] ?? null);
        $this->payload = $props['payload'] ?? null;
        $this->class = (string)($props['class'] ?? '');
        $this->attributes = (array)($props['attributes'] ?? []);
    }

    public function render(): string
    {
        $classes = [
            'oshim-btn',
            "oshim-btn--{$this->variant}",
            "oshim-btn--{$this->size}",
        ];
        if ($this->fullWidth) {
            $classes[] = 'oshim-btn--block';
        }
        if ($this->loading) {
            $classes[] = 'oshim-btn--loading';
        }
        if ($this->class !== '') {
            $classes[] = $this->class;
        }

        $attrs = [];
        $attrs[] = 'data-oshim-id="' . $this->escape($this->id) . '"';
        $attrs[] = 'type="' . $this->escape($this->type) . '"';
        $attrs[] = 'class="' . $this->escape(implode(' ', $classes)) . '"';

        if ($this->disabled || $this->loading) {
            $attrs[] = 'disabled="disabled"';
            $attrs[] = 'aria-disabled="true"';
        }

        if ($this->action !== null) {
            $attrs[] = 'oshim:click="' . $this->escape($this->action) . '"';
            $attrs[] = 'data-oshim-action="' . $this->escape($this->action) . '"';
            if ($this->payload !== null) {
                $payloadJson = is_string($this->payload) ? $this->payload : json_encode($this->payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES);
                $attrs[] = 'data-oshim-payload="' . $this->escape((string)$payloadJson) . '"';
            }
        }

        foreach ($this->attributes as $k => $v) {
            $attrs[] = $this->escape((string)$k) . '="' . $this->escape((string)$v) . '"';
        }

        $spinnerHtml = '<span class="oshim-btn__spinner' . ($this->loading ? '' : ' oshim-hidden') . '" aria-hidden="true">'
            . '<svg class="oshim-spinner-svg" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity="0.25"></circle><path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>'
            . '</span>';

        $iconContent = '';
        if ($this->hasSlot('icon')) {
            $iconContent = $this->slot('icon');
        } elseif ($this->icon !== null) {
            $iconContent = (str_starts_with($this->icon, '<svg') || mb_strlen($this->icon) <= 2)
                ? $this->icon
                : $this->renderNamedIcon($this->icon);
        }

        $iconHtml = '';
        if ($iconContent !== '') {
            $iconHtml = '<span class="oshim-btn__icon oshim-btn__icon--' . $this->iconPosition . ($this->loading ? ' oshim-hidden' : '') . '">' . $iconContent . '</span>';
        }

        $labelText = $this->hasSlot('default') ? $this->slot('default') : $this->label;
        $labelHtml = '<span class="oshim-btn__label">' . ($this->hasSlot('default') ? $labelText : $this->escape($labelText)) . '</span>';

        $innerHtml = $spinnerHtml;
        if ($this->iconPosition === 'left') {
            $innerHtml .= $iconHtml . $labelHtml;
        } else {
            $innerHtml .= $labelHtml . $iconHtml;
        }

        return '<button ' . implode(' ', $attrs) . '>' . $innerHtml . '</button>';
    }

    private function renderNamedIcon(string $name): string
    {
        return match (strtolower($name)) {
            'power' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M18.36 6.64a9 9 0 1 1-12.73 0M12 2v10"/></svg>',
            'refresh' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>',
            'terminal' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/></svg>',
            'trash' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>',
            'plus' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
            'check' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>',
            'edit' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
            'server' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>',
            'play' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>',
            'stop' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><rect x="6" y="6" width="12" height="12"/></svg>',
            default => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><circle cx="12" cy="12" r="6"/></svg>',
        };
    }
}
