<?php
declare(strict_types=1);

namespace Oshim\Ui\Widgets;

use Oshim\Ui\Dsl\Element;

/**
 * HologramCardWidget: 3D Holographic Cyberpunk Glassmorphic Card.
 * Features rotating border-beam gradients, scanline overlays, and neon glow accents.
 */
class HologramCardWidget extends Element
{
    private string $title;
    private string $subtitle;
    private string $accentColor;
    private string $icon;
    private string $bodyHtml;

    public function __construct(
        string $title,
        string $subtitle = '',
        string $bodyHtml = '',
        string $accentColor = '#00f2fe',
        string $icon = '⚡'
    ) {
        parent::__construct('div');
        $this->title = $title;
        $this->subtitle = $subtitle;
        $this->bodyHtml = $bodyHtml;
        $this->accentColor = $accentColor;
        $this->icon = $icon;
        $this->class('oshim-hologram-card-container');
    }

    public static function create(
        string $title,
        string $subtitle = '',
        string $bodyHtml = '',
        string $accentColor = '#00f2fe',
        string $icon = '⚡'
    ): self {
        return new self($title, $subtitle, $bodyHtml, $accentColor, $icon);
    }

    public function render(): string
    {
        $id = 'holo_' . substr(md5($this->title . microtime()), 0, 8);

        return <<<HTML
<div id="{$id}" class="oshim-hologram-card relative group p-6 rounded-2xl overflow-hidden transition-all duration-500 hover:scale-[1.02] hover:-translate-y-1" style="background: rgba(10, 15, 29, 0.85); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.08); box-shadow: 0 20px 50px rgba(0,0,0,0.5), inset 0 0 30px rgba(0, 242, 254, 0.03);">
    <!-- Animated Border Beam -->
    <div class="absolute inset-0 rounded-2xl pointer-events-none overflow-hidden">
        <div class="absolute -inset-[100%] animate-[spin_6s_linear_infinite] opacity-40 group-hover:opacity-100 transition-opacity" style="background: conic-gradient(from 0deg, transparent 0 300deg, {$this->accentColor} 340deg, #9d4edd 360deg);"></div>
    </div>
    <div class="absolute inset-[1px] rounded-2xl bg-[#090d16]/95 backdrop-blur-xl"></div>

    <!-- Holographic Cyber Grid Overlay -->
    <div class="absolute inset-0 pointer-events-none opacity-20 bg-[linear-gradient(to_right,#1e293b_1px,transparent_1px),linear-gradient(to_bottom,#1e293b_1px,transparent_1px)] bg-[size:1.5rem_1.5rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_100%)]"></div>

    <!-- Card Content -->
    <div class="relative z-10">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center space-x-3">
                <span class="w-10 h-10 rounded-xl flex items-center justify-center text-xl shadow-lg" style="background: {$this->accentColor}15; border: 1px solid {$this->accentColor}40; color: {$this->accentColor}; text-shadow: 0 0 12px {$this->accentColor};">
                    {$this->icon}
                </span>
                <div>
                    <h3 class="font-bold text-lg text-slate-100 tracking-wide">{$this->title}</h3>
                    <p class="text-xs text-slate-400 font-mono">{$this->subtitle}</p>
                </div>
            </div>
            <span class="w-2.5 h-2.5 rounded-full animate-pulse shadow-md" style="background: {$this->accentColor}; box-shadow: 0 0 10px {$this->accentColor};"></span>
        </div>

        <div class="text-sm text-slate-300 leading-relaxed font-sans">
            {$this->bodyHtml}
        </div>
    </div>
</div>
HTML;
    }
}
