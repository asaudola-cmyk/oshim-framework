<?php
declare(strict_types=1);

namespace Oshim\Ui\Css;

/**
 * Pure PHP Full-Scale Tailwind CSS JIT (Just-In-Time) Compiler.
 * Complete color palette (22 families × 11 shades), opacity modifiers, responsive breakpoints,
 * flex/grid, spacing, sizing, borders, effects, transitions, and pseudo-variants.
 */
class TailwindJitCompiler
{
    private static array $cache = [];
    public static function clearCache(): void { self::$cache = []; }

    /** @var array<string, array<int, string>> Full 22 Tailwind Color Palettes */
    private static array $colorPalette = [
        'slate' => [50 => '#f8fafc', 100 => '#f1f5f9', 200 => '#e2e8f0', 300 => '#cbd5e1', 400 => '#94a3b8', 500 => '#64748b', 600 => '#475569', 700 => '#334155', 800 => '#1e293b', 900 => '#0f172a', 950 => '#020617'],
        'gray' => [50 => '#f9fafb', 100 => '#f3f4f6', 200 => '#e5e7eb', 300 => '#d1d5db', 400 => '#9ca3af', 500 => '#6b7280', 600 => '#4b5563', 700 => '#374151', 800 => '#1f2937', 900 => '#111827', 950 => '#030712'],
        'zinc' => [50 => '#fafafa', 100 => '#f4f4f5', 200 => '#e4e4e7', 300 => '#d4d4d8', 400 => '#a1a1aa', 500 => '#71717a', 600 => '#52525b', 700 => '#3f3f46', 800 => '#27272a', 900 => '#18181b', 950 => '#09090b'],
        'neutral' => [50 => '#fafafa', 100 => '#f5f5f5', 200 => '#e5e5e5', 300 => '#d4d4d4', 400 => '#a3a3a3', 500 => '#737373', 600 => '#525252', 700 => '#404040', 800 => '#262626', 900 => '#171717', 950 => '#0a0a0a'],
        'stone' => [50 => '#fafaf9', 100 => '#f5f5f4', 200 => '#e7e5e4', 300 => '#d6d3d1', 400 => '#a8a29e', 500 => '#78716c', 600 => '#57534e', 700 => '#44403c', 800 => '#292524', 900 => '#1c1917', 950 => '#0c0a09'],
        'red' => [50 => '#fef2f2', 100 => '#fee2e2', 200 => '#fecaca', 300 => '#fca5a5', 400 => '#f87171', 500 => '#ef4444', 600 => '#dc2626', 700 => '#b91c1c', 800 => '#991b1b', 900 => '#7f1d1d', 950 => '#450a0a'],
        'orange' => [50 => '#fff7ed', 100 => '#ffedd5', 200 => '#fed7aa', 300 => '#fdba74', 400 => '#fb923c', 500 => '#f97316', 600 => '#ea580c', 700 => '#c2410c', 800 => '#9a3412', 900 => '#7c2d12', 950 => '#431407'],
        'amber' => [50 => '#fffbeb', 100 => '#fef3c7', 200 => '#fde68a', 300 => '#fcd34d', 400 => '#fbbf24', 500 => '#f59e0b', 600 => '#d97706', 700 => '#b45309', 800 => '#92400e', 900 => '#78350f', 950 => '#451a03'],
        'yellow' => [50 => '#fefce8', 100 => '#fef9c3', 200 => '#fef08a', 300 => '#fde047', 400 => '#facc15', 500 => '#eab308', 600 => '#ca8a04', 700 => '#a16207', 800 => '#854d0e', 900 => '#713f12', 950 => '#422006'],
        'lime' => [50 => '#f7fee7', 100 => '#ecfccb', 200 => '#d9f99d', 300 => '#bef264', 400 => '#a3e635', 500 => '#84cc16', 600 => '#65a30d', 700 => '#4d7c0f', 800 => '#3f6212', 900 => '#365314', 950 => '#1a2e05'],
        'green' => [50 => '#f0fdf4', 100 => '#dcfce7', 200 => '#bbf7d0', 300 => '#86efac', 400 => '#4ade80', 500 => '#22c55e', 600 => '#16a34a', 700 => '#15803d', 800 => '#166534', 900 => '#14532d', 950 => '#052e16'],
        'emerald' => [50 => '#ecfdf5', 100 => '#d1fae5', 200 => '#a7f3d0', 300 => '#6ee7b7', 400 => '#34d399', 500 => '#10b981', 600 => '#059669', 700 => '#047857', 800 => '#065f46', 900 => '#064e3b', 950 => '#022c22'],
        'teal' => [50 => '#f0fdfa', 100 => '#ccfbf1', 200 => '#99f6e4', 300 => '#5eead4', 400 => '#2dd4bf', 500 => '#14b8a6', 600 => '#0d9488', 700 => '#0f766e', 800 => '#115e59', 900 => '#134e4a', 950 => '#042f2e'],
        'cyan' => [50 => '#ecfeff', 100 => '#cffafe', 200 => '#a5f3fc', 300 => '#67e8f9', 400 => '#22d3ee', 500 => '#06b6d4', 600 => '#0891b2', 700 => '#0e7490', 800 => '#155e75', 900 => '#164e63', 950 => '#083344'],
        'sky' => [50 => '#f0f9ff', 100 => '#e0f2fe', 200 => '#bae6fd', 300 => '#7dd3fc', 400 => '#38bdf8', 500 => '#0ea5e9', 600 => '#0284c7', 700 => '#0369a1', 800 => '#075985', 900 => '#0c4a6e', 950 => '#082f49'],
        'blue' => [50 => '#eff6ff', 100 => '#dbeafe', 200 => '#bfdbfe', 300 => '#93c5fd', 400 => '#60a5fa', 500 => '#3b82f6', 600 => '#2563eb', 700 => '#1d4ed8', 800 => '#1e40af', 900 => '#1e3a8a', 950 => '#172554'],
        'indigo' => [50 => '#eef2ff', 100 => '#e0e7ff', 200 => '#c7d2fe', 300 => '#a5b4fc', 400 => '#818cf8', 500 => '#6366f1', 600 => '#4f46e5', 700 => '#4338ca', 800 => '#3730a3', 900 => '#312e81', 950 => '#1e1b4b'],
        'violet' => [50 => '#f5f3ff', 100 => '#ede9fe', 200 => '#ddd6fe', 300 => '#c4b5fd', 400 => '#a78bfa', 500 => '#8b5cf6', 600 => '#7c3aed', 700 => '#6d28d9', 800 => '#5b21b6', 900 => '#4c1d95', 950 => '#2e1065'],
        'purple' => [50 => '#faf5ff', 100 => '#f3e8ff', 200 => '#e9d5ff', 300 => '#d8b4fe', 400 => '#c084fc', 500 => '#a855f7', 600 => '#9333ea', 700 => '#7e22ce', 800 => '#6b21a8', 900 => '#581c87', 950 => '#3b0764'],
        'fuchsia' => [50 => '#fdf4ff', 100 => '#fae8ff', 200 => '#f5d0fe', 300 => '#f0abfc', 400 => '#e879f9', 500 => '#d946ef', 600 => '#c026d3', 700 => '#a21caf', 800 => '#86198f', 900 => '#701a75', 950 => '#4a044e'],
        'pink' => [50 => '#fdf2f8', 100 => '#fce7f3', 200 => '#fbcfe8', 300 => '#f9a8d4', 400 => '#f472b6', 500 => '#ec4899', 600 => '#db2777', 700 => '#be185d', 800 => '#9d174d', 900 => '#831843', 950 => '#500724'],
        'rose' => [50 => '#fff1f2', 100 => '#ffe4e6', 200 => '#fecdd3', 300 => '#fda4af', 400 => '#fb7185', 500 => '#f43f5e', 600 => '#e11d48', 700 => '#be123c', 800 => '#9f1239', 900 => '#881337', 950 => '#4c0519'],
    ];

