<?php
declare(strict_types=1);

namespace Oshim\Ui\Components;

use Oshim\Ui\Component;

class Navbar extends Component
{
    protected ?array $brand = null;
    protected array $breadcrumbs = []; // [['label' => '', 'url' => '']]
    protected bool $showSearch = true;
    protected string $searchPlaceholder = 'Search instances, domains, invoices...';
    protected ?string $searchAction = 'search';
    protected ?array $statusIndicator = null; // ['status' => 'running', 'label' => 'All Systems Operational']
    protected array $notifications = [];      // ['count' => 3, 'items' => []]
    protected ?array $user = null;            // ['name' => '', 'email' => '', 'avatar' => '']
    protected string $class = '';

    public function mount(array $props): void
    {
        $this->brand = $props['brand'] ?? null;
        $this->breadcrumbs = (array)($props['breadcrumbs'] ?? []);
        $this->showSearch = (bool)($props['showSearch'] ?? true);
        $this->searchPlaceholder = (string)($props['searchPlaceholder'] ?? 'Search instances, domains, invoices...');
        $this->searchAction = $props['searchAction'] ?? 'search';
        $this->statusIndicator = $props['statusIndicator'] ?? null;
        $this->notifications = (array)($props['notifications'] ?? ['count' => 0]);
        $this->user = $props['user'] ?? null;
        $this->class = (string)($props['class'] ?? '');
    }

    public function render(): string
    {
        $html = '<header class="oshim-navbar oshim-glass ' . $this->escape($this->class) . '" data-oshim-id="' . $this->escape($this->id) . '">';

        // Left Section: Mobile Toggle & Breadcrumbs
        $html .= '<div class="oshim-navbar__left">';
        $html .= '<button type="button" class="oshim-navbar__menu-btn" oshim:click="toggleMobileSidebar" aria-label="Toggle Navigation"><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg></button>';

        if ($this->hasSlot('breadcrumbs')) {
            $html .= $this->slot('breadcrumbs');
        } elseif (!empty($this->breadcrumbs)) {
            $html .= '<nav class="oshim-breadcrumbs" aria-label="Breadcrumb"><ol class="oshim-breadcrumbs__list">';
            $n = count($this->breadcrumbs);
            foreach ($this->breadcrumbs as $idx => $crumb) {
                $isLast = ($idx === $n - 1);
                $html .= '<li class="oshim-breadcrumbs__item">';
                if (!$isLast && !empty($crumb['url'])) {
                    $html .= '<a href="' . $this->escape($crumb['url']) . '" class="oshim-breadcrumbs__link">' . $this->escape($crumb['label'] ?? '') . '</a>';
                    $html .= '<span class="oshim-breadcrumbs__sep">/</span>';
                } else {
                    $html .= '<span class="oshim-breadcrumbs__current" aria-current="page">' . $this->escape($crumb['label'] ?? '') . '</span>';
                }
                $html .= '</li>';
            }
            $html .= '</ol></nav>';
        }
        $html .= '</div>';

        // Center Section: Global Search
        $html .= '<div class="oshim-navbar__center">';
        if ($this->hasSlot('searchSlot') || $this->hasSlot('center')) {
            $html .= $this->hasSlot('searchSlot') ? $this->slot('searchSlot') : $this->slot('center');
        } elseif ($this->showSearch) {
            $html .= '<div class="oshim-navbar__search">';
            $html .= '<svg class="oshim-navbar__search-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>';
            $html .= '<input type="search" class="oshim-input oshim-navbar__search-input" placeholder="' . $this->escape($this->searchPlaceholder) . '" oshim:input="' . $this->escape((string)$this->searchAction) . '" data-oshim-debounce="300">';
            $html .= '</div>';
        }
        $html .= '</div>';

        // Right Section: Node/System Status, Notifications, User Menu
        $html .= '<div class="oshim-navbar__right">';
        if ($this->statusIndicator !== null) {
            $badge = new StatusBadge([
                'status' => $this->statusIndicator['status'] ?? 'running',
                'label'  => $this->statusIndicator['label'] ?? 'Online',
                'size'   => 'sm',
                'pulse'  => true,
            ]);
            $html .= '<div class="oshim-navbar__status">' . $badge->render() . '</div>';
        }

        // Notification Bell
        $notifCount = (int)($this->notifications['count'] ?? 0);
        $html .= '<div class="oshim-navbar__notif-wrapper">';
        $html .= '<button type="button" class="oshim-btn oshim-btn--ghost oshim-btn--sm oshim-btn--icon" oshim:click="toggleNotifications" aria-label="Notifications">';
        $html .= '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>';
        if ($notifCount > 0) {
            $html .= '<span class="oshim-badge-dot oshim-badge-dot--pulse">' . $notifCount . '</span>';
        }
        $html .= '</button></div>';

        // User Avatar Dropdown
        if ($this->hasSlot('userMenuSlot')) {
            $html .= $this->slot('userMenuSlot');
        } elseif ($this->user !== null) {
            $avatar = $this->user['avatar'] ?? 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="%2300f2fe"><circle cx="12" cy="8" r="4"/><path d="M12 14c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5z"/></svg>';
            $html .= '<div class="oshim-dropdown oshim-navbar__user">';
            $html .= '<button type="button" class="oshim-navbar__user-btn" oshim:click="toggleUserMenu">';
            $html .= '<img class="oshim-avatar oshim-avatar--sm" src="' . $this->escape($avatar) . '" alt="' . $this->escape($this->user['name'] ?? 'User') . '">';
            $html .= '<span class="oshim-navbar__user-name">' . $this->escape($this->user['name'] ?? 'User') . '</span>';
            $html .= '<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M7 10l5 5 5-5z"/></svg>';
            $html .= '</button></div>';
        }

        $html .= '</div></header>';
        return $html;
    }
}
