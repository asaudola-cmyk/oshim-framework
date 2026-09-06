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

        $basePath = defined('OSHIM_BASE_PATH') ? OSHIM_BASE_PATH : dirname(__DIR__, 3);
        $routesFile = $basePath . '/routes/web.php';

        $container = \Oshim\Container\Container::getInstance();
        $router = null;

        if (file_exists($routesFile)) {
            $router = new \Oshim\Http\Router\Router($container);
            \Oshim\Http\Router\RouteFacade::setRouter($router);
            $container->instance('router', $router);
            $container->instance(\Oshim\Http\Router\Router::class, $router);

            (function (\Oshim\Http\Router\Router $router) use ($routesFile) {
                require_once $routesFile;
            })($router);
        }

        try {
            $reactor = new UniversalReactor($host, $port);
            
            // Core HTTP Handler mapping
            $reactor->setHttpHandler(function (string $method, string $uri, string $rawRequest = '') use ($router, $basePath) {
                // Serve the LiveDOM Client Engine JS
                if ($uri === '/oshim-livedom.js') {
                    $jsPath = dirname(__DIR__, 3) . '/public/oshim-livedom.js';
                    if (file_exists($jsPath)) {
                        return file_get_contents($jsPath);
                    }
                }

                // If application routes exist, dispatch through the router
                if ($router !== null) {
                    // Split headers and body from raw HTTP request
                    $parts = explode("\r\n\r\n", $rawRequest, 2);
                    $headerLines = explode("\r\n", $parts[0] ?? '');
                    $rawBody = $parts[1] ?? '';

                    $headers = [];
                    for ($i = 1; $i < count($headerLines); $i++) {
                        $line = $headerLines[$i];
                        if (strpos($line, ':') !== false) {
                            [$k, $v] = explode(':', $line, 2);
                            $headers[trim($k)] = trim($v);
                        }
                    }

                    // Parse query parameters
                    $parsed = parse_url($uri);
                    $query = [];
                    if (!empty($parsed['query'])) {
                        parse_str($parsed['query'], $query);
                    }

                    // Parse POST or JSON payload
                    $post = [];
                    $contentType = $headers['Content-Type'] ?? $headers['content-type'] ?? '';
                    if (strpos($contentType, 'application/json') !== false) {
                        $post = json_decode($rawBody, true) ?: [];
                    } elseif (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
                        parse_str($rawBody, $post);
                    }

                    // Parse Cookies
                    $cookies = [];
                    if (!empty($headers['Cookie']) || !empty($headers['cookie'])) {
                        $cookieHeader = $headers['Cookie'] ?? $headers['cookie'];
                        foreach (explode(';', $cookieHeader) as $cookieStr) {
                            if (strpos($cookieStr, '=') !== false) {
                                [$ck, $cv] = explode('=', trim($cookieStr), 2);
                                $cookies[$ck] = urldecode($cv);
                            }
                        }
                    }

                    $req = new \Oshim\Http\Request($method, $uri, $query, $post, $headers, $cookies, [], [], $rawBody);
                    return $router->dispatch($req);
                }
                
                // Initialize the Demo Component for bare framework installations
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
