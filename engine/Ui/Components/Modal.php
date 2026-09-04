<?php
declare(strict_types=1);

namespace Oshim\Ui\Components;

use Oshim\Ui\Component;

class Modal extends Component
{
    protected string $name = '';
    protected string $title = '';
    protected ?string $subtitle = null;
    protected string $size = 'md'; // sm, md, lg, xl, full
    protected bool $closeOnBackdrop = true;
    protected bool $closeOnEsc = true;
    protected ?string $header = null;
    protected ?string $body = null;
    protected ?string $footer = null;
    protected string $class = '';

    public function mount(array $props): void
    {
        $this->name = (string)($props['name'] ?? 'modal_' . $this->getId());
        $this->title = (string)($props['title'] ?? 'Modal Dialog');
        $this->subtitle = $props['subtitle'] ?? null;
        $this->size = in_array($props['size'] ?? '', ['sm', 'md', 'lg', 'xl', 'full'], true) ? $props['size'] : 'md';
        $this->closeOnBackdrop = (bool)($props['closeOnBackdrop'] ?? true);
        $this->closeOnEsc = (bool)($props['closeOnEsc'] ?? true);
        $this->header = $props['header'] ?? null;
        $this->body = $props['body'] ?? ($props['content'] ?? null);
        $this->footer = $props['footer'] ?? null;
        $this->class = (string)($props['class'] ?? '');

        if (!isset($this->state['open'])) {
            $this->state['open'] = (bool)($props['isOpen'] ?? ($props['open'] ?? false));
        }
    }

    public function open(array $payload = []): void
    {
        $this->state['open'] = true;
    }

    public function close(array $payload = []): void
    {
        $this->state['open'] = false;
    }

    public function toggle(array $payload = []): void
    {
        $this->state['open'] = empty($this->state['open']);
    }

    public function render(): string
    {
        $isOpen = !empty($this->state['open']);
        $displayState = $isOpen ? 'active oshim-modal--open' : 'hidden oshim-modal--closed';

        $backdropClasses = [
            'oshim-modal-backdrop',
            $displayState,
        ];

        $modalClasses = [
            'oshim-modal',
            'oshim-glass',
            "oshim-modal--{$this->size}",
        ];
        if ($this->class !== '') {
            $modalClasses[] = $this->class;
        }

        $html = '<div id="' . $this->escape($this->name) . '" class="' . implode(' ', $backdropClasses) . '" data-oshim-id="' . $this->escape($this->id) . '" data-oshim-modal="' . $this->escape($this->name) . '"';
        if ($this->closeOnBackdrop) {
            $html .= ' oshim:click="close"';
        }
        $html .= '>';

        $html .= '<div class="' . $this->escape(implode(' ', $modalClasses)) . '" role="dialog" aria-modal="true" aria-labelledby="modal_title_' . $this->escape($this->id) . '" onclick="event.stopPropagation()">';

        // Header
        $headerContent = $this->hasSlot('header') ? $this->slot('header') : $this->header;
        $html .= '<div class="oshim-modal__header oshim-modal-header">';
        if ($headerContent !== null) {
            $html .= $headerContent;
        } else {
            $html .= '<div class="oshim-modal__title-group">';
            $html .= '<h4 id="modal_title_' . $this->escape($this->id) . '" class="oshim-modal__title">' . $this->escape($this->title) . '</h4>';
            if ($this->subtitle !== null) {
                $html .= '<p class="oshim-modal__subtitle">' . $this->escape($this->subtitle) . '</p>';
            }
            $html .= '</div>';
            $html .= '<button type="button" class="oshim-modal__close" aria-label="Close" oshim:click="close"><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg></button>';
        }
        $html .= '</div>';

        // Body
        $bodyContent = $this->hasSlot('default') ? $this->slot('default') : ($this->hasSlot('body') ? $this->slot('body') : $this->body);
        if ($bodyContent !== null) {
            $html .= '<div class="oshim-modal__body oshim-modal-body">' . $bodyContent . '</div>';
        }

        // Footer
        $footerContent = $this->hasSlot('footer') ? $this->slot('footer') : $this->footer;
        if ($footerContent !== null) {
            $html .= '<div class="oshim-modal__footer oshim-modal-footer">' . $footerContent . '</div>';
        }

        $html .= '</div></div>';
        return $html;
    }
}
