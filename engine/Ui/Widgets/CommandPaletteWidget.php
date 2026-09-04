<?php
declare(strict_types=1);

namespace Oshim\Ui\Widgets;

use Oshim\Ui\Dsl\Element;

/**
 * Mac Spotlight / Cmd+K Global Search & Shortcut Command Palette.
 */
class CommandPaletteWidget extends Element
{
    /** @var array<array{id: string, label: string, shortcut: string, action: string, icon: string}> */
    private array $commands = [];

    public function __construct(array $commands = [])
    {
        parent::__construct('div');
        $this->commands = $commands;
    }

    public static function palette(array $commands = []): self
    {
        return new self($commands);
    }

    public function addCommand(string $label, string $action, string $shortcut = '', string $icon = '⚡'): self
    {
        $this->commands[] = [
            'id' => 'cmd-' . count($this->commands),
            'label' => $label,
            'action' => $action,
            'shortcut' => $shortcut,
            'icon' => $icon,
        ];
        return $this;
    }

    public function render(): string
    {
        $itemsHtml = '';
        foreach ($this->commands as $cmd) {
            $shortcutHtml = $cmd['shortcut'] !== '' ? sprintf('<kbd style="background: rgba(255,255,255,0.1); padding: 2px 6px; border-radius: 4px; font-size: 0.75rem;">%s</kbd>', htmlspecialchars($cmd['shortcut'])) : '';
            $itemsHtml .= sprintf(
                '<div class="oshim-cmd-item" data-action="%s" style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; border-radius: 8px; cursor: pointer; transition: background 0.15s ease;" onmouseover="this.style.background=\'rgba(0,242,254,0.1)\'" onmouseout="this.style.background=\'transparent\'">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span>%s</span>
                        <span style="font-weight: 500; font-size: 0.9rem;">%s</span>
                    </div>
                    %s
                </div>',
                htmlspecialchars($cmd['action']),
                htmlspecialchars($cmd['icon']),
                htmlspecialchars($cmd['label']),
                $shortcutHtml
            );
        }

        return <<<HTML
<div id="oshim-command-palette" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.75); backdrop-filter: blur(16px); z-index: 99999; justify-content: center; align-items: flex-start; padding-top: 15vh;">
    <div style="width: 100%; max-width: 600px; background: rgba(15,23,42,0.95); border: 1px solid rgba(0,242,254,0.3); border-radius: 16px; box-shadow: 0 25px 50px rgba(0,0,0,0.8), 0 0 30px rgba(0,242,254,0.2); overflow: hidden;">
        <div style="padding: 1rem; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; gap: 10px;">
            <span style="color: #00f2fe; font-size: 1.2rem;">🔍</span>
            <input type="text" id="oshim-cmd-search" placeholder="Type a command or search... (Esc to close)" style="width: 100%; background: transparent; border: none; outline: none; color: #f8fafc; font-size: 1rem;" autofocus />
        </div>
        <div style="max-height: 350px; overflow-y: auto; padding: 0.5rem;">
            {$itemsHtml}
        </div>
    </div>
</div>
<script>
document.addEventListener('keydown', function(e) {
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        var p = document.getElementById('oshim-command-palette');
        if (p) {
            p.style.display = (p.style.display === 'none' || !p.style.display) ? 'flex' : 'none';
            if (p.style.display === 'flex') document.getElementById('oshim-cmd-search').focus();
        }
    }
    if (e.key === 'Escape') {
        var p = document.getElementById('oshim-command-palette');
        if (p) p.style.display = 'none';
    }
});
</script>
HTML;
    }
}
