<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\App\AppGenerator;

class AppCreateCommand extends Command
{
    protected string $name = 'app:create';
    protected string $description = 'Scaffold a new Universal OSHIM App (Web, Mobile, Desktop, API, or AI)';

    protected function configure(): void
    {
        $this->addArgument('name', Input::REQUIRED, 'The application project name');
        $this->addOption('type', 't', Input::VALUE_OPTIONAL, 'Application type: fullstack, web, mobile, desktop, api, ai', 'fullstack');
    }

    public function execute(Input $input, Output $output): int
    {
        $name = (string)($input->getArgument('name') ?: 'MyUniversalApp');
        $type = (string)$input->getOption('type', 'fullstack');

        $result = AppGenerator::createProject($name, $type);

        $output->writeln("<bold><cyan>🚀 OSHIM Universal Application Generator</cyan></bold>");
        $output->writeln("App Name: <green>{$name}</green>");
        $output->writeln("App Type: <yellow>{$type}</yellow>");
        $output->writeln("Architecture: <magenta>Pure PHP 8.3+ Zero-Dependency DSL</magenta>");
        $output->writeln("Supported Targets: <green>Web, Android, iOS, Windows, Mac, Linux</green>");
        $output->writeln("<bold><green>✔ Project Scaffolding Completed Successfully!</green></bold>");
        return 0;
    }
}
