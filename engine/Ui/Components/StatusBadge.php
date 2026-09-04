<?php
declare(strict_types=1);

namespace Oshim\Ui\Components;

use Oshim\Ui\Component;

class StatusBadge extends Component
{
    protected string $status = 'idle'; // running, stopped, warning, error, provisioning, rebooting, idle, active, suspended, pending, healthy, unhealthy
    protected ?string $label = null;
    protected bool $pulse = true;
    protected string $size = 'md';       // sm, md, lg
    protected string $variant = 'glow';  // glow, subtle, bordered, solid
    protected ?string $icon = null;
    protected string $class = '';

    public function mount(array $props): void
    {
        $this->status = (string)($props['status'] ?? 'idle');
        $this->label = $props['label'] ?? null;
        $this->pulse = (bool)($props['pulse'] ?? true);
        $this->size = in_array($props['size'] ?? '', ['sm', 'md', 'lg'], true) ? $props['size'] : 'md';
        $this->variant = in_array($props['variant'] ?? '', ['glow', 'subtle', 'bordered', 'solid'], true) ? $props['variant'] : 'glow';
        $this->icon = $props['icon'] ?? null;
        $this->class = (string)($props['class'] ?? '');
    }

    public function render(): string
    {
        $statusKey = strtolower($this->status);
        $classes = [
            'oshim-badge',
            "oshim-badge--{$statusKey}",
            "oshim-badge--{$this->size}",
            "oshim-badge--{$this->variant}",
        ];
        if ($this->pulse) {
            $classes[] = 'oshim-badge--pulse';
        }
        if ($this->class !== '') {
            $classes[] = $this->class;
        }

        $labelText = $this->label ?? strtoupper($this->status);

        $html = '<span class="' . $this->escape(implode(' ', $classes)) . '" data-oshim-id="' . $this->escape($this->id) . '">';
        $html .= '<span class="oshim-badge__dot" aria-hidden="true"></span>';
        if ($this->icon !== null) {
            $html .= '<span class="oshim-badge__icon">' . $this->icon . '</span>';
        }
        $html .= '<span class="oshim-badge__label">' . $this->escape($labelText) . '</span>';
        $html .= '</span>';

        return $html;
    }
}