    /**
     * Scan HTML content and compile production utility CSS rules.
     */
    public static function compile(string $html): string
    {
        $hash = md5($html);
        if (isset(self::$cache[$hash])) {
            return self::$cache[$hash];
        }

        $classes = self::extractClasses($html);
        $rules = [];
        $hoverRules = [];
        $focusRules = [];
        $activeRules = [];
        $mediaRules = [];

        foreach ($classes as $class) {
            $parsed = self::parseClass($class);
            if ($parsed !== null) {
                match ($parsed['type']) {
                    'standard' => $rules[$parsed['selector']] = $parsed['body'],
                    'hover' => $hoverRules[$parsed['selector']] = $parsed['body'],
                    'focus' => $focusRules[$parsed['selector']] = $parsed['body'],
                    'active' => $activeRules[$parsed['selector']] = $parsed['body'],
                    'media' => $mediaRules[$parsed['media']][$parsed['selector']] = $parsed['body'],
                    default => $rules[$parsed['selector']] = $parsed['body'],
                };
            }
        }

        $css = '';
        foreach ($rules as $sel => $body) $css .= "{$sel}{{$body}}\n";
        foreach ($hoverRules as $sel => $body) $css .= "{$sel}{{$body}}\n";
        foreach ($focusRules as $sel => $body) $css .= "{$sel}{{$body}}\n";
        foreach ($activeRules as $sel => $body) $css .= "{$sel}{{$body}}\n";

        foreach ($mediaRules as $media => $mRules) {
            $css .= "@media ({$media}) {\n";
            foreach ($mRules as $sel => $body) {
                $css .= "  {$sel}{{$body}}\n";
            }
            $css .= "}\n";
        }

        return self::$cache[$hash] = trim($css);
    }

