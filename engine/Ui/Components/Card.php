<?php
declare(strict_types=1);

namespace Oshim\Ui\Components;

use Oshim\Ui\Component;

class Card extends Component
{
    protected ?string $title = null;
    protected ?string $subtitle = null;
    protected ?string $icon = null;
    protected string $variant = 'glass';   // glass, solid, elevated, bordered
    protected string $glowColor = 'none';  // cyan, purple, emerald, rose, amber, blue, none
    protected string $padding = 'md';      // none, sm, md, lg
    protected bool $hoverable = true;
    protected ?string $header = null;
    protected ?string $headerActions = null;
    protected ?string $body = null;
    protected ?string $footer = null;
    protected ?string $footerActions = null;
    protected string $class = '';

    public function mount(array $props): void
    {
        $this->title = $props['title'] ?? null;
        $this->subtitle = $props['subtitle'] ?? null;
        $this->icon = $props['icon'] ?? null;
        $this->variant = in_array($props['variant'] ?? '', ['glass', 'solid', 'elevated', 'bordered'], true) ? $props['variant'] : 'glass';
        $this->glowColor = in_array($props['glowColor'] ?? '', ['cyan', 'purple', 'emerald', 'rose', 'amber', 'blue', 'none'], true) ? $props['glowColor'] : 'none';
        $this->padding = in_array($props['padding'] ?? '', ['none', 'sm', 'md', 'lg'], true) ? $props['padding'] : 'md';
        $this->hoverable = (bool)($props['hoverable'] ?? true);
        $this->header = $props['header'] ?? null;
        $this->headerActions = $props['headerActions'] ?? null;
        $this->body = $props['body'] ?? ($props['content'] ?? null);
        $this->footer = $props['footer'] ?? null;
        $this->footerActions = $props['footerActions'] ?? null;
        $this->class = (string)($props['class'] ?? '');
    }

    public function render(): string
    {
        $classes = [
            'oshim-card',
            'oshim-glass',
            "oshim-card--{$this->variant}",
            "oshim-card--glow-{$this->glowColor}",
            "oshim-card--pad-{$this->padding}",
        ];
        if ($this->hoverable) {
            $classes[] = 'oshim-card--hover';
        }
        if ($this->class !== '') {
            $classes[] = $this->class;
        }

        $html = '<div data-oshim-id="' . $this->escape($this->id) . '" class="' . $this->escape(implode(' ', $classes)) . '">';

        // Specular glow border highlight
        $html .= '<div class="oshim-card__glow-border"></div>';

        // Header section (rendered if title, subtitle, icon, custom header slot/prop, or headerActions exist)
        $headerContent = $this->hasSlot('header') ? $this->slot('header') : $this->header;
        $headerActionsContent = $this->hasSlot('headerActions') ? $this->slot('headerActions') : $this->headerActions;

        if ($headerContent !== null || $this->title !== null || $headerActionsContent !== null) {
            $html .= '<div class="oshim-card__header oshim-card-header">';
            if ($headerContent !== null) {
                $html .= $headerContent;
            } else {
                $html .= '<div class="oshim-card__title-group">';
                if ($this->icon !== null) {
                    $html .= '<span class="oshim-card__icon">' . $this->icon . '</span>';
                }
                $html .= '<div class="oshim-card__headings">';
                if ($this->title !== null) {
                    $html .= '<h3 class="oshim-card__title">' . $this->escape($this->title) . '</h3>';
                }
                if ($this->subtitle !== null) {
                    $html .= '<p class="oshim-card__subtitle">' . $this->escape($this->subtitle) . '</p>';
                }
                $html .= '</div></div>';
                if ($headerActionsContent !== null) {
                    $html .= '<div class="oshim-card__actions">' . $headerActionsContent . '</div>';
                }
            }
            $html .= '</div>';
        }

        // Body section
        $bodyContent = $this->hasSlot('default') ? $this->slot('default') : ($this->hasSlot('body') ? $this->slot('body') : $this->body);
        if ($bodyContent !== null) {
            $html .= '<div class="oshim-card__body oshim-card-body">' . $bodyContent . '</div>';
        }

        // Footer section
        $footerContent = $this->hasSlot('footer') ? $this->slot('footer') : $this->footer;
        $footerActionsContent = $this->hasSlot('footerActions') ? $this->slot('footerActions') : $this->footerActions;

        if ($footerContent !== null || $footerActionsContent !== null) {
            $html .= '<div class="oshim-card__footer oshim-card-footer">';
            if ($footerContent !== null) {
                $html .= '<div class="oshim-card__footer-content">' . $footerContent . '</div>';
            }
            if ($footerActionsContent !== null) {
                $html .= '<div class="oshim-card__footer-actions">' . $footerActionsContent . '</div>';
            }
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }
}
