<?php
declare(strict_types=1);

namespace Oshim\Ui\Widgets;

use Oshim\Ui\Dsl\Element;

/**
 * BentoGridWidget: Vercel & Apple-Style Asymmetric Bento Grid.
 * Displays high-impact visual feature cards with variable spans, glow borders, and hover micro-animations.
 */
class BentoGridWidget extends Element
{
    /** @var list<array{
     *     title: string,
     *     subtitle: string,
     *     content: string,
     *     col_span: int,
     *     row_span: int,
     *     accent: string,
     *     icon: string,
     *     badge: ?string
     * }> */
    private array $items = [];

    public function __construct(array $items = [])
    {
        parent::__construct('div');
        $this->class('oshim-bento-grid-widget');
        $this->items = $items;
    }

    public static function create(array $items = []): self
    {
        return new self($items);
    }

    public function addItem(
        string $title,
        string $subtitle,
        string $content = '',
        int $colSpan = 1,
        int $rowSpan = 1,
        string $accent = '#00f2fe',
        string $icon = '✦',
        ?string $badge = null
    ): self {
        $this->items[] = [
            'title' => $title,
            'subtitle' => $subtitle,
            'content' => $content,
            'col_span' => max(1, min(3, $colSpan)),
            'row_span' => max(1, min(2, $rowSpan)),
            'accent' => $accent,
            'icon' => $icon,
            'badge' => $badge,
        ];
        return $this;
    }

    public function render(): string
    {
        if (empty($this->items)) {
            // Default sample items showcasing OSHIM vs React/Next
            $this->addItem('Zero-JS Server Actions', 'RSC Streaming Architecture', '<p>Pure PHP fibers stream HTML without bulky Node.js or 500KB client-side hydration runtimes.</p>', 2, 1, '#00f2fe', '⚡', 'NEXT-KILLER');
            $this->addItem('KVM MicroVM Sandbox', '<50ms Isolated Virtualization', '<p>Run untrusted code in bare-metal hardware virtualization.</p>', 1, 1, '#10b981', '🛡️', 'HARDWARE');
            $this->addItem('Wasm Engine Core', 'Pure Bytecode Interpretation', '<p>Run compiled Rust & C libraries in PHP memory space.</p>', 1, 1, '#a855f7', '⚙️', 'WASM');
            $this->addItem('Autonomous Multi-Agent AI', 'LangGraph Equivalent State Machine', '<p>Squad coordination, cyclic agent graphs, and dense TF-IDF semantic RAG vector retrieval.</p>', 2, 1, '#f59e0b', '🧠', 'AI SQUAD');
        }

        $cardsHtml = '';
        foreach ($this->items as $item) {
            $colClass = match ($item['col_span']) {
                2 => 'md:col-span-2',
                3 => 'md:col-span-3',
                default => 'md:col-span-1',
            };
            $rowClass = ($item['row_span'] === 2) ? 'md:row-span-2' : 'md:row-span-1';

            $badgeHtml = '';
            if ($item['badge'] !== null) {
                $badgeHtml = <<<HTML
                <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-full uppercase tracking-wider" style="background: {$item['accent']}15; color: {$item['accent']}; border: 1px solid {$item['accent']}40;">
                    {$item['badge']}
                </span>
HTML;
            }

            $cardsHtml .= <<<HTML
            <div class="group relative rounded-3xl p-6 bg-slate-900/60 border border-slate-800/80 hover:border-cyan-500/40 transition-all duration-500 hover:shadow-[0_20px_40px_rgba(0,0,0,0.6)] backdrop-blur-xl overflow-hidden flex flex-col justify-between {$colClass} {$rowClass}">
                <div class="absolute -inset-px rounded-3xl opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none" style="background: radial-gradient(400px circle at top left, {$item['accent']}15, transparent 60%);"></div>

                <div>
                    <div class="flex items-center justify-between mb-4">
                        <span class="w-10 h-10 rounded-2xl flex items-center justify-center text-lg shadow-inner" style="background: {$item['accent']}20; color: {$item['accent']}; border: 1px solid {$item['accent']}40;">
                            {$item['icon']}
                        </span>
                        {$badgeHtml}
                    </div>
                    <h3 class="text-base font-bold text-slate-100 group-hover:text-cyan-300 transition-colors tracking-wide font-sans">
                        {$item['title']}
                    </h3>
                    <p class="text-xs text-slate-400 font-mono mt-1 mb-4">
                        {$item['subtitle']}
                    </p>
                    <div class="text-xs text-slate-300 leading-relaxed">
                        {$item['content']}
                    </div>
                </div>

                <div class="mt-6 pt-3 border-t border-slate-800/60 flex items-center justify-between text-xs text-slate-500 font-mono">
                    <span class="group-hover:text-slate-300 transition-colors">Explore Architecture →</span>
                    <span class="w-1.5 h-1.5 rounded-full" style="background: {$item['accent']};"></span>
                </div>
            </div>
HTML;
        }

        return <<<HTML
<div class="oshim-bento-grid grid grid-cols-1 md:grid-cols-3 gap-5 max-w-7xl mx-auto">
    {$cardsHtml}
</div>
HTML;
    }
}
