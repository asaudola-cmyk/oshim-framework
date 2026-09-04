<?php
declare(strict_types=1);

namespace App\Components;

use Oshim\Ui\Reactive\ReactiveComponent;

/**
 * Stateful Reactive Counter Widget with signed HMAC state.
 */
class CounterWidget extends ReactiveComponent
{
    public int $count = 0;
    public string $status = 'Ready';

    public function increment(): void
    {
        $this->count++;
        $this->status = 'Incremented to ' . $this->count;
    }

    public function decrement(): void
    {
        if ($this->count > 0) {
            $this->count--;
        }
        $this->status = 'Decremented to ' . $this->count;
    }

    public function resetCount(): void
    {
        $this->count = 0;
        $this->status = 'Reset to 0';
    }

    public function render(): string
    {
        $payload = htmlspecialchars(json_encode($this->createSignedPayload(), JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<div id="{$this->getId()}" data-payload='{$payload}' class="p-6 bg-slate-900/80 rounded-2xl border border-white/10 backdrop-blur-xl shadow-2xl">
    <div class="flex items-center justify-between mb-4">
        <h4 class="text-lg font-semibold text-white">⚡ Reactive Counter</h4>
        <span class="text-xs px-2.5 py-1 rounded-full bg-cyan-500/20 text-cyan-400 border border-cyan-500/30">LiveWire DSL</span>
    </div>
    <div class="flex items-center gap-4 mb-4">
        <span class="text-4xl font-bold text-cyan-400">{$this->count}</span>
        <span class="text-xs text-slate-400">({$this->status})</span>
    </div>
    <div class="flex items-center gap-2">
        <button wire:click="increment" class="px-4 py-2 bg-cyan-500 text-slate-950 font-semibold rounded-lg hover:scale-105 transition-all text-sm">+1 Add</button>
        <button wire:click="decrement" class="px-4 py-2 bg-slate-800 text-white font-semibold rounded-lg hover:scale-105 transition-all text-sm">-1 Sub</button>
        <button wire:click="resetCount" class="px-4 py-2 bg-rose-500/20 text-rose-400 border border-rose-500/30 rounded-lg hover:scale-105 transition-all text-sm">Reset</button>
    </div>
</div>
HTML;
    }
}
