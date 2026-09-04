<?php
declare(strict_types=1);

namespace Oshim\Ui\Widgets;

use Oshim\Ui\Dsl\Element;

/**
 * Stackable Notification Toast Widget.
 */
class ToastWidget extends Element
{
    private string $message;
    private string $type; // success, error, warning, info
    private int $durationMs;

    public function __construct(string $message, string $type = 'success', int $durationMs = 4000)
    {
        parent::__construct('div');
        $this->message = $message;
        $this->type = $type;
        $this->durationMs = $durationMs;
    }

    public static function success(string $msg): self { return new self($msg, 'success'); }
    public static function error(string $msg): self { return new self($msg, 'error'); }
    public static function warning(string $msg): self { return new self($msg, 'warning'); }
    public static function info(string $msg): self { return new self($msg, 'info'); }

    public function render(): string
    {
        $colors = match ($this->type) {
            'success' => ['bg' => 'rgba(0,230,118,0.15)', 'border' => 'rgba(0,230,118,0.4)', 'text' => '#00e676', 'icon' => '✔'],
            'error' => ['bg' => 'rgba(255,82,82,0.15)', 'border' => 'rgba(255,82,82,0.4)', 'text' => '#ff5252', 'icon' => '✖'],
            'warning' => ['bg' => 'rgba(255,214,0,0.15)', 'border' => 'rgba(255,214,0,0.4)', 'text' => '#ffd600', 'icon' => '⚠'],
            default => ['bg' => 'rgba(0,242,254,0.15)', 'border' => 'rgba(0,242,254,0.4)', 'text' => '#00f2fe', 'icon' => 'ℹ'],
        };

        $msgEsc = htmlspecialchars($this->message, ENT_QUOTES);
        $toastId = 'oshim-toast-' . uniqid();

        return <<<HTML
<div id="{$toastId}" style="position: fixed; bottom: 24px; right: 24px; z-index: 100000; background: {$colors['bg']}; border: 1px solid {$colors['border']}; backdrop-filter: blur(12px); border-radius: 12px; padding: 0.85rem 1.25rem; display: flex; align-items: center; gap: 10px; color: #f8fafc; font-size: 0.9rem; box-shadow: 0 10px 30px rgba(0,0,0,0.5); transition: all 0.3s ease;">
    <span style="color: {$colors['text']}; font-weight: bold; font-size: 1.1rem;">{$colors['icon']}</span>
    <span>{$msgEsc}</span>
</div>
<script>
setTimeout(function() {
    var t = document.getElementById('{$toastId}');
    if (t) {
        t.style.opacity = '0';
        t.style.transform = 'translateY(10px)';
        setTimeout(function() { t.remove(); }, 300);
    }
}, {$this->durationMs});
</script>
HTML;
    }
}
