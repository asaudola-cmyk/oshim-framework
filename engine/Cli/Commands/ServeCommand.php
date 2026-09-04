<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;

class ServeCommand extends Command
{
    protected string $name = 'serve';
    protected string $description = 'Start the OSHIM local development server';

    protected function configure(): void
    {
        $this->addOption('host', 'H', Input::VALUE_OPTIONAL, 'The host address to serve the application on', '127.0.0.1')
             ->addOption('port', 'p', Input::VALUE_OPTIONAL, 'The port to serve the application on', '8000');
    }

    public function execute(Input $input, Output $output): int
    {
        $host = $input->getOption('host', '127.0.0.1');
        $port = $input->getOption('port', '8000');

        $basePath = defined('OSHIM_BASE_PATH') ? OSHIM_BASE_PATH : dirname(__DIR__, 3);
        $publicDir = $basePath . '/public';

        if (!is_dir($publicDir)) {
            mkdir($publicDir, 0755, true);
        }

        $indexFile = $publicDir . '/index.php';
        if (!is_file($indexFile)) {
            file_put_contents($indexFile, "<?php\nrequire_once dirname(__DIR__) . '/engine/Bootstrap.php';\n\$app = \\Oshim\\Bootstrap::boot();\n");
        }

        $output->writeln("<bold><cyan>OSHIM Sovereign Framework Server</cyan></bold>");
        $output->writeln("Server running at: <green>http://{$host}:{$port}</green>");
        $output->writeln("Document root: <dim>{$publicDir}</dim>");
        $output->writeln("Press Ctrl+C to stop the server.");
        $output->writeln();

        passthru(sprintf(
            '%s -S %s:%s -t %s %s',
            PHP_BINARY,
            escapeshellarg((string)$host),
            escapeshellarg((string)$port),
            escapeshellarg($publicDir),
            escapeshellarg($indexFile)
        ));

        return 0;
    }
}
