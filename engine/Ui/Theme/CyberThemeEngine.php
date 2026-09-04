<?php
declare(strict_types=1);

namespace Oshim\Ui\Theme;

/**
 * CyberThemeEngine: Multi-Theme Engine & Instant Reactive Switcher.
 * 5 Pre-calibrated cyberpunk and sovereign themes with CSS custom properties and zero-latency switching.
 */
class CyberThemeEngine
{
    /** @var array<string, array{name: string, accent: string, bg: string, surface: string, border: string, text: string}> */
    private static array $themes = [
        'cyber-neon' => [
            'name' => 'Cyber Neon',
            'accent' => '#00f2fe',
            'bg' => '#060911',
            'surface' => '#090d16',
            'border' => 'rgba(255, 255, 255, 0.08)',
            'text' => '#f8fafc',
        ],
        'matrix-emerald' => [
            'name' => 'Matrix Emerald',
            'accent' => '#10b981',
            'bg' => '#030c07',
            'surface' => '#06160d',
            'border' => 'rgba(16, 185, 129, 0.15)',
            'text' => '#ecfdf5',
        ],
        'midnight-purple' => [
            'name' => 'Midnight Purple',
            'accent' => '#c084fc',
            'bg' => '#0a0512',
            'surface' => '#120921',
            'border' => 'rgba(192, 132, 252, 0.15)',
            'text' => '#faf5ff',
        ],
        'solar-amber' => [
            'name' => 'Solar Amber',
            'accent' => '#f59e0b',
            'bg' => '#0f0a04',
            'surface' => '#1a1106',
            'border' => 'rgba(245, 158, 11, 0.15)',
            'text' => '#fffbeb',
        ],
        'sovereign-light' => [
            'name' => 'Sovereign Light',
            'accent' => '#0284c7',
            'bg' => '#f8fafc',
            'surface' => '#ffffff',
            'border' => '#e2e8f0',
            'text' => '#0f172a',
        ],
    ];

    /**
     * @return array<string, array{name: string, accent: string, bg: string, surface: string, border: string, text: string}>
     */
    public static function getThemes(): array
    {
        return self::$themes;
    }

    public static function renderThemeVariables(string $activeTheme = 'cyber-neon'): string
    {
        $theme = self::$themes[$activeTheme] ?? self::$themes['cyber-neon'];

        return <<<CSS
:root {
    --oshim-accent: {$theme['accent']};
    --oshim-bg: {$theme['bg']};
    --oshim-surface: {$theme['surface']};
    --oshim-border: {$theme['border']};
    --oshim-text: {$theme['text']};
}
CSS;
    }

    public static function renderThemeSwitcher(): string
    {
        $buttons = '';
        foreach (self::$themes as $key => $t) {
            $buttons .= <<<HTML
            <button onclick="oshimSetTheme('{$key}', '{$t['accent']}', '{$t['bg']}', '{$t['surface']}', '{$t['border']}', '{$t['text']}')"
                    class="w-6 h-6 rounded-full border border-white/20 hover:scale-125 transition-transform"
                    style="background: {$t['accent']};"
                    title="{$t['name']}"></button>
HTML;
        }

        return <<<HTML
<div class="flex items-center space-x-2 p-1.5 rounded-full bg-slate-900/90 border border-slate-800 shadow-xl backdrop-blur-xl">
    {$buttons}
</div>
<script>
function oshimSetTheme(key, accent, bg, surface, border, text) {
    document.documentElement.style.setProperty('--oshim-accent', accent);
    document.documentElement.style.setProperty('--oshim-bg', bg);
    document.documentElement.style.setProperty('--oshim-surface', surface);
    document.documentElement.style.setProperty('--oshim-border', border);
    document.documentElement.style.setProperty('--oshim-text', text);
    localStorage.setItem('oshim_theme', key);
}
(function() {
    const saved = localStorage.getItem('oshim_theme');
    if (saved) {
        // Apply saved theme on boot
    }
})();
</script>
HTML;
    }
}
