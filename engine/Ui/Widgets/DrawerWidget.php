<?php
declare(strict_types=1);

namespace Oshim\Ui\Widgets;

use Oshim\Ui\Dsl\Element;

/**
 * DrawerWidget: Slide-Over Drawer / Sheet Component (Right, Left, Bottom).
 * Smooth hardware-accelerated drawer for settings, details panels, and mobile sheets.
 */
class DrawerWidget extends Element
{
    private string $id;
    private string $title;
    private string $position;
    private string $bodyHtml;
    private ?string $footerHtml;

    public function __construct(
        string $id,
        string $title,
        string $bodyHtml,
        string $position = 'right',
        ?string $footerHtml = null
    ) {
        parent::__construct('div');
        $this->id = $id;
        $this->title = $title;
        $this->bodyHtml = $bodyHtml;
        $this->position = in_array($position, ['right', 'left', 'bottom'], true) ? $position : 'right';
        $this->footerHtml = $footerHtml;
        $this->class('oshim-drawer-widget');
    }

    public static function create(
        string $id,
        string $title,
        string $bodyHtml,
        string $position = 'right',
        ?string $footerHtml = null
    ): self {
        return new self($id, $title, $bodyHtml, $position, $footerHtml);
    }

    public function render(): string
    {
        $posClass = match ($this->position) {
            'left' => 'top-0 left-0 h-full w-full max-w-md -translate-x-full',
            'bottom' => 'bottom-0 left-0 w-full max-h-[85vh] translate-y-full rounded-t-3xl',
            default => 'top-0 right-0 h-full w-full max-w-md translate-x-full',
        };

        $footerContent = '';
        if ($this->footerHtml !== null) {
            $footerContent = <<<HTML
            <div class="p-4 border-t border-slate-800 bg-slate-900/60 flex items-center justify-end space-x-3">
                {$this->footerHtml}
            </div>
HTML;
        }

        return <<<HTML
<div id="{$this->id}" class="fixed inset-0 z-50 pointer-events-none transition-all duration-300 [&.active]:pointer-events-auto">
    <!-- Backdrop Blur -->
    <div onclick="document.getElementById('{$this->id}').classList.remove('active')" class="absolute inset-0 bg-black/60 backdrop-blur-sm opacity-0 transition-opacity duration-300 [div.active_&]:opacity-100"></div>

    <!-- Drawer Panel -->
    <div class="absolute {$posClass} bg-[#090d16] border-l border-slate-800 shadow-2xl flex flex-col transition-transform duration-300 ease-out [div.active_&]:translate-x-0 [div.active_&]:translate-y-0">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-800">
            <h3 class="text-base font-bold text-slate-100 font-mono tracking-wide">{$this->title}</h3>
            <button onclick="document.getElementById('{$this->id}').classList.remove('active')" class="w-8 h-8 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-slate-100 flex items-center justify-center transition-colors">
                ✕
            </button>
        </div>

        <!-- Body -->
        <div class="flex-1 overflow-y-auto p-6 space-y-4 text-sm text-slate-300">
            {$this->bodyHtml}
        </div>

        <!-- Footer -->
        {$footerContent}
    </div>
</div>
HTML;
    }
}
