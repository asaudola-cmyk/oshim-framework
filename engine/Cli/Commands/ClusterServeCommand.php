<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Http\Server\ClusterReactor;
use Oshim\Ui\LiveDom\DemoComponent;

/**
 * 👑 Sovereign OSHIM Cluster Serve Command
 * 
 * Boots the multi-core ClusterReactor across all available CPU cores.
 */
class ClusterServeCommand extends Command
{
    protected string $name = 'cluster:serve';
    protected string $description = 'Start the OSHIM Multi-Core Cluster Reactor (Kernel SO_REUSEPORT)';

    protected function configure(): void
    {
        $this->addOption('host', 'H', Input::VALUE_OPTIONAL, 'The host address to serve on', '0.0.0.0')
             ->addOption('port', 'p', Input::VALUE_OPTIONAL, 'The port to serve on', '8000')
             ->addOption('workers', 'w', Input::VALUE_OPTIONAL, 'Number of worker processes (default: all CPU cores)', null);
    }

    public function execute(Input $input, Output $output): int
    {
        $host = (string)$input->getOption('host', '0.0.0.0');
        $port = (int)$input->getOption('port', '8000');
        $workersOption = $input->getOption('workers', null);
        $workers = $workersOption !== null ? (int)$workersOption : null;

        $output->writeln("<bold><cyan>⚡ OSHIM Multi-Core Cluster Reactor</cyan></bold>");
        $output->writeln("Binding address: <green>http://{$host}:{$port}</green>");
        $output->writeln("Press Ctrl+C to terminate the cluster.\n");

        try {
            $cluster = new ClusterReactor($host, $port, $workers);

            // Re-use standard Sovereign Demo Handler
            $cluster->setHttpHandler(function (string $method, string $uri) {
                if ($uri === '/oshim-livedom.js') {
                    $jsPath = (defined('OSHIM_BASE_PATH') ? OSHIM_BASE_PATH : dirname(__DIR__, 3)) . '/public/oshim-livedom.js';
                    if (file_exists($jsPath)) {
                        return file_get_contents($jsPath);
                    }
                }

                $component = new DemoComponent('root_demo');
                $componentHtml = $component->compile();

                return <<<HTML
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>OSHIM Sovereign Multi-Core Engine</title>
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
                    <script src="/oshim-livedom.js"></script>
                </head>
                <body>
                    {$componentHtml}
                </body>
                </html>
                HTML;
            });

            $cluster->boot();

        } catch (\Throwable $e) {
            $output->writeln("<red>Failed to start cluster: " . $e->getMessage() . "</red>");
            return 1;
        }

        return 0;
    }
}
