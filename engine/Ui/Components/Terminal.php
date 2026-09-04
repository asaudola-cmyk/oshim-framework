<?php
declare(strict_types=1);

namespace Oshim\Ui\Components;

use Oshim\Ui\Component;

class Terminal extends Component
{
    protected string $type = 'ssh'; // ssh, vnc, serial, logs
    protected string $instanceId = '';
    protected string $title = 'WebSSH Terminal';
    protected string $wsEndpoint = '';
    protected string $status = 'disconnected'; // connected, connecting, disconnected, error
    protected string $theme = 'dracula';       // dracula, cyberpunk, monokai, matrix, classic
    protected int $fontSize = 14;
    protected int $height = 480;
    protected bool $toolbar = true;
    protected bool $readOnly = false;
    protected string $class = '';

    public function mount(array $props): void
    {
        $this->type = in_array($props['type'] ?? '', ['ssh', 'vnc', 'serial', 'logs'], true) ? $props['type'] : 'ssh';
        $this->instanceId = (string)($props['instanceId'] ?? 'inst_' . bin2hex(random_bytes(4)));
        $this->title = (string)($props['title'] ?? ($this->type === 'vnc' ? 'VNC Graphical Console' : 'WebSSH Terminal'));
        $this->wsEndpoint = (string)($props['wsEndpoint'] ?? "/ws/{$this->type}/{$this->instanceId}");
        $this->status = in_array($props['status'] ?? '', ['connected', 'connecting', 'disconnected', 'error'], true) ? $props['status'] : 'disconnected';
        $this->theme = in_array($props['theme'] ?? '', ['dracula', 'cyberpunk', 'monokai', 'matrix', 'classic'], true) ? $props['theme'] : 'dracula';
        $this->fontSize = max(10, (int)($props['fontSize'] ?? 14));
        $this->height = max(150, (int)($props['height'] ?? 480));
        $this->toolbar = (bool)($props['toolbar'] ?? true);
        $this->readOnly = (bool)($props['readOnly'] ?? false);
        $this->class = (string)($props['class'] ?? '');
    }

    public function render(): string
    {
        $classes = [
            'oshim-terminal',
            'oshim-terminal-container',
            "oshim-terminal--{$this->type}",
            "oshim-terminal--theme-{$this->theme}",
        ];
        if ($this->class !== '') {
            $classes[] = $this->class;
        }

        $html = '<div id="terminal_' . $this->escape($this->instanceId) . '" class="' . $this->escape(implode(' ', $classes)) . '" data-oshim-id="' . $this->escape($this->id) . '" data-oshim-terminal-type="' . $this->type . '" data-oshim-ws="' . $this->escape($this->wsEndpoint) . '" style="height: ' . $this->height . 'px;">';

        // Toolbar Header
        if ($this->toolbar) {
            $badgeStatus = match ($this->status) {
                'connected' => 'running',
                'connecting' => 'provisioning',
                'error' => 'error',
                default => 'stopped',
            };
            $badge = new StatusBadge(['status' => $badgeStatus, 'label' => strtoupper($this->status), 'size' => 'sm', 'pulse' => $this->status === 'connected']);

            $html .= '<div class="oshim-terminal__header">';
            $html .= '<div class="oshim-terminal__info">' . $badge->render() . '<span class="oshim-terminal__title">' . $this->escape($this->title) . '</span></div>';
            $html .= '<div class="oshim-terminal__actions">';
            $html .= '<button type="button" class="oshim-btn oshim-btn--xs oshim-btn--glass" data-terminal-action="reconnect" title="Reconnect"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg> Reconnect</button>';
            $html .= '<button type="button" class="oshim-btn oshim-btn--xs oshim-btn--glass" data-terminal-action="clear" title="Clear Screen"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg> Clear</button>';
            $html .= '<button type="button" class="oshim-btn oshim-btn--xs oshim-btn--glass" data-terminal-action="fullscreen" title="Fullscreen"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg> Fullscreen</button>';
            $html .= '</div></div>';
        }

        // Viewport
        $html .= '<div class="oshim-terminal__viewport">';
        if ($this->type === 'vnc') {
            $html .= '<canvas class="oshim-terminal__canvas" tabindex="0" aria-label="VNC Console Framebuffer"></canvas>';
        } else {
            $html .= '<div class="oshim-terminal__screen" tabindex="0" aria-label="VT100 Console Buffer" style="font-size: ' . $this->fontSize . 'px;"><div class="oshim-terminal__buffer"></div><div class="oshim-terminal__cursor"></div></div>';
        }
        $html .= '</div>';

        $html .= '</div>';
        return $html;
    }
}
