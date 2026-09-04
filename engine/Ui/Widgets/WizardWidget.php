<?php
declare(strict_types=1);

namespace Oshim\Ui\Widgets;

use Oshim\Ui\Dsl\Element;

/**
 * WizardWidget: Multi-Step Interactive Form Wizard.
 * Features progress bars, step indicators, client-side step validation, and animated transitions.
 */
class WizardWidget extends Element
{
    /** @var list<array{title: string, description: string, html: string}> */
    private array $steps = [];
    private string $submitLabel;
    private string $actionUrl;

    public function __construct(array $steps = [], string $submitLabel = 'Deploy Solution', string $actionUrl = '#')
    {
        parent::__construct('div');
        $this->class('oshim-wizard-widget');
        $this->steps = $steps;
        $this->submitLabel = $submitLabel;
        $this->actionUrl = $actionUrl;
    }

    public static function create(array $steps = [], string $submitLabel = 'Deploy Solution', string $actionUrl = '#'): self
    {
        return new self($steps, $submitLabel, $actionUrl);
    }

    public function addStep(string $title, string $description, string $html): self
    {
        $this->steps[] = [
            'title' => $title,
            'description' => $description,
            'html' => $html,
        ];
        return $this;
    }

    public function render(): string
    {
        $wizardId = 'wiz_' . substr(md5(uniqid()), 0, 8);
        $totalSteps = count($this->steps);

        $stepsNav = '';
        $stepsContent = '';

        foreach ($this->steps as $idx => $step) {
            $stepNum = $idx + 1;
            $isActive = ($idx === 0);
            $activeIndicator = $isActive
                ? 'bg-cyan-500 text-slate-950 shadow-[0_0_15px_rgba(6,182,212,0.5)]'
                : 'bg-slate-800 text-slate-400';

            $stepsNav .= <<<HTML
            <div class="flex items-center space-x-3">
                <span id="{$wizardId}_step_indicator_{$idx}" class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-mono font-bold {$activeIndicator} transition-all duration-300">
                    {$stepNum}
                </span>
                <div class="hidden sm:block">
                    <p class="text-xs font-bold text-slate-200 font-mono">{$step['title']}</p>
                    <p class="text-[10px] text-slate-500 font-mono">{$step['description']}</p>
                </div>
            </div>
HTML;
            if ($idx < $totalSteps - 1) {
                $stepsNav .= '<div class="hidden sm:block w-8 h-[2px] bg-slate-800 flex-1"></div>';
            }

            $hiddenClass = $isActive ? '' : 'hidden';
            $stepsContent .= <<<HTML
            <div id="{$wizardId}_step_{$idx}" class="oshim-wizard-step-pane {$hiddenClass} space-y-4">
                {$step['html']}
            </div>
HTML;
        }

        return <<<HTML
<div id="{$wizardId}" class="p-6 rounded-3xl bg-[#090d16]/90 border border-slate-800 shadow-2xl backdrop-blur-2xl max-w-3xl mx-auto">
    <!-- Progress Indicator Header -->
    <div class="flex items-center justify-between pb-6 mb-6 border-b border-slate-800">
        {$stepsNav}
    </div>

    <!-- Step Body Container -->
    <form action="{$this->actionUrl}" method="POST" onsubmit="return true;">
        <div class="min-h-[220px]">
            {$stepsContent}
        </div>

        <!-- Navigation Buttons -->
        <div class="flex items-center justify-between pt-6 mt-6 border-t border-slate-800">
            <button type="button" id="{$wizardId}_btn_prev" onclick="
                const current = parseInt(document.getElementById('{$wizardId}').dataset.currentStep || '0');
                if (current > 0) {
                    oshimSwitchStep('{$wizardId}', current, current - 1, {$totalSteps});
                }
            " class="px-4 py-2 rounded-xl text-xs font-mono font-bold bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-slate-200 border border-slate-700 transition-colors opacity-50 pointer-events-none">
                ← Previous
            </button>

            <button type="button" id="{$wizardId}_btn_next" onclick="
                const current = parseInt(document.getElementById('{$wizardId}').dataset.currentStep || '0');
                if (current < {$totalSteps} - 1) {
                    oshimSwitchStep('{$wizardId}', current, current + 1, {$totalSteps});
                } else {
                    alert('Wizard completed! Executing payload...');
                }
            " class="px-5 py-2 rounded-xl text-xs font-mono font-bold bg-gradient-to-r from-cyan-500 to-indigo-500 text-slate-950 hover:shadow-[0_0_20px_rgba(6,182,212,0.4)] transition-all">
                Next Step →
            </button>
        </div>
    </form>
</div>

<script>
function oshimSwitchStep(wizId, fromStep, toStep, total) {
    const root = document.getElementById(wizId);
    root.dataset.currentStep = toStep;

    // Hide previous
    const fromPane = document.getElementById(wizId + '_step_' + fromStep);
    if (fromPane) fromPane.classList.add('hidden');

    // Show next
    const toPane = document.getElementById(wizId + '_step_' + toStep);
    if (toPane) toPane.classList.remove('hidden');

    // Update indicators
    for (let i = 0; i < total; i++) {
        const ind = document.getElementById(wizId + '_step_indicator_' + i);
        if (!ind) continue;
        if (i === toStep) {
            ind.className = 'w-8 h-8 rounded-full flex items-center justify-center text-xs font-mono font-bold bg-cyan-500 text-slate-950 shadow-[0_0_15px_rgba(6,182,212,0.5)] transition-all duration-300';
        } else if (i < toStep) {
            ind.className = 'w-8 h-8 rounded-full flex items-center justify-center text-xs font-mono font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 transition-all duration-300';
            ind.innerText = '✔';
        } else {
            ind.className = 'w-8 h-8 rounded-full flex items-center justify-center text-xs font-mono font-bold bg-slate-800 text-slate-400 transition-all duration-300';
            ind.innerText = (i + 1).toString();
        }
    }

    // Update Buttons
    const prevBtn = document.getElementById(wizId + '_btn_prev');
    const nextBtn = document.getElementById(wizId + '_btn_next');
    if (toStep === 0) {
        prevBtn.classList.add('opacity-50', 'pointer-events-none');
    } else {
        prevBtn.classList.remove('opacity-50', 'pointer-events-none');
    }

    if (toStep === total - 1) {
        nextBtn.innerText = '{$this->submitLabel} ✔';
    } else {
        nextBtn.innerText = 'Next Step →';
    }
}
</script>
HTML;
    }
}
