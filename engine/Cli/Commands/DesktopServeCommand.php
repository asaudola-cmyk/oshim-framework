<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Desktop\DesktopAppEngine;

class DesktopServeCommand extends Command
{
    protected string $name = 'desktop:serve';
    protected string $description = 'Launch OSHIM Native Desktop Application Window & System Tray';

    protected function configure(): void
    {
        $this->addOption('url', 'u', Input::VALUE_OPTIONAL, 'Target URL to launch in desktop window', 'http://127.0.0.1:8000/');
    }

    public function execute(Input $input, Output $output): int
    {
        $url = (string)$input->getOption('url', 'http://127.0.0.1:8000/');
        $launch = DesktopAppEngine::launchStandaloneWindow($url);

        $output->writeln("<bold><cyan>🖥️ OSHIM Native Desktop Runtime Launcher</cyan></bold>");
        $output->writeln("Window Title: <green>" . $launch['window_title'] . "</green>");
        $output->writeln("Resolution: <yellow>" . $launch['resolution'] . "</yellow>");
        $output->writeln("System Tray: <green>ACTIVE</green>");
        $output->writeln("Target: <cyan>" . $launch['target_url'] . "</cyan>");
        $output->writeln("<bold><green>OSHIM Desktop Window Initialized Successfully.</green></bold>");
        return 0;
    }
}
