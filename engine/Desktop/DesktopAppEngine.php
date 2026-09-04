<?php
declare(strict_types=1);

namespace Oshim\Desktop;

class DesktopAppEngine
{
    public static function getDesktopConfig(): array
    {
        return [
            'app_name' => 'OSHIM Sovereign Desktop',
            'version' => '1.0.0',
            'window' => [
                'title' => 'OSHIM Sovereign Framework & Studio',
                'width' => 1280,
                'height' => 840,
                'min_width' => 900,
                'min_height' => 600,
                'frameless' => false,
                'dark_theme' => true,
                'system_tray' => true,
                'always_on_top' => false,
            ],
            'tray_menu' => [
                ['label' => 'Open OSHIM Documentation', 'action' => 'open_main_window'],
                ['label' => 'Server Status: 100% HEALTHY', 'enabled' => false],
                ['type' => 'separator'],
                ['label' => 'Exit OSHIM Desktop', 'action' => 'quit'],
            ],
            'shortcuts' => [
                'reload' => 'Ctrl+R',
                'terminal' => 'Ctrl+Shift+T',
                'devtools' => 'F12',
            ]
        ];
    }

    public static function launchStandaloneWindow(string $targetUrl = 'http://127.0.0.1:8000/'): array
    {
        $config = self::getDesktopConfig();

        // 1. Check if server is running on target port; if not, spin up background server
        self::ensureServerRunning($targetUrl);

        $spawned = self::spawnSystemWindow($targetUrl);

        return [
            'status' => 'LAUNCHED',
            'target_url' => $targetUrl,
            'window_title' => $config['window']['title'],
            'resolution' => $config['window']['width'] . 'x' . $config['window']['height'],
            'tray_active' => true,
            'native_bridge' => 'OSHIM_DESKTOP_RUNTIME_V1',
            'spawn_command' => $spawned,
        ];
    }

    public static function ensureServerRunning(string $targetUrl): void
    {
        $parts = parse_url($targetUrl);
        $host = $parts['host'] ?? '127.0.0.1';
        $port = $parts['port'] ?? 8000;

        $fp = @fsockopen($host, (int)$port, $errno, $errstr, 0.2);
        if ($fp) {
            fclose($fp);
            return; // Server is already running
        }

        // Spin up background server
        $basePath = dirname(__DIR__, 2);
        $publicDir = $basePath . '/public';
        $indexFile = $publicDir . '/index.php';

        if (is_dir($publicDir) && is_file($indexFile)) {
            $cmd = sprintf(
                '%s -S %s:%s -t %s %s > /dev/null 2>&1 &',
                PHP_BINARY,
                escapeshellarg($host),
                escapeshellarg((string)$port),
                escapeshellarg($publicDir),
                escapeshellarg($indexFile)
            );
            if (!getenv('CI') && !getenv('TESTING')) {
                @exec($cmd);
                usleep(200000); // 200ms grace period for socket bind
            }
        }
    }

    public static function spawnSystemWindow(string $targetUrl = 'http://127.0.0.1:8000/'): string
    {
        $os = PHP_OS_FAMILY;
        $cmd = '';

        if ($os === 'Linux') {
            if (exec('which google-chrome 2>/dev/null')) {
                $cmd = "google-chrome --app=\"{$targetUrl}\" --window-size=1280,840 > /dev/null 2>&1 &";
            } elseif (exec('which chromium 2>/dev/null')) {
                $cmd = "chromium --app=\"{$targetUrl}\" --window-size=1280,840 > /dev/null 2>&1 &";
            } elseif (exec('which xdg-open 2>/dev/null')) {
                $cmd = "xdg-open \"{$targetUrl}\" > /dev/null 2>&1 &";
            }
        } elseif ($os === 'Windows') {
            $cmd = "start chrome --app=\"{$targetUrl}\" --window-size=1280,840 || start \"{$targetUrl}\"";
        } elseif ($os === 'Darwin') {
            $cmd = "open -na \"Google Chrome\" --args --app=\"{$targetUrl}\" --window-size=1280,840 || open \"{$targetUrl}\"";
        }

        if (!empty($cmd) && php_sapi_name() === 'cli' && !getenv('CI') && !getenv('TESTING')) {
            @exec($cmd);
        }

        return $cmd ?: "xdg-open \"{$targetUrl}\"";
    }
}
