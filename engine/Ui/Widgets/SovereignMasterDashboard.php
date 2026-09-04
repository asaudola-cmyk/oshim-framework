<?php
declare(strict_types=1);

namespace Oshim\Ui\Widgets;

use Oshim\Ui\Css\TailwindJitCompiler;
use Oshim\Ui\Dsl\Element;

/**
 * SovereignMasterDashboard: Flagship God-Tier Workstation & Cyberpunk HUD.
 * Combines particle starfield, live telemetry gauges, 3D hologram cards,
 * interactive code studio, drag-and-drop Kanban, and real-time SVG charts.
 */
class SovereignMasterDashboard extends Element
{
    private string $title;

    public function __construct(string $title = 'OSHIM Universal Sovereign HUD & Studio')
    {
        parent::__construct('div');
        $this->title = $title;
        $this->class('oshim-master-dashboard-container');
    }

    public static function create(string $title = 'OSHIM Universal Sovereign HUD & Studio'): self
    {
        return new self($title);
    }

    public function render(): string
    {
        // 1. Particle Background
        $particles = (new ParticleBackgroundWidget('#00f2fe', 50))->render();

        // 2. Telemetry HUD
        $telemetry = (new TelemetryHudWidget())->render();

        // 3. 3D Hologram Cards Grid
        $card1 = (new HologramCardWidget(
            'Sovereign AI Engine',
            'LangGraph & Tensor Core',
            '<p>Cyclic stateful agent graphs, TF-IDF semantic embeddings, and local/multi-provider LLM streaming with self-correction.</p>',
            '#00f2fe',
            '🧠'
        ))->render();

        $card2 = (new HologramCardWidget(
            'Immutable Ledger',
            'Cryptographic Blockchain',
            '<p>Pure PHP binary Merkle trees, SHA-256 blocks, zero-knowledge audit trails, and untamperable financial ledger.</p>',
            '#10b981',
            '⛓️'
        ))->render();

        $card3 = (new HologramCardWidget(
            'Electron Killer',
            'Native Desktop Packager',
            '<p>Single-click native desktop compilation for Windows (Edge WebView2), Linux (WebKit2GTK), and macOS (.app).</p>',
            '#c084fc',
            '⚡'
        ))->render();

        // 4. Code Studio Widget
        $sampleCode = <<<PHP
<?php
// OSHIM Sovereign Autonomous MicroVM & AI Reactor
use Oshim\Ai\Agents\AgentTeam;
use Oshim\Ledger\Blockchain;

\$ledger = new Blockchain(difficulty: 2);
\$squad = new AgentTeam('Autonomous Ops');

\$squad->delegate('Optimize Fiber Loop', function (\$agent) use (\$ledger) {
    \$ledger->record(['status' => 'OPTIMIZED', 'tps' => 1400000]);
    return \$ledger->minePending();
});
PHP;
        $codeStudio = (new CodeStudioWidget('Reactor.php', $sampleCode, 'php', '✔ Mined Block #14893 | TPS: 1,400,000 | Memory: 34MB'))->render();

        // 5. Kanban Pipeline Widget
        $kanban = (new KanbanPipelineWidget())->render();

        // 6. Chart Widget
        $chart = (new ChartWidget('Event Loop Throughput (kRPS)', [120, 350, 680, 920, 1150, 1380, 1420], 'area', '#00f2fe'))->render();

        return <<<HTML
<div class="min-h-screen bg-[#060911] text-slate-100 font-sans relative selection:bg-cyan-500 selection:text-black">
    {$particles}

    <!-- Top Navigation Bar -->
    <header class="relative z-10 border-b border-slate-800/80 bg-slate-950/70 backdrop-blur-2xl px-6 py-4 flex items-center justify-between sticky top-0">
        <div class="flex items-center space-x-4">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-cyan-500 to-indigo-500 flex items-center justify-center font-black text-slate-950 text-xl shadow-[0_0_20px_rgba(6,182,212,0.5)]">
                Ω
            </div>
            <div>
                <h1 class="text-lg font-black tracking-wider uppercase font-mono bg-clip-text text-transparent bg-gradient-to-r from-cyan-400 to-indigo-400">
                    OSHIM SOVEREIGN WORKSTATION
                </h1>
                <p class="text-[11px] text-slate-400 font-mono">v2.0.0 • Pure PHP 8.3+ Zero-Dependency • Sovereign Cloud</p>
            </div>
        </div>

        <div class="flex items-center space-x-3">
            <div class="hidden sm:flex items-center space-x-2 px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-800 text-xs font-mono text-slate-400">
                <span>Quick Search</span>
                <kbd class="px-1.5 py-0.5 rounded bg-slate-800 text-[10px] text-cyan-300 font-bold border border-slate-700">Ctrl+K</kbd>
            </div>
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping"></span>
            <span class="text-xs font-bold font-mono text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-2.5 py-1 rounded-full">
                ONLINE
            </span>
        </div>
    </header>

    <!-- Main Grid Workspace -->
    <main class="relative z-10 p-6 max-w-7xl mx-auto space-y-6">
        <!-- Telemetry HUD -->
        {$telemetry}

        <!-- 3D Holographic Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            {$card1}
            {$card2}
            {$card3}
        </div>

        <!-- Middle Split: Code Studio & Real-Time Performance Chart -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            <div class="lg:col-span-2">
                {$codeStudio}
            </div>
            <div class="lg:col-span-1">
                {$chart}
            </div>
        </div>

        <!-- Kanban Interactive Pipeline -->
        {$kanban}
    </main>

    <!-- Footer -->
    <footer class="relative z-10 border-t border-slate-800/80 bg-slate-950/80 px-6 py-4 text-center text-xs text-slate-500 font-mono">
        OSHIM Autonomous Framework • Designed for Sovereign High-Performance Cloud Computing • Zero Dependencies
    </footer>
</div>
HTML;
    }

    /**
     * Render as complete standalone HTML5 Document with compiled Tailwind JIT CSS.
     */
    public function renderFullPage(): string
    {
        $body = $this->render();
        $css = TailwindJitCompiler::compile($body);

        return <<<HTML
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$this->title}</title>
    <style>
        {$css}
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #060911; }
        ::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #06b6d4; }
    </style>
</head>
<body style="margin: 0; background: #060911;">
    {$body}
</body>
</html>
HTML;
    }
}