    /**
     * Extract class names from HTML `class="..."` attributes.
     * @return array<string>
     */
    public static function extractClasses(string $html): array
    {
        $classes = [];
        if (preg_match_all('/class=["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $classString) {
                $tokens = preg_split('/\s+/', trim($classString));
                if ($tokens !== false) {
                    foreach ($tokens as $token) {
                        if ($token !== '') {
                            $classes[$token] = true;
                        }
                    }
                }
            }
        }
        return array_keys($classes);
    }

    /**
     * Parse an individual utility class token into CSS selector and body.
     */
    public static function parseClass(string $class): ?array
    {
        $escapedSelector = '.' . self::escapeCssIdentifier($class);

        $parts = preg_split('/:(?![^\[]*\])/', $class);
        if ($parts === false || empty($parts)) {
            return null;
        }

        $base = array_pop($parts);
        $innerParsed = self::parseBaseClass($base);
        if ($innerParsed === null) {
            return null;
        }

        if (empty($parts)) {
            return ['type' => 'standard', 'selector' => $escapedSelector, 'body' => $innerParsed];
        }

        $mediaQuery = null;
        $pseudoStates = [];
        $isDark = false;

        foreach ($parts as $variant) {
            match ($variant) {
                'sm' => $mediaQuery = 'min-width: 640px',
                'md' => $mediaQuery = 'min-width: 768px',
                'lg' => $mediaQuery = 'min-width: 1024px',
                'xl' => $mediaQuery = 'min-width: 1280px',
                '2xl' => $mediaQuery = 'min-width: 1536px',
                'hover' => $pseudoStates[] = ':hover',
                'focus' => $pseudoStates[] = ':focus',
                'active' => $pseudoStates[] = ':active',
                'disabled' => $pseudoStates[] = ':disabled',
                'focus-visible' => $pseudoStates[] = ':focus-visible',
                'first' => $pseudoStates[] = ':first-child',
                'last' => $pseudoStates[] = ':last-child',
                'dark' => $isDark = true,
                default => null,
            };
        }

        $selector = $escapedSelector . implode('', $pseudoStates);
        if ($isDark) {
            $selector = ".dark {$selector}";
        }

        if ($mediaQuery !== null) {
            return [
                'type' => 'media',
                'media' => $mediaQuery,
                'selector' => $selector,
                'body' => $innerParsed,
            ];
        }

        $primaryType = 'standard';
        if (in_array(':hover', $pseudoStates, true)) $primaryType = 'hover';
        elseif (in_array(':focus', $pseudoStates, true)) $primaryType = 'focus';
        elseif (in_array(':active', $pseudoStates, true)) $primaryType = 'active';

        return [
            'type' => $primaryType,
            'selector' => $selector,
            'body' => $innerParsed,
        ];
    }

