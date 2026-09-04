<?php
declare(strict_types=1);

namespace Oshim\Ui\Widgets;

use Oshim\Ui\Dsl\Element;

/**
 * FloatingDockWidget: macOS / Raycast / Linear Style Floating Navigation Dock.
 * Pinned glassmorphic dock with dynamic icon magnification, tooltips, and shortcut triggers.
 */
class FloatingDockWidget extends Element
{
    /** @var list<array{title: string, icon: string, href: string, shortcut: ?string, badge: ?string}> */
    private array $items;

    public function __construct(array $items = [])
    {
        parent::__construct('nav');
        $this->class('oshim-floating-dock-widget');

        $this->items = !empty($items) ? $items : [
            ['title' => 'Dashboard', 'icon' => '⚡', 'href' => '/dashboard', 'shortcut' => '⌥1', 'badge' => null],
            ['title' => 'AI Studio', 'icon' => '🧠', 'href' => '/ai/canvas', 'shortcut' => '⌥2', 'badge' => 'PRO'],
            ['title' => 'Kanban Sprint', 'icon' => '📋', 'href' => '#kanban', 'shortcut' => '⌥3', 'badge' => null],
            ['title' => 'Code Sandbox', 'icon' => '💻', 'href' => '#editor', 'shortcut' => '⌥4', 'badge' => null],
            ['title' => 'Ledger Chain', 'icon' => '⛓️', 'href' => '#ledger', 'shortcut' => '⌥5', 'badge' => null],
            ['title' => 'Command Palette', 'icon' => '🔍', 'href' => 'javascript:void(0)', 'shortcut' => '⌘K', 'badge' => null],
        ];
    }

    public static function create(array $items = []): self
    {
        return new self($items);
    }

    public function addItem(string $title, string $icon, string $href = '#', ?string $shortcut = null, ?string $badge = null): self
    {
        $this->items[] = [
            'title' => $title,
            'icon' => $icon,
            'href' => $href,
            'shortcut' => $shortcut,
            'badge' => $badge,
        ];
        return $this;
    }

    public function render(): string
    {
        $itemsHtml = '';
        foreach ($this->items as $item) {
            $badgeHtml = '';
            if ($item['badge'] !== null) {
                $badgeHtml = <<<HTML
                <span class="absolute -top-1 -right-1 px-1 py-0.2 rounded-full text-[8px] font-mono font-black bg-cyan-500 text-slate-950 shadow-sm">
                    {$item['badge']}
                </span>
HTML;
            }

            $shortcutHtml = '';
            if ($item['shortcut'] !== null) {
                $shortcutHtml = "<kbd class=\"ml-1 text-[9px] text-slate-400 font-mono\">{$item['shortcut']}</kbd>";
            }

            $itemsHtml .= <<<HTML
            <div class="group relative flex flex-col items-center">
                <!-- Hover Tooltip -->
                <div class="absolute -top-10 opacity-0 group-hover:opacity-100 transition-all duration-200 pointer-events-none -translate-y-1 group-hover:translate-y-0 px-2.5 py-1 rounded-lg bg-slate-900 border border-slate-700 text-xs font-mono text-slate-200 whitespace-nowrap shadow-xl flex items-center">
                    <span>{$item['title']}</span>
                    {$shortcutHtml}
                </div>

                <!-- Dock Icon Button -->
                <a href="{$item['href']}" class="relative w-12 h-12 rounded-2xl flex items-center justify-center text-xl bg-slate-800/80 hover:bg-slate-700/90 border border-slate-700/60 hover:border-cyan-400 text-slate-200 hover:text-cyan-300 transition-all duration-300 hover:scale-125 hover:-translate-y-2 shadow-lg backdrop-blur-md">
                    <span>{$item['icon']}</span>
                    {$badgeHtml}
                </a>
            </div>
HTML;
        }

        return <<<HTML
<div class="fixed bottom-6 left-1/2 transform -translate-x-1/2 z-50">
    <div class="flex items-center space-x-3 px-4 py-2.5 rounded-3xl bg-[#090d16]/85 border border-slate-800/90 shadow-[0_20px_50px_rgba(0,0,0,0.7)] backdrop-blur-2xl ring-1 ring-white/10">
        {$itemsHtml}
    </div>
</div>
HTML;
    }
}
