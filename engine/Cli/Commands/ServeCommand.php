<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Http\Server\UniversalReactor;
use Oshim\Ui\LiveDom\DemoComponent;

class ServeCommand extends Command
{
    protected string $name = 'serve';
    protected string $description = 'Start the OSHIM Universal Fiber Reactor (Zero-Lag Server)';

    protected function configure(): void
    {
        $this->addOption('host', 'H', Input::VALUE_OPTIONAL, 'The host address to serve the application on', '127.0.0.1')
             ->addOption('port', 'p', Input::VALUE_OPTIONAL, 'The port to serve the application on', '8000');
    }

    public function execute(Input $input, Output $output): int
    {
        $host = (string)$input->getOption('host', '127.0.0.1');
        $port = (int)$input->getOption('port', '8000');

        $output->writeln("<bold><cyan>🚀 Booting OSHIM Universal Fiber Reactor</cyan></bold>");
        $output->writeln("Server running at: <green>http://{$host}:{$port}</green>");
        $output->writeln("<yellow>Note: HTTP and WebSockets are multiplexed on this single port.</yellow>");
        $output->writeln("Press Ctrl+C to stop the server.\n");

        try {
            $reactor = new UniversalReactor($host, $port);
            
            // Core HTTP Handler mapping
            $reactor->setHttpHandler(function (string $method, string $uri) {
                // Serve the LiveDOM Client Engine JS
                if ($uri === '/oshim-livedom.js') {
                    $jsPath = dirname(__DIR__, 3) . '/public/oshim-livedom.js';
                    if (file_exists($jsPath)) {
                        return file_get_contents($jsPath);
                    }
                }
                
                // Initialize the Demo Component for the Root Route
                $component = new DemoComponent('root_demo');
                $componentHtml = $component->compile();
                
                // Return the Full HTML Shell with Tailwind CSS
                return <<<HTML
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>OSHIM Sovereign Engine</title>
                    <!-- Sovereign Offline CSS Fallback (Zero external CDN dependency required) -->
                    <style>
                        *, ::before, ::after { box-sizing: border-box; }
                        body { background-color: #0f172a; color: #f8fafc; font-family: ui-sans-serif, system-ui, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
                        .bg-gray-900 { background-color: #0f172a; }
                        .text-white { color: #ffffff; }
                        .p-6 { padding: 1.5rem; }
                        .rounded-lg { border-radius: 0.75rem; }
                        .shadow-xl { box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 8px 10px -6px rgba(0, 0, 0, 0.5); }
                        .text-2xl { font-size: 1.5rem; line-height: 2rem; }
                        .font-bold { font-weight: 700; }
                        .mb-4 { margin-bottom: 1rem; }
                        .flex { display: flex; }
                        .items-center { align-items: center; }
                        .space-x-4 > * + * { margin-left: 1rem; }
                        .bg-blue-600 { background-color: #2563eb; }
                        .bg-blue-600:hover { background-color: #3b82f6; }
                        .px-4 { padding-left: 1rem; padding-right: 1rem; }
                        .py-2 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
                        .rounded { border-radius: 0.375rem; }
                        .transition { transition-property: all; transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1); transition-duration: 150ms; }
                        button { cursor: pointer; border: none; font-weight: 600; }
                    </style>
                    <script src="https://cdn.tailwindcss.com"></script>
                    <script src="/oshim-livedom.js"></script>
                </head>
                <body>
                    {$componentHtml}
                </body>
                </html>
                HTML;
            });

            $reactor->boot();
            
        } catch (\Throwable $e) {
            $output->writeln("<red>Failed to start server: " . $e->getMessage() . "</red>");
            return 1;
        }

        return 0;
    }
}
