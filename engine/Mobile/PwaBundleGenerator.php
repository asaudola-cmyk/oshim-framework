<?php
declare(strict_types=1);

namespace Oshim\Mobile;

class PwaBundleGenerator
{
    public static function build(string $publicDir): array
    {
        if (!is_dir($publicDir)) {
            @mkdir($publicDir, 0755, true);
        }

        // 1. Generate manifest.json
        $manifest = [
            'name' => 'OSHIM Sovereign Cloud App',
            'short_name' => 'OSHIM',
            'start_url' => '/',
            'display' => 'standalone',
            'background_color' => '#070a13',
            'theme_color' => '#00f2fe',
            'orientation' => 'portrait',
            'icons' => [
                [
                    'src' => '/assets/icon-192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png'
                ],
                [
                    'src' => '/assets/icon-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png'
                ]
            ]
        ];
        file_put_contents($publicDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // 2. Generate service-worker.js
        $sw = <<<'JS'
const CACHE_NAME = 'oshim-sovereign-v1';
const ASSETS_TO_CACHE = [
    '/',
    '/vps',
    '/ai',
    '/offline.html',
    '/manifest.json'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(ASSETS_TO_CACHE);
        })
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
            );
        })
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;
    event.respondWith(
        fetch(event.request).catch(() => {
            return caches.match(event.request).then((res) => {
                return res || caches.match('/offline.html');
            });
        })
    );
});
JS;
        file_put_contents($publicDir . '/service-worker.js', $sw);

        // 3. Generate offline.html
        $offlineHtml = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OSHIM Sovereign App — Offline Mode</title>
    <style>
        body { margin: 0; background: #070a13; color: #fff; font-family: system-ui, sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; text-align: center; }
        .card { background: rgba(255,255,255,0.05); padding: 2rem; border-radius: 1rem; border: 1px solid rgba(255,255,255,0.1); max-width: 400px; }
        h1 { color: #00f2fe; margin-bottom: 0.5rem; }
        p { color: #94a3b8; }
        button { background: #00f2fe; color: #000; font-weight: bold; border: none; padding: 0.75rem 1.5rem; border-radius: 0.5rem; cursor: pointer; margin-top: 1rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1>📱 OSHIM Offline Mode</h1>
        <p>You are currently offline. Local sovereign caches and state are preserved.</p>
        <button onclick="window.location.reload()">Retry Connection</button>
    </div>
</body>
</html>
HTML;
        file_put_contents($publicDir . '/offline.html', $offlineHtml);

        return [
            'manifest' => $publicDir . '/manifest.json',
            'service_worker' => $publicDir . '/service-worker.js',
            'offline_page' => $publicDir . '/offline.html',
            'status' => 'GENERATED',
        ];
    }
}
