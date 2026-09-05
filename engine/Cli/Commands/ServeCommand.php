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
            $basePath = defined('OSHIM_BASE_PATH') ? OSHIM_BASE_PATH : getcwd();
            
            // Intelligent Application HTTP & Static Asset Handler
            $reactor->setHttpHandler(function (string $method, string $uri) use ($basePath, $host, $port) {
                $parsedUri = parse_url($uri, PHP_URL_PATH) ?? '/';

                // 1. OSHIM LiveDOM Client Runtime
                if ($parsedUri === '/oshim-livedom.js') {
                    $jsPath = $basePath . '/public/oshim-livedom.js';
                    if (!file_exists($jsPath)) {
                        $jsPath = dirname(__DIR__, 3) . '/public/oshim-livedom.js';
                    }
                    if (file_exists($jsPath)) {
                        return [
                            'status' => 200,
                            'headers' => ['Content-Type' => 'application/javascript; charset=UTF-8'],
                            'body' => file_get_contents($jsPath)
                        ];
                    }
                }

                // 2. Static File Resolution (public/ assets, images, styles)
                $publicFile = realpath($basePath . '/public' . $parsedUri);
                $publicDir = realpath($basePath . '/public');
                if ($publicFile && $publicDir && str_starts_with($publicFile, $publicDir) && is_file($publicFile)) {
                    $ext = strtolower(pathinfo($publicFile, PATHINFO_EXTENSION));
                    $mime = match ($ext) {
                        'css' => 'text/css; charset=UTF-8',
                        'js' => 'application/javascript; charset=UTF-8',
                        'json' => 'application/json; charset=UTF-8',
                        'png' => 'image/png',
                        'jpg', 'jpeg' => 'image/jpeg',
                        'svg' => 'image/svg+xml; charset=UTF-8',
                        'webp' => 'image/webp',
                        'ico' => 'image/x-icon',
                        'woff2' => 'font/woff2',
                        'woff' => 'font/woff',
                        'ttf' => 'font/ttf',
                        default => 'application/octet-stream',
                    };
                    return [
                        'status' => 200,
                        'headers' => ['Content-Type' => $mime],
                        'body' => file_get_contents($publicFile)
                    ];
                }

                // 3. Application Routing Dispatch (Dynamic PHP application kernel)
                $_SERVER['REQUEST_METHOD'] = $method;
                $_SERVER['REQUEST_URI'] = $uri;
                $_SERVER['SERVER_NAME'] = $host;
                $_SERVER['SERVER_PORT'] = (string)$port;

                $routesFile = $basePath . '/routes/web.php';
                if (file_exists($routesFile)) {
                    require_once $routesFile;
                }

                $router = \Oshim\Http\Router\RouteFacade::getRouter();
                $request = \Oshim\Http\Request::capture();
                
                try {
                    return $router->dispatch($request);
                } catch (\Throwable $e) {
                    return [
                        'status' => 500,
                        'headers' => ['Content-Type' => 'text/html; charset=UTF-8'],
                        'body' => "<h1>500 Server Error</h1><pre>" . htmlspecialchars($e->getMessage()) . "</pre>"
                    ];
                }
            });

            $reactor->boot();
            
        } catch (\Throwable $e) {
            $output->writeln("<red>Failed to start server: " . $e->getMessage() . "</red>");
            return 1;
        }

        return 0;
    }
}
