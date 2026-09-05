<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Http\Server\UniversalReactor;

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
        $output->writeln("<yellow>Note: This server replaces the slow 'php -S' with our Native Fiber Engine.</yellow>");
        $output->writeln("Press Ctrl+C to stop the server.\n");

        try {
            $reactor = new UniversalReactor($host, $port);
            
            // Dummy HTTP Handler to serve the JS file and an example page
            $reactor->setHttpHandler(function (string $method, string $uri) {
                if ($uri === '/oshim-livedom.js') {
                    $jsPath = dirname(__DIR__, 3) . '/public/oshim-livedom.js';
                    if (file_exists($jsPath)) {
                        return file_get_contents($jsPath);
                    }
                }
                
                // Default fallback response
                return <<<HTML
                <!DOCTYPE html>
                <html>
                <head>
                    <title>OSHIM Fiber Reactor</title>
                    <script src="/oshim-livedom.js"></script>
                </head>
                <body class="bg-gray-900 text-white p-8 font-sans">
                    <h1 class="text-3xl font-bold text-cyan-400">OSHIM Zero-Lag Server Active</h1>
                    <p class="mt-4">HTTP and WebSockets are now running on the same port.</p>
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
