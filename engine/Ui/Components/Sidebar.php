<?php
declare(strict_types=1);

namespace Oshim\Ui\Components;

use Oshim\Ui\Component;

class Sidebar extends Component
{
    protected array $brand = [];
    protected array $items = []; // array of ['group' => '', 'items' => [['label' => '', 'url' => '', 'route' => '', 'icon' => '', 'badge' => '', 'badgeVariant' => 'cyan']]]
    protected string $activeRoute = '';
    protected ?array $userProfile = null;
    protected string $class = '';

    public function mount(array $props): void
    {
        $this->brand = (array)($props['brand'] ?? ['name' => 'OSHIM CLOUD', 'url' => '/']);
        $this->items = (array)($props['items'] ?? ($props['menuItems'] ?? []));
        $this->activeRoute = (string)($props['activeRoute'] ?? '');
        $this->userProfile = $props['userProfile'] ?? ($props['user'] ?? null);
        $this->class = (string)($props['class'] ?? '');

        if (!isset($this->state['collapsed'])) {
            $this->state['collapsed'] = (bool)($props['collapsed'] ?? false);
        }
    }

    public function toggleCollapse(array $payload = []): void
    {
        $this->state['collapsed'] = empty($this->state['collapsed']);
    }

    public function render(): string
    {
        $isCollapsed = !empty($this->state['collapsed']);
        $classes = ['oshim-sidebar', 'oshim-glass'];
        if ($isCollapsed) {
            $classes[] = 'oshim-sidebar--collapsed';
        }
        if ($this->class !== '') {
            $classes[] = $this->class;
        }

        $html = '<aside class="' . $this->escape(implode(' ', $classes)) . '" data-oshim-id="' . $this->escape($this->id) . '">';

        // Brand
        $html .= '<div class="oshim-sidebar__brand">';
        $html .= '<a href="' . $this->escape($this->brand['url'] ?? '/') . '" class="oshim-sidebar__brand-link">';
        $html .= '<span class="oshim-sidebar__logo">' . ($this->brand['logo'] ?? '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="#00f2fe" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>') . '</span>';
        $html .= '<span class="oshim-sidebar__brand-name">' . $this->escape($this->brand['name'] ?? 'OSHIM') . '</span>';
        $html .= '</a>';
        $html .= '<button type="button" class="oshim-sidebar__toggle" oshim:click="toggleCollapse" aria-label="Toggle Sidebar"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg></button>';
        $html .= '</div>';

        // Navigation Menu
        $html .= '<nav class="oshim-sidebar__nav">';
        foreach ($this->items as $entry) {
            if (!empty($entry['group'])) {
                $html .= '<div class="oshim-sidebar__group-header">' . $this->escape($entry['group']) . '</div>';
                foreach ($entry['items'] ?? [] as $subItem) {
                    $html .= $this->renderItem((array)$subItem);
                }
            } else {
                $html .= $this->renderItem((array)$entry);
            }
        }
        $html .= '</nav>';

        // User Profile Widget Footer
        if ($this->userProfile !== null) {
            $html .= '<div class="oshim-sidebar__footer">';
            $html .= '<div class="oshim-sidebar__user">';
            $avatar = $this->userProfile['avatar'] ?? 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="%2394a3b8"><circle cx="12" cy="8" r="4"/><path d="M12 14c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5z"/></svg>';
            $html .= '<img class="oshim-avatar oshim-avatar--sm" src="' . $this->escape($avatar) . '" alt="' . $this->escape($this->userProfile['name'] ?? 'User') . '">';
            $html .= '<div class="oshim-sidebar__user-info">';
            $html .= '<span class="oshim-sidebar__user-name">' . $this->escape($this->userProfile['name'] ?? 'User') . '</span>';
            $html .= '<span class="oshim-sidebar__user-role">' . $this->escape($this->userProfile['role'] ?? 'Client') . '</span>';
            $html .= '</div></div></div>';
        }

        $html .= '</aside>';
        return $html;
    }

    private function renderItem(array $item): string
    {
        $url = $item['url'] ?? '#';
        $route = $item['route'] ?? $url;
        $isActive = ($this->activeRoute !== '' && ($this->activeRoute === $route || str_starts_with($this->activeRoute, $route . '/'))) || (!empty($item['active']));
        $activeClass = $isActive ? ' oshim-sidebar__item--active' : '';

        $html = '<a href="' . $this->escape($url) . '" class="oshim-sidebar__item' . $activeClass . '">';
        if (!empty($item['icon'])) {
            $html .= '<span class="oshim-sidebar__item-icon">' . $item['icon'] . '</span>';
        }
        $html .= '<span class="oshim-sidebar__item-label">' . $this->escape($item['label'] ?? '') . '</span>';
        if (!empty($item['badge'])) {
            $badgeVariant = $item['badgeVariant'] ?? 'cyan';
            $html .= '<span class="oshim-badge oshim-badge--' . $this->escape($badgeVariant) . ' oshim-sidebar__item-badge">' . $this->escape((string)$item['badge']) . '</span>';
        }
        $html .= '</a>';
        return $html;
    }
}
