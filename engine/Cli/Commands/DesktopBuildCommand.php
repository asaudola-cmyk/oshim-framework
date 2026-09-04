<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Desktop\DesktopPackager;

/**
 * CLI Command: oshim build:desktop [--dist=...]
 * Sovereign Electron-Killer Native Desktop App Compiler and Packager.
 */
class DesktopBuildCommand extends Command
{
    protected string $name = 'build:desktop';
    protected string $description = 'Package OSHIM application into zero-dependency native desktop bundles (Linux, Windows, macOS)';

    protected function configure(): void
    {
        $this->addOption('dist', 'd', Input::VALUE_OPTIONAL, 'Target distribution output directory', 'dist/desktop')
            ->addOption('name', null, Input::VALUE_OPTIONAL, 'Desktop application name')
            ->addOption('width', null, Input::VALUE_OPTIONAL, 'Default window width', 1280)
            ->addOption('height', null, Input::VALUE_OPTIONAL, 'Default window height', 840);
    }

    public function execute(Input $input, Output $output): int
    {
        $output->writeln("<bold><cyan>⚡ OSHIM Sovereign Electron-Killer Desktop Packager</cyan></bold>");

        $appRoot = getcwd() ?: dirname(__DIR__, 3);
        $dist = (string)$input->getOption('dist', 'dist/desktop');

        $config = [];
        if ($input->getOption('name')) {
            $config['app_name'] = (string)$input->getOption('name');
        }
        $config['window']['width'] = (int)$input->getOption('width', 1280);
        $config['window']['height'] = (int)$input->getOption('height', 840);

        $packager = new DesktopPackager($appRoot, $dist, $config);

        $output->writeln("Compiling native desktop bundle into <yellow>{$dist}</yellow>...");
        $result = $packager->package();

        $output->writeln("<green>✔ Desktop Bundle Generated Successfully!</green>");
        $output->writeln("• Linux Launcher:   <cyan>{$dist}/oshim-desktop</cyan>");
        $output->writeln("• Linux Desktop:    <cyan>{$dist}/oshim.desktop</cyan>");
        $output->writeln("• Windows Runner:   <cyan>{$dist}/oshim-desktop.bat</cyan>");
        $output->writeln("• macOS Bundle:     <cyan>{$dist}/OSHIM.app</cyan>");
        $output->writeln("• Runtime Manifest: <cyan>{$dist}/app-manifest.json</cyan>");
        $output->writeln("\n<dim>Zero Electron overhead. 100% Native OS WebViews.</dim>");

        return 0;
    }
}
