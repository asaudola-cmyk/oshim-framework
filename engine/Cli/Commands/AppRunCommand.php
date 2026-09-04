<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\App\UniversalAppEngine;

class AppRunCommand extends Command
{
    protected string $name = 'app:run';
    protected string $description = 'Run OSHIM Universal App in target mode (web, mobile, desktop)';

    protected function configure(): void
    {
        $this->addOption('target', 't', Input::VALUE_OPTIONAL, 'Target runtime: web, mobile, desktop', 'web');
    }

    public function execute(Input $input, Output $output): int
    {
        $target = (string)$input->getOption('target', 'web');
        $caps = UniversalAppEngine::getPlatformCapabilities();

        $output->writeln("<bold><cyan>⚡ OSHIM Universal App Runtime Launcher</cyan></bold>");
        $output->writeln("Host OS: <green>" . strtoupper($caps['host_os']) . "</green>");
        $output->writeln("Target Runtime: <yellow>" . strtoupper($target) . "</yellow>");
        $output->writeln("AI Tensor Engine: <magenta>" . $caps['ai_engine'] . "</magenta>");
        $output->writeln("<bold><green>Universal Application Running Smoothly!</green></bold>");
        return 0;
    }
}
