<?php
declare(strict_types=1);

namespace Oshim\Mobile;

class MobileAppEngine
{
    public static function getManifestConfig(): array
    {
        return [
            'name' => 'OSHIM Sovereign Mobile',
            'short_name' => 'OSHIM',
            'description' => 'Sovereign Cloud, VPS & Hosting Mobile App',
            'start_url' => '/',
            'display' => 'standalone',
            'background_color' => '#070a13',
            'theme_color' => '#7f00ff',
            'orientation' => 'portrait-primary',
            'icons' => [
                [
                    'src' => '/assets/icons/icon-192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any maskable'
                ],
                [
                    'src' => '/assets/icons/icon-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any maskable'
                ]
            ],
            'shortcuts' => [
                [
                    'name' => 'Cloud VPS',
                    'url' => '/vps',
                    'description' => 'Launch instant VPS'
                ],
                [
                    'name' => 'Console',
                    'url' => '/client/dashboard',
                    'description' => 'Client Console'
                ]
            ]
        ];
    }

    public static function renderMobileBottomNav(string $activeRoute = '/'): string
    {
        $tabs = [
            ['route' => '/', 'icon' => '🏠', 'label' => 'হোম'],
            ['route' => '/vps', 'icon' => '⚡', 'label' => 'VPS'],
            ['route' => '/domains', 'icon' => '🔍', 'label' => 'ডোমেইন'],
            ['route' => '/cart', 'icon' => '🛒', 'label' => 'কার্ট'],
            ['route' => '/client/dashboard', 'icon' => '👤', 'label' => 'কনসোল'],
        ];

        $html = '<nav class="oshim-mobile-bottom-nav" style="position: fixed; bottom: 0; left: 0; right: 0; height: 65px; background: rgba(10, 14, 26, 0.95); backdrop-filter: blur(20px); border-top: 1px solid rgba(255,255,255,0.08); display: flex; justify-content: space-around; align-items: center; z-index: 9999;">';
        
        foreach ($tabs as $tab) {
            $isActive = ($activeRoute === $tab['route']);
            $color = $isActive ? '#00f2fe' : '#94a3b8';
            $glow = $isActive ? 'text-shadow: 0 0 10px rgba(0, 242, 254, 0.6);' : '';
            $html .= sprintf(
                '<a href="%s" style="text-decoration: none; display: flex; flex-direction: column; align-items: center; gap: 4px; color: %s; %s">
                    <span style="font-size: 1.3rem;">%s</span>
                    <span style="font-size: 0.72rem; font-weight: 600;">%s</span>
                </a>',
                htmlspecialchars($tab['route']),
                $color,
                $glow,
                $tab['icon'],
                htmlspecialchars($tab['label'])
            );
        }

        $html .= '</nav>';
        return $html;
    }

    public static function getServiceWorkerScript(): string
    {
        return <<<JS
const CACHE_NAME = 'oshim-mobile-v1';
const STATIC_ASSETS = [
    '/',
    '/assets/oshim.css',
    '/assets/oshim-client.js',
    '/assets/oshim-terminal.js'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => cache.addAll(STATIC_ASSETS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => Promise.all(
            keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))
        ))
    );
    self.clients.claim();
});

self.addEventListener('fetch', event => {
    event.respondWith(
        fetch(event.request).catch(() => caches.match(event.request))
    );
});
JS;
    }
}
