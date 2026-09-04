<?php
declare(strict_types=1);

namespace Oshim\Ui\Widgets;

use Oshim\Ui\Dsl\Element;

/**
 * TelemetryHudWidget: Mission-Control Real-Time Telemetry HUD.
 * Renders circular SVG radial progress gauges and live metric tickers.
 */
class TelemetryHudWidget extends Element
{
    /** @var array<string, array{value: int|float, max: int|float, unit: string, color: string, icon: string}> */
    private array $gauges;
    /** @var array<string, string> */
    private array $tickers;

    public function __construct(array $gauges = [], array $tickers = [])
    {
        parent::__construct('div');
        $this->class('oshim-telemetry-hud-widget');

        $this->gauges = !empty($gauges) ? $gauges : [
            'CPU Engine' => ['value' => 14, 'max' => 100, 'unit' => '%', 'color' => '#00f2fe', 'icon' => '⚡'],
            'RAM Allocation' => ['value' => 34, 'max' => 512, 'unit' => 'MB', 'color' => '#10b981', 'icon' => '💾'],
            'Fiber Loop' => ['value' => 99.8, 'max' => 100, 'unit' => '%', 'color' => '#a855f7', 'icon' => '🔄'],
            'Swarm Sync' => ['value' => 12, 'max' => 100, 'unit' => 'ms', 'color' => '#f59e0b', 'icon' => '🌐'],
        ];

        $this->tickers = !empty($tickers) ? $tickers : [
            'Throughput Capacity' => '1.4M RPS (Turbo SQPOLL)',
            'Virtualization' => 'KVM MicroVM Driver Active',
            'Ledger Block Height' => '#14,892 Cryptographic',
            'Zero Dependency' => '100% Sovereign Pure PHP',
        ];
    }

    public static function create(array $gauges = [], array $tickers = []): self
    {
        return new self($gauges, $tickers);
    }

    public function render(): string
    {
        $gaugeHtml = '';
        foreach ($this->gauges as $label => $g) {
            $percentage = min(100, max(0, ($g['value'] / $g['max']) * 100));
            $radius = 36;
            $circumference = 2 * M_PI * $radius;
            $offset = $circumference - ($percentage / 100 * $circumference);
            $color = $g['color'];

            $gaugeHtml .= <<<HTML
            <div class="flex flex-col items-center justify-center p-4 rounded-xl bg-slate-900/60 border border-slate-800/80 shadow-inner">
                <div class="relative w-24 h-24 flex items-center justify-center">
                    <svg class="w-24 h-24 transform -rotate-90" viewBox="0 0 90 90">
                        <circle cx="45" cy="45" r="{$radius}" stroke="#1e293b" stroke-width="6" fill="transparent" />
                        <circle cx="45" cy="45" r="{$radius}" stroke="{$color}" stroke-width="6" fill="transparent"
                            stroke-dasharray="{$circumference}"
                            stroke-dashoffset="{$offset}"
                            stroke-linecap="round"
                            style="transition: stroke-dashoffset 1s ease-in-out; filter: drop-shadow(0 0 6px {$color}80);" />
                    </svg>
                    <div class="absolute flex flex-col items-center justify-center text-center">
                        <span class="text-xs">{$g['icon']}</span>
                        <span class="text-sm font-bold font-mono text-slate-100">{$g['value']}<span class="text-[10px] text-slate-400">{$g['unit']}</span></span>
                    </div>
                </div>
                <span class="mt-2 text-xs font-medium text-slate-300 tracking-wider uppercase font-mono">{$label}</span>
            </div>
HTML;
        }

        $tickerHtml = '';
        foreach ($this->tickers as $k => $v) {
            $tickerHtml .= <<<HTML
            <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-slate-950/40 border border-slate-800/50">
                <span class="text-xs text-slate-400 font-mono">{$k}</span>
                <span class="text-xs font-semibold font-mono text-cyan-400 flex items-center space-x-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-ping"></span>
                    <span>{$v}</span>
                </span>
            </div>
HTML;
        }

        return <<<HTML
<div class="oshim-telemetry-hud p-6 rounded-2xl bg-[#090d16]/90 border border-cyan-500/20 shadow-2xl backdrop-blur-2xl">
    <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-800">
        <div class="flex items-center space-x-3">
            <span class="w-3 h-3 rounded-full bg-emerald-400 shadow-[0_0_12px_#10b981] animate-pulse"></span>
            <h2 class="text-base font-bold text-slate-100 tracking-wider uppercase font-mono">Telemetry & Sovereign Kernel Telemetry HUD</h2>
        </div>
        <span class="text-xs font-mono text-slate-400 px-2.5 py-1 rounded bg-slate-800/80 border border-slate-700">KERNEL STATUS: OPTIMAL</span>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        {$gaugeHtml}
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2">
        {$tickerHtml}
    </div>
</div>
HTML;
    }
}