    private static function parseBaseClass(string $c): ?string
    {
        // 1. Layout & Display
        if ($c === 'flex') return 'display:flex;';
        if ($c === 'inline-flex') return 'display:inline-flex;';
        if ($c === 'grid') return 'display:grid;';
        if ($c === 'block') return 'display:block;';
        if ($c === 'inline-block') return 'display:inline-block;';
        if ($c === 'inline') return 'display:inline;';
        if ($c === 'hidden') return 'display:none;';
        if ($c === 'flex-col') return 'flex-direction:column;';
        if ($c === 'flex-row') return 'flex-direction:row;';
        if ($c === 'flex-wrap') return 'flex-wrap:wrap;';
        if ($c === 'flex-1') return 'flex:1 1 0%;';
        if ($c === 'flex-auto') return 'flex:1 1 auto;';
        if ($c === 'flex-none') return 'flex:none;';
        if ($c === 'grow') return 'flex-grow:1;';
        if ($c === 'shrink-0') return 'flex-shrink:0;';
        if ($c === 'items-center') return 'align-items:center;';
        if ($c === 'items-start') return 'align-items:flex-start;';
        if ($c === 'items-end') return 'align-items:flex-end;';
        if ($c === 'items-stretch') return 'align-items:stretch;';
        if ($c === 'justify-between') return 'justify-content:space-between;';
        if ($c === 'justify-center') return 'justify-content:center;';
        if ($c === 'justify-start') return 'justify-content:flex-start;';
        if ($c === 'justify-end') return 'justify-content:flex-end;';
        if ($c === 'justify-around') return 'justify-content:space-around;';
        if ($c === 'w-full') return 'width:100%;';
        if ($c === 'w-screen') return 'width:100vw;';
        if ($c === 'w-auto') return 'width:auto;';
        if ($c === 'h-full') return 'height:100%;';
        if ($c === 'h-screen') return 'height:100vh;';
        if ($c === 'h-auto') return 'height:auto;';
        if ($c === 'min-h-screen') return 'min-height:100vh;';
        if ($c === 'min-h-full') return 'min-height:100%;';
        if ($c === 'relative') return 'position:relative;';
        if ($c === 'absolute') return 'position:absolute;';
        if ($c === 'fixed') return 'position:fixed;';
        if ($c === 'sticky') return 'position:sticky;';
        if ($c === 'inset-0') return 'inset:0px;';
        if ($c === 'top-0') return 'top:0px;';
        if ($c === 'bottom-0') return 'bottom:0px;';
        if ($c === 'left-0') return 'left:0px;';
        if ($c === 'right-0') return 'right:0px;';
        if ($c === 'overflow-hidden') return 'overflow:hidden;';
        if ($c === 'overflow-auto') return 'overflow:auto;';
        if ($c === 'overflow-x-auto') return 'overflow-x:auto;';
        if ($c === 'overflow-y-auto') return 'overflow-y:auto;';
        if ($c === 'z-10') return 'z-index:10;';
        if ($c === 'z-20') return 'z-index:20;';
        if ($c === 'z-30') return 'z-index:30;';
        if ($c === 'z-40') return 'z-index:40;';
        if ($c === 'z-50') return 'z-index:50;';

        // Grid columns & rows
        if (preg_match('/^grid-cols-(\d+)$/', $c, $m)) return "grid-template-columns:repeat({$m[1]},minmax(0,1fr));";
        if (preg_match('/^col-span-(\d+)$/', $c, $m)) return "grid-column:span {$m[1]} / span {$m[1]};";
        if ($c === 'col-span-full') return 'grid-column:1 / -1;';

        // Gap
        if (preg_match('/^gap-(\d+)$/', $c, $m)) return 'gap:' . ((int)$m[1] * 0.25) . 'rem;';
        if (preg_match('/^gap-x-(\d+)$/', $c, $m)) return 'column-gap:' . ((int)$m[1] * 0.25) . 'rem;';
        if (preg_match('/^gap-y-(\d+)$/', $c, $m)) return 'row-gap:' . ((int)$m[1] * 0.25) . 'rem;';

        // Width & Height (w-*, h-*, max-w-*)
        if (preg_match('/^w-(\d+)$/', $c, $m)) return 'width:' . ((int)$m[1] * 0.25) . 'rem;';
        if (preg_match('/^h-(\d+)$/', $c, $m)) return 'height:' . ((int)$m[1] * 0.25) . 'rem;';
        if ($c === 'max-w-xs') return 'max-width:20rem;';
        if ($c === 'max-w-sm') return 'max-width:24rem;';
        if ($c === 'max-w-md') return 'max-width:28rem;';
        if ($c === 'max-w-lg') return 'max-width:32rem;';
        if ($c === 'max-w-xl') return 'max-width:36rem;';
        if ($c === 'max-w-2xl') return 'max-width:42rem;';
        if ($c === 'max-w-3xl') return 'max-width:48rem;';
        if ($c === 'max-w-4xl') return 'max-width:56rem;';
        if ($c === 'max-w-5xl') return 'max-width:64rem;';
        if ($c === 'max-w-6xl') return 'max-width:72rem;';
        if ($c === 'max-w-7xl') return 'max-width:80rem;';
        if ($c === 'max-w-full') return 'max-width:100%;';

        // Padding (p, px, py, pt, pb, pl, pr)
        if (preg_match('/^p-(\d+(\.\d+)?)$/', $c, $m)) return 'padding:' . ((float)$m[1] * 0.25) . 'rem;';
        if (preg_match('/^px-(\d+(\.\d+)?)$/', $c, $m)) return 'padding-left:' . ((float)$m[1] * 0.25) . 'rem;padding-right:' . ((float)$m[1] * 0.25) . 'rem;';
        if (preg_match('/^py-(\d+(\.\d+)?)$/', $c, $m)) return 'padding-top:' . ((float)$m[1] * 0.25) . 'rem;padding-bottom:' . ((float)$m[1] * 0.25) . 'rem;';
        if (preg_match('/^pt-(\d+(\.\d+)?)$/', $c, $m)) return 'padding-top:' . ((float)$m[1] * 0.25) . 'rem;';
        if (preg_match('/^pb-(\d+(\.\d+)?)$/', $c, $m)) return 'padding-bottom:' . ((float)$m[1] * 0.25) . 'rem;';
        if (preg_match('/^pl-(\d+(\.\d+)?)$/', $c, $m)) return 'padding-left:' . ((float)$m[1] * 0.25) . 'rem;';
        if (preg_match('/^pr-(\d+(\.\d+)?)$/', $c, $m)) return 'padding-right:' . ((float)$m[1] * 0.25) . 'rem;';

        // Margin (m, mx, my, mt, mb, ml, mr)
        if (preg_match('/^m-(\d+(\.\d+)?)$/', $c, $m)) return 'margin:' . ((float)$m[1] * 0.25) . 'rem;';
        if (preg_match('/^mx-(\d+(\.\d+)?)$/', $c, $m)) return 'margin-left:' . ((float)$m[1] * 0.25) . 'rem;margin-right:' . ((float)$m[1] * 0.25) . 'rem;';
        if (preg_match('/^my-(\d+(\.\d+)?)$/', $c, $m)) return 'margin-top:' . ((float)$m[1] * 0.25) . 'rem;margin-bottom:' . ((float)$m[1] * 0.25) . 'rem;';
        if (preg_match('/^mt-(\d+(\.\d+)?)$/', $c, $m)) return 'margin-top:' . ((float)$m[1] * 0.25) . 'rem;';
        if (preg_match('/^mb-(\d+(\.\d+)?)$/', $c, $m)) return 'margin-bottom:' . ((float)$m[1] * 0.25) . 'rem;';
        if (preg_match('/^ml-(\d+(\.\d+)?)$/', $c, $m)) return 'margin-left:' . ((float)$m[1] * 0.25) . 'rem;';
        if (preg_match('/^mr-(\d+(\.\d+)?)$/', $c, $m)) return 'margin-right:' . ((float)$m[1] * 0.25) . 'rem;';
        if ($c === 'mx-auto') return 'margin-left:auto;margin-right:auto;';

        // Full Color Palette Matcher: bg-{color}-{shade}(/{opacity})
        if (preg_match('/^bg-([a-z]+)-(\d+)(\/(\d+))?$/', $c, $m)) {
            $fam = $m[1];
            $shade = (int)$m[2];
            $opacity = isset($m[4]) ? ((int)$m[4] / 100) : null;
            if (isset(self::$colorPalette[$fam][$shade])) {
                $hex = self::$colorPalette[$fam][$shade];
                if ($opacity !== null) {
                    $rgba = self::hexToRgba($hex, $opacity);
                    return "background-color:{$rgba};";
                }
                return "background-color:{$hex};";
            }
        }

        // Full Color Palette Matcher: text-{color}-{shade}(/{opacity})
        if (preg_match('/^text-([a-z]+)-(\d+)(\/(\d+))?$/', $c, $m)) {
            $fam = $m[1];
            $shade = (int)$m[2];
            $opacity = isset($m[4]) ? ((int)$m[4] / 100) : null;
            if (isset(self::$colorPalette[$fam][$shade])) {
                $hex = self::$colorPalette[$fam][$shade];
                if ($opacity !== null) {
                    $rgba = self::hexToRgba($hex, $opacity);
                    return "color:{$rgba};";
                }
                return "color:{$hex};";
            }
        }

        // Full Color Palette Matcher: border-{color}-{shade}(/{opacity})
        if (preg_match('/^border-([a-z]+)-(\d+)(\/(\d+))?$/', $c, $m)) {
            $fam = $m[1];
            $shade = (int)$m[2];
            $opacity = isset($m[4]) ? ((int)$m[4] / 100) : null;
            if (isset(self::$colorPalette[$fam][$shade])) {
                $hex = self::$colorPalette[$fam][$shade];
                if ($opacity !== null) {
                    $rgba = self::hexToRgba($hex, $opacity);
                    return "border-color:{$rgba};";
                }
                return "border-color:{$hex};";
            }
        }

        // Special Colors & Opacities (white, black, transparent)
        if ($c === 'bg-white') return 'background-color:#ffffff;';
        if ($c === 'bg-black') return 'background-color:#000000;';
        if ($c === 'bg-transparent') return 'background-color:transparent;';
        if ($c === 'text-white') return 'color:#ffffff;';
        if ($c === 'text-black') return 'color:#000000;';
        if ($c === 'text-transparent') return 'color:transparent;';
        if ($c === 'border-transparent') return 'border-color:transparent;';
        if ($c === 'border-white') return 'border-color:#ffffff;';
        if ($c === 'border-black') return 'border-color:#000000;';
        if (preg_match('/^bg-white\/(\d+)$/', $c, $m)) return 'background-color:rgba(255,255,255,' . ((int)$m[1]/100) . ');';
        if (preg_match('/^bg-black\/(\d+)$/', $c, $m)) return 'background-color:rgba(0,0,0,' . ((int)$m[1]/100) . ');';
        if (preg_match('/^border-white\/(\d+)$/', $c, $m)) return 'border-color:rgba(255,255,255,' . ((int)$m[1]/100) . ');';
        if (preg_match('/^border-black\/(\d+)$/', $c, $m)) return 'border-color:rgba(0,0,0,' . ((int)$m[1]/100) . ');';

        // Typography
        if ($c === 'text-xs') return 'font-size:0.75rem;line-height:1rem;';
        if ($c === 'text-sm') return 'font-size:0.875rem;line-height:1.25rem;';
        if ($c === 'text-base') return 'font-size:1rem;line-height:1.5rem;';
        if ($c === 'text-lg') return 'font-size:1.125rem;line-height:1.75rem;';
        if ($c === 'text-xl') return 'font-size:1.25rem;line-height:1.75rem;';
        if ($c === 'text-2xl') return 'font-size:1.5rem;line-height:2rem;';
        if ($c === 'text-3xl') return 'font-size:1.875rem;line-height:2.25rem;';
        if ($c === 'text-4xl') return 'font-size:2.25rem;line-height:2.5rem;';
        if ($c === 'text-5xl') return 'font-size:3rem;line-height:1;';
        if ($c === 'font-thin') return 'font-weight:100;';
        if ($c === 'font-light') return 'font-weight:300;';
        if ($c === 'font-normal') return 'font-weight:400;';
        if ($c === 'font-medium') return 'font-weight:500;';
        if ($c === 'font-semibold') return 'font-weight:600;';
        if ($c === 'font-bold') return 'font-weight:700;';
        if ($c === 'font-extrabold') return 'font-weight:800;';
        if ($c === 'font-mono') return 'font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace;';
        if ($c === 'text-left') return 'text-align:left;';
        if ($c === 'text-center') return 'text-align:center;';
        if ($c === 'text-right') return 'text-align:right;';
        if ($c === 'truncate') return 'overflow:hidden;text-overflow:ellipsis;white-space:nowrap;';
        if ($c === 'underline') return 'text-decoration:underline;';
        if ($c === 'no-underline') return 'text-decoration:none;';
        if ($c === 'line-through') return 'text-decoration:line-through;';

        // Borders & Radii
        if ($c === 'rounded-none') return 'border-radius:0px;';
        if ($c === 'rounded-sm') return 'border-radius:0.125rem;';
        if ($c === 'rounded-md') return 'border-radius:0.375rem;';
        if ($c === 'rounded-lg') return 'border-radius:0.5rem;';
        if ($c === 'rounded-xl') return 'border-radius:0.75rem;';
        if ($c === 'rounded-2xl') return 'border-radius:1rem;';
        if ($c === 'rounded-3xl') return 'border-radius:1.5rem;';
        if ($c === 'rounded-full') return 'border-radius:9999px;';
        if ($c === 'border') return 'border-width:1px;border-style:solid;';
        if ($c === 'border-2') return 'border-width:2px;border-style:solid;';
        if ($c === 'border-4') return 'border-width:4px;border-style:solid;';
        if ($c === 'border-t') return 'border-top-width:1px;border-top-style:solid;';
        if ($c === 'border-b') return 'border-bottom-width:1px;border-bottom-style:solid;';
        if ($c === 'border-l') return 'border-left-width:1px;border-left-style:solid;';
        if ($c === 'border-r') return 'border-right-width:1px;border-right-style:solid;';

        // Glassmorphism, Shadows & Effects
        if ($c === 'backdrop-blur-none') return 'backdrop-filter:blur(0);';
        if ($c === 'backdrop-blur-sm') return 'backdrop-filter:blur(4px);';
        if ($c === 'backdrop-blur-md') return 'backdrop-filter:blur(12px);';
        if ($c === 'backdrop-blur-lg') return 'backdrop-filter:blur(16px);';
        if ($c === 'backdrop-blur-xl') return 'backdrop-filter:blur(24px);';
        if ($c === 'shadow-sm') return 'box-shadow:0 1px 2px 0 rgba(0,0,0,0.05);';
        if ($c === 'shadow-md') return 'box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);';
        if ($c === 'shadow-lg') return 'box-shadow:0 10px 15px -3px rgba(0,0,0,0.3);';
        if ($c === 'shadow-xl') return 'box-shadow:0 20px 25px -5px rgba(0,0,0,0.4);';
        if ($c === 'shadow-2xl') return 'box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);';
        if ($c === 'shadow-none') return 'box-shadow:none;';
        if ($c === 'cursor-pointer') return 'cursor:pointer;';
        if ($c === 'cursor-not-allowed') return 'cursor:not-allowed;';
        if ($c === 'select-none') return 'user-select:none;';
        if (preg_match('/^opacity-(\d+)$/', $c, $m)) return 'opacity:' . ((int)$m[1] / 100) . ';';

        // Transitions, Transforms & Animations
        if ($c === 'transition-all') return 'transition-property:all;transition-timing-function:cubic-bezier(0.4,0,0.2,1);transition-duration:150ms;';
        if ($c === 'transition-colors') return 'transition-property:color,background-color,border-color;transition-duration:150ms;';
        if ($c === 'duration-100') return 'transition-duration:100ms;';
        if ($c === 'duration-150') return 'transition-duration:150ms;';
        if ($c === 'duration-200') return 'transition-duration:200ms;';
        if ($c === 'duration-300') return 'transition-duration:300ms;';
        if ($c === 'duration-500') return 'transition-duration:500ms;';
        if ($c === 'scale-95') return 'transform:scale(0.95);';
        if ($c === 'scale-100') return 'transform:scale(1);';
        if ($c === 'scale-105') return 'transform:scale(1.05);';
        if ($c === 'scale-110') return 'transform:scale(1.1);';
        if ($c === 'animate-pulse') return 'animation:pulse 2s cubic-bezier(0.4,0,0.6,1) infinite;';
        if ($c === 'animate-spin') return 'animation:spin 1s linear infinite;';
        if ($c === 'animate-ping') return 'animation:ping 1s cubic-bezier(0,0,0.2,1) infinite;';

        $isNegative = str_starts_with($c, '-');
        $normalized = $isNegative ? substr($c, 1) : $c;

        // Arbitrary Value Bracket Handling: e.g. w-[350px], bg-[#123456], text-[#fff], rounded-[12px], etc.
        if (preg_match('/^([a-z-]+)-\[([^\]]+)\]$/', $normalized, $m)) {
            $prop = $m[1];
            $val = str_replace('_', ' ', $m[2]);
            if ($isNegative) {
                $val = str_starts_with($val, '-') ? substr($val, 1) : "-{$val}";
            }
            return match ($prop) {
                'w' => "width:{$val};",
                'h' => "height:{$val};",
                'min-w' => "min-width:{$val};",
                'max-w' => "max-width:{$val};",
                'min-h' => "min-height:{$val};",
                'max-h' => "max-height:{$val};",
                'p' => "padding:{$val};",
                'px' => "padding-left:{$val};padding-right:{$val};",
                'py' => "padding-top:{$val};padding-bottom:{$val};",
                'pt' => "padding-top:{$val};",
                'pb' => "padding-bottom:{$val};",
                'pl' => "padding-left:{$val};",
                'pr' => "padding-right:{$val};",
                'm' => "margin:{$val};",
                'mx' => "margin-left:{$val};margin-right:{$val};",
                'my' => "margin-top:{$val};margin-bottom:{$val};",
                'mt' => "margin-top:{$val};",
                'mb' => "margin-bottom:{$val};",
                'ml' => "margin-left:{$val};",
                'mr' => "margin-right:{$val};",
                'bg' => "background-color:{$val};",
                'text' => (str_starts_with($val, '#') || str_starts_with($val, 'rgb') || str_starts_with($val, 'hsl')) ? "color:{$val};" : "font-size:{$val};",
                'border' => (str_starts_with($val, '#') || str_starts_with($val, 'rgb')) ? "border-color:{$val};" : "border-width:{$val};border-style:solid;",
                'rounded' => "border-radius:{$val};",
                'gap' => "gap:{$val};",
                'top' => "top:{$val};",
                'bottom' => "bottom:{$val};",
                'left' => "left:{$val};",
                'right' => "right:{$val};",
                'z' => "z-index:{$val};",
                'grid-cols' => "grid-template-columns:{$val};",
                default => null,
            };
        }

        // Negative standard spacing & positioning
        if (preg_match('/^-top-(\d+(\.\d+)?)$/', $c, $m)) return 'top:-' . ((float)$m[1] * 0.25) . 'rem;';
        if (preg_match('/^-bottom-(\d+(\.\d+)?)$/', $c, $m)) return 'bottom:-' . ((float)$m[1] * 0.25) . 'rem;';
        if (preg_match('/^-left-(\d+(\.\d+)?)$/', $c, $m)) return 'left:-' . ((float)$m[1] * 0.25) . 'rem;';
        if (preg_match('/^-right-(\d+(\.\d+)?)$/', $c, $m)) return 'right:-' . ((float)$m[1] * 0.25) . 'rem;';
        if (preg_match('/^-m-(\d+(\.\d+)?)$/', $c, $m)) return 'margin:-' . ((float)$m[1] * 0.25) . 'rem;';
        if (preg_match('/^-mt-(\d+(\.\d+)?)$/', $c, $m)) return 'margin-top:-' . ((float)$m[1] * 0.25) . 'rem;';
        if (preg_match('/^-mb-(\d+(\.\d+)?)$/', $c, $m)) return 'margin-bottom:-' . ((float)$m[1] * 0.25) . 'rem;';
        if (preg_match('/^-ml-(\d+(\.\d+)?)$/', $c, $m)) return 'margin-left:-' . ((float)$m[1] * 0.25) . 'rem;';
        if (preg_match('/^-mr-(\d+(\.\d+)?)$/', $c, $m)) return 'margin-right:-' . ((float)$m[1] * 0.25) . 'rem;';
        if (preg_match('/^-z-(\d+)$/', $c, $m)) return 'z-index:-' . $m[1] . ';';

        return null;
    }

    private static function hexToRgba(string $hex, float $alpha): string
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return "rgba({$r},{$g},{$b},{$alpha})";
    }

    private static function escapeCssIdentifier(string $id): string
    {
        return preg_replace('/([:\/\[\]\.%#(),!$])/', '\\\\$1', $id);
    }
}
