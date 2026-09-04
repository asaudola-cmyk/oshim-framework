<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Compiler\StandalonePackager;

/**
 * CLI Command: oshim pack:standalone [source.php] [--output=dist/app.php]
 * Compiles an application into a single self-contained executable file that runs anywhere without OSHIM directory.
 */
class PackStandaloneCommand extends Command
{
    protected string $name = 'pack:standalone';
    protected string $description = 'Compile an app into a single self-contained zero-dependency executable file';

    protected function configure(): void
    {
        $this->addArgument('source', Input::OPTIONAL, 'Path to the application source script', 'app.php')
            ->addOption('output', 'o', Input::VALUE_OPTIONAL, 'Destination path for bundled executable', 'dist/bundle.php');
    }

    public function execute(Input $input, Output $output): int
    {
        $output->writeln("<bold><cyan>📦 OSHIM Sovereign Standalone Single-File Compiler</cyan></bold>");

        $source = (string)($input->getArgument('source') ?? $input->getArgument(0, 'app.php'));
        $destination = (string)$input->getOption('output', 'dist/bundle.php');

        if (!is_file($source)) {
            $output->writeln("<red>Source file not found: {$source}</red>");
            return 1;
        }

        $packager = new StandalonePackager();
        $output->writeln("Analyzing and tree-shaking dependencies for <yellow>{$source}</yellow>...");

        $result = $packager->compile($source, $destination);

        $output->writeln("<green>✔ Standalone Bundle Compiled Successfully!</green>");
        $output->writeln("• Output File:     <cyan>{$result['output_file']}</cyan>");
        $output->writeln("• File Size:       <yellow>" . round($result['size_bytes'] / 1024, 2) . " KB</yellow>");
        $output->writeln("• Classes Bundled: <dim>" . count($result['classes_bundled']) . " classes</dim>");
        $output->writeln("• SHA-256:         <dim>{$result['sha256']}</dim>");
        $output->writeln("\n<green>🚀 You can now copy this single file to any server and run with: php {$destination}</green>");
        $output->writeln("<dim>Zero framework folders required. Zero composer dependencies.</dim>");

        return 0;
    }
}
