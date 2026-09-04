<?php
declare(strict_types=1);

namespace App\Views;

use Oshim\Ui\Theme\CyberThemeEngine;
use Oshim\Ui\LiveDom\LiveDomRuntime;

class Layout
{
    public static function render(string $title, string $content, bool $withLiveDom = false): string
    {
        $theme = new CyberThemeEngine();
        $head = $theme->getInlineStyles();
        
        $liveDomScript = '';
        if ($withLiveDom) {
            $liveDomScript = "<script>" . LiveDomRuntime::getCoreScript() . "</script>";
        }

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en" class="dark">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{$title} - Oshim AI Workspace</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <script>
                tailwind.config = {
                    darkMode: 'class',
                    theme: {
                        extend: {
                            colors: {
                                cyber: {
                                    900: '#0f172a',
                                    800: '#1e293b',
                                    accent: '#3b82f6',
                                }
                            }
                        }
                    }
                }
            </script>
            <style>
                {$head}
                body { background-color: #0f172a; color: #f8fafc; font-family: system-ui, sans-serif; }
            </style>
        </head>
        <body class="antialiased h-screen flex flex-col">
            <nav class="border-b border-cyber-800 p-4 flex justify-between items-center bg-cyber-900/50 backdrop-blur-md sticky top-0 z-50">
                <div class="text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-purple-500">
                    Oshim AI Workspace
                </div>
                <div class="flex gap-4">
                    <a href="/workspace" class="hover:text-blue-400 transition">Home</a>
                    <?php if (isset(\$_SESSION['user_id'])): ?>
                        <a href="/workspace/dashboard" class="hover:text-blue-400 transition">Dashboard</a>
                        <form action="/workspace/logout" method="POST" class="inline">
                            <button type="submit" class="text-red-400 hover:text-red-300 transition">Logout</button>
                        </form>
                    <?php else: ?>
                        <a href="/workspace/login" class="hover:text-blue-400 transition">Login</a>
                        <a href="/workspace/register" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-1 rounded-md transition">Register</a>
                    <?php endif; ?>
                </div>
            </nav>
            <main class="flex-1 overflow-auto">
                {$content}
            </main>
            {$liveDomScript}
        </body>
        </html>
        HTML;
    }
}
