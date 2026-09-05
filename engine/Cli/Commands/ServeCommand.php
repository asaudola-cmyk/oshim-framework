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
                    <script src="https://cdn.tailwindcss.com"></script>
                    <script src="/oshim-livedom.js"></script>
                    <style>
                        body { background-color: #111827; color: #fff; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
                    </style>
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
