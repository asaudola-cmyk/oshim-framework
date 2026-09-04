<?php
declare(strict_types=1);

namespace Oshim\Ui\Widgets;

use Oshim\Ui\Dsl\Element;

/**
 * Collapsible Sovereign Navigation Sidebar Widget.
 */
class SidebarWidget extends Element
{
    private string $brandTitle;
    private array $items = [];
    private string $activeKey;

    public function __construct(string $brandTitle = 'OSHIM Cloud', string $activeKey = 'dashboard')
    {
        parent::__construct('aside');
        $this->brandTitle = $brandTitle;
        $this->activeKey = $activeKey;
    }

    public static function sidebar(string $brandTitle = 'OSHIM Cloud', string $activeKey = 'dashboard'): self
    {
        return new self($brandTitle, $activeKey);
    }

    public function addItem(string $key, string $label, string $url, string $icon = '📁', ?string $badge = null): self
    {
        $this->items[] = [
            'key' => $key,
            'label' => $label,
            'url' => $url,
            'icon' => $icon,
            'badge' => $badge,
        ];
        return $this;
    }

    public function render(): string
    {
        $linksHtml = '';
        foreach ($this->items as $item) {
            $isActive = $item['key'] === $this->activeKey;
            $bg = $isActive ? 'background: rgba(0,242,254,0.12); color: #00f2fe; border-left: 3px solid #00f2fe;' : 'color: #94a3b8; border-left: 3px solid transparent;';
            $badgeHtml = $item['badge'] !== null ? "<span style=\"font-size: 0.7rem; padding: 2px 6px; border-radius: 9999px; background: rgba(0,242,254,0.2); color: #00f2fe;\">" . htmlspecialchars($item['badge']) . "</span>" : '';
            $urlEsc = htmlspecialchars($item['url']);
            $iconEsc = htmlspecialchars($item['icon']);
            $labelEsc = htmlspecialchars($item['label']);
            $isActiveJs = $isActive ? 'true' : 'false';

            $linksHtml .= <<<HTML
<a href="{$urlEsc}" style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1.25rem; font-size: 0.9rem; font-weight: 500; text-decoration: none; transition: all 0.15s ease; {$bg}" onmouseover="if(!{$isActiveJs})this.style.background='rgba(255,255,255,0.04)'" onmouseout="if(!{$isActiveJs})this.style.background='transparent'">
    <div style="display: flex; align-items: center; gap: 10px;">
        <span>{$iconEsc}</span>
        <span>{$labelEsc}</span>
    </div>
    {$badgeHtml}
</a>
HTML;
        }

        return <<<HTML
<aside style="width: 260px; min-height: 100vh; background: rgba(10,15,30,0.95); border-right: 1px solid rgba(255,255,255,0.08); display: flex; flex-direction: column;">
    <div style="padding: 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; gap: 10px;">
        <span style="font-size: 1.5rem;">👑</span>
        <h2 style="font-size: 1.15rem; font-weight: 700; color: #f8fafc; margin: 0;">{$this->brandTitle}</h2>
    </div>
    <nav style="flex: 1; padding: 1rem 0; display: flex; flex-direction: column; gap: 2px;">
        {$linksHtml}
    </nav>
</aside>
HTML;
    }
}
