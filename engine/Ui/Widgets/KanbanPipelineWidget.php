<?php
declare(strict_types=1);

namespace Oshim\Ui\Widgets;

use Oshim\Ui\Dsl\Element;

/**
 * KanbanPipelineWidget: Drag-and-Drop Sovereign Kanban Pipeline.
 * Zero-dependency interactive pipeline for sprint planning and AI task workflows.
 */
class KanbanPipelineWidget extends Element
{
    /** @var array<string, list<array{id: string, title: string, priority: string, tag: string, assignee: string}>> */
    private array $columns;

    /**
     * @param array<string, list<array{id: string, title: string, priority: string, tag: string, assignee: string}>> $columns
     */
    public function __construct(array $columns = [])
    {
        parent::__construct('div');
        $this->class('oshim-kanban-pipeline-widget');

        $this->columns = !empty($columns) ? $columns : [
            'Backlog' => [
                ['id' => 'kb_1', 'title' => 'Implement Quantum Resistance Hash', 'priority' => 'high', 'tag' => 'Crypto', 'assignee' => 'AI-01'],
                ['id' => 'kb_2', 'title' => 'Optimize Fiber RingBuffer Allocator', 'priority' => 'medium', 'tag' => 'Core', 'assignee' => 'Dev-02'],
            ],
            'In Progress' => [
                ['id' => 'kb_3', 'title' => 'Real-Time WebRTC Media Relay', 'priority' => 'high', 'tag' => 'Network', 'assignee' => 'Dev-01'],
                ['id' => 'kb_4', 'title' => 'Swarm Leader Gossip Consensus', 'priority' => 'medium', 'tag' => 'Swarm', 'assignee' => 'AI-03'],
            ],
            'AI Verification' => [
                ['id' => 'kb_5', 'title' => 'Self-Healing AST Syntax Mutation', 'priority' => 'critical', 'tag' => 'AI', 'assignee' => 'Healer'],
            ],
            'Deployed' => [
                ['id' => 'kb_6', 'title' => 'Pure PHP Tailwind JIT Compiler', 'priority' => 'low', 'tag' => 'UI', 'assignee' => 'Core'],
                ['id' => 'kb_7', 'title' => 'Wasm Runtime & Bytecode Engine', 'priority' => 'high', 'tag' => 'Wasm', 'assignee' => 'Engine'],
            ],
        ];
    }

    public static function create(array $columns = []): self
    {
        return new self($columns);
    }

    public function render(): string
    {
        $colsHtml = '';

        foreach ($this->columns as $colName => $cards) {
            $cardCount = count($cards);
            $cardsHtml = '';

            foreach ($cards as $card) {
                $priorityColor = match ($card['priority']) {
                    'critical' => 'border-rose-500/40 bg-rose-500/10 text-rose-300',
                    'high' => 'border-amber-500/40 bg-amber-500/10 text-amber-300',
                    'medium' => 'border-cyan-500/40 bg-cyan-500/10 text-cyan-300',
                    default => 'border-slate-500/40 bg-slate-500/10 text-slate-300',
                };

                $cardsHtml .= <<<HTML
                <div id="{$card['id']}" draggable="true" ondragstart="event.dataTransfer.setData('text/plain', event.target.id)" class="p-3.5 rounded-xl bg-slate-900/90 border border-slate-800/80 hover:border-cyan-500/50 hover:shadow-[0_0_15px_rgba(6,182,212,0.15)] transition-all cursor-grab active:cursor-grabbing group">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] uppercase font-bold font-mono px-2 py-0.5 rounded border {$priorityColor}">
                            {$card['priority']}
                        </span>
                        <span class="text-[10px] text-slate-500 font-mono">{$card['tag']}</span>
                    </div>
                    <h4 class="text-xs font-semibold text-slate-200 group-hover:text-cyan-300 transition-colors leading-snug">
                        {$card['title']}
                    </h4>
                    <div class="flex items-center justify-between mt-3 pt-2 border-t border-slate-800/60 text-[11px] text-slate-400 font-mono">
                        <span>#{$card['id']}</span>
                        <div class="flex items-center space-x-1">
                            <span class="w-4 h-4 rounded-full bg-cyan-500/20 text-cyan-400 text-[9px] flex items-center justify-center font-bold">👤</span>
                            <span>{$card['assignee']}</span>
                        </div>
                    </div>
                </div>
HTML;
            }

            $colsHtml .= <<<HTML
            <div class="flex-1 min-w-[260px] bg-slate-950/60 rounded-2xl border border-slate-800/80 p-4 flex flex-col space-y-3"
                 ondragover="event.preventDefault(); this.classList.add('bg-cyan-950/20');"
                 ondragleave="this.classList.remove('bg-cyan-950/20');"
                 ondrop="event.preventDefault(); this.classList.remove('bg-cyan-950/20'); const id = event.dataTransfer.getData('text/plain'); const el = document.getElementById(id); if (el) this.querySelector('.oshim-kanban-dropzone').appendChild(el);">
                <div class="flex items-center justify-between pb-2 border-b border-slate-800/70">
                    <h3 class="text-xs font-bold font-mono uppercase tracking-wider text-slate-300 flex items-center space-x-2">
                        <span>{$colName}</span>
                    </h3>
                    <span class="text-xs font-mono px-2 py-0.5 rounded-full bg-slate-800 text-slate-400 font-bold">{$cardCount}</span>
                </div>
                <div class="oshim-kanban-dropzone flex flex-col space-y-2.5 min-h-[140px]">
                    {$cardsHtml}
                </div>
            </div>
HTML;
        }

        return <<<HTML
<div class="oshim-kanban-pipeline p-6 rounded-2xl bg-[#090d16]/90 border border-slate-800 shadow-2xl backdrop-blur-2xl">
    <div class="flex items-center justify-between mb-5">
        <div class="flex items-center space-x-3">
            <span class="w-8 h-8 rounded-lg bg-cyan-500/20 text-cyan-400 flex items-center justify-center font-bold text-sm">📋</span>
            <div>
                <h2 class="text-base font-bold text-slate-100 font-mono">Sovereign Agile & Multi-Agent Kanban Pipeline</h2>
                <p class="text-xs text-slate-400 font-mono">Interactive Drag-and-Drop Workflow Orchestration</p>
            </div>
        </div>
        <button class="px-3 py-1.5 text-xs font-mono font-bold rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition-colors">
            + New Task
        </button>
    </div>

    <div class="flex space-x-4 overflow-x-auto pb-2">
        {$colsHtml}
    </div>
</div>
HTML;
    }
}
