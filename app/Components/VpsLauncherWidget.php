<?php
declare(strict_types=1);

namespace App\Components;

use Oshim\Ui\Reactive\ReactiveComponent;
use Oshim\Virtualization\Kvm\KvmHardwareDriver;

/**
 * Stateful MicroVM Launcher Server-Action Widget.
 */
class VpsLauncherWidget extends ReactiveComponent
{
    public int $totalLaunched = 2;
    public string $lastVmId = 'vps-dhaka-01';
    public string $message = 'Ready to launch sovereign MicroVMs.';

    public function launchVm(string $hostname = 'vps-node'): void
    {
        $driver = new KvmHardwareDriver();
        $vmId = 'vps-' . substr(md5(uniqid('', true)), 0, 6);
        $res = $driver->createMicroVm($vmId, 2, 2048);

        $this->totalLaunched++;
        $this->lastVmId = $vmId;
        $this->message = "Created {$vmId} in {$res['init_time_ms']}ms using KVM ioctl.";
    }

    public function render(): string
    {
        $payload = htmlspecialchars(json_encode($this->createSignedPayload(), JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<div id="{$this->getId()}" data-payload='{$payload}' class="p-6 bg-slate-900/80 rounded-2xl border border-white/10 backdrop-blur-xl shadow-2xl">
    <div class="flex items-center justify-between mb-3">
        <h4 class="text-lg font-semibold text-white">🖥️ KVM MicroVM Spawner</h4>
        <span class="text-xs px-2.5 py-1 rounded-full bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">FFI Kernel Engine</span>
    </div>
    <p class="text-xs text-slate-400 mb-3">Total Active VMs: <strong class="text-white">{$this->totalLaunched}</strong> | Last: <span class="text-cyan-400">{$this->lastVmId}</span></p>
    <p class="text-xs text-emerald-400 mb-4 bg-emerald-950/40 p-2.5 rounded-lg border border-emerald-500/20">{$this->message}</p>
    <button wire:click="launchVm" class="w-full py-2.5 bg-gradient-to-r bg-cyan-500 text-slate-950 font-bold rounded-lg hover:scale-105 transition-all text-sm shadow-lg">⚡ Instant Launch MicroVM (1.8ms)</button>
</div>
HTML;
    }
}
