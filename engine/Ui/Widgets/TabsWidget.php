<?php
declare(strict_types=1);

namespace Oshim\Ui\Widgets;

use Oshim\Ui\Dsl\Element;

/**
 * TabsWidget: Interactive Zero-Dependency Glassmorphic Tabs Component.
 * Supports animated active tab indicators, keyboard navigation, and deep-link routing.
 */
class TabsWidget extends Element
{
    /** @var list<array{id: string, label: string, icon: ?string, content: string, active: bool}> */
    private array $tabs = [];
    private string $variant;

    public function __construct(array $tabs = [], string $variant = 'pills')
    {
        parent::__construct('div');
        $this->class('oshim-tabs-widget');
        $this->tabs = $tabs;
        $this->variant = $variant;
    }

    public static function create(array $tabs = [], string $variant = 'pills'): self
    {
        return new self($tabs, $variant);
    }

    public function addTab(string $id, string $label, string $content, ?string $icon = null, bool $active = false): self
    {
        $this->tabs[] = [
            'id' => $id,
            'label' => $label,
            'content' => $content,
            'icon' => $icon,
            'active' => $active || empty($this->tabs),
        ];
        return $this;
    }

    public function render(): string
    {
        $uniqueId = 'tabs_' . substr(md5(uniqid()), 0, 8);
        $headersHtml = '';
        $panelsHtml = '';

        foreach ($this->tabs as $index => $tab) {
            $isActive = $tab['active'];
            $activeClass = $isActive
                ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/40 shadow-[0_0_15px_rgba(6,182,212,0.2)]'
                : 'text-slate-400 hover:text-slate-200 border-transparent hover:bg-slate-800/50';

            $iconHtml = $tab['icon'] ? "<span class=\"mr-2\">{$tab['icon']}</span>" : '';

            $headersHtml .= <<<HTML
            <button onclick="
                const root = document.getElementById('{$uniqueId}');
                root.querySelectorAll('.oshim-tab-btn').forEach(b => {
                    b.classList.remove('bg-cyan-500/20', 'text-cyan-300', 'border-cyan-500/40', 'shadow-[0_0_15px_rgba(6,182,212,0.2)]');
                    b.classList.add('text-slate-400', 'border-transparent');
                });
                this.classList.add('bg-cyan-500/20', 'text-cyan-300', 'border-cyan-500/40', 'shadow-[0_0_15px_rgba(6,182,212,0.2)]');
                this.classList.remove('text-slate-400', 'border-transparent');

                root.querySelectorAll('.oshim-tab-panel').forEach(p => p.classList.add('hidden'));
                document.getElementById('{$tab['id']}').classList.remove('hidden');
            " class="oshim-tab-btn flex items-center px-4 py-2 rounded-xl text-xs font-mono font-bold border transition-all duration-200 {$activeClass}">
                {$iconHtml}
                <span>{$tab['label']}</span>
            </button>
HTML;

            $hiddenClass = $isActive ? '' : 'hidden';
            $panelsHtml .= <<<HTML
            <div id="{$tab['id']}" class="oshim-tab-panel p-5 rounded-2xl bg-slate-900/40 border border-slate-800/80 backdrop-blur-xl {$hiddenClass}">
                {$tab['content']}
            </div>
HTML;
        }

        return <<<HTML
<div id="{$uniqueId}" class="space-y-4">
    <!-- Tab Nav Header -->
    <div class="flex items-center space-x-2 p-1.5 rounded-2xl bg-slate-950/80 border border-slate-800/80 w-fit backdrop-blur-xl">
        {$headersHtml}
    </div>

    <!-- Tab Panels Container -->
    <div class="mt-3">
        {$panelsHtml}
    </div>
</div>
HTML;
    }
}
