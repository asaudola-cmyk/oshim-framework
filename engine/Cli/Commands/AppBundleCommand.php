<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Compiler\UniversalPackager;

class AppBundleCommand extends Command
{
    protected string $name = 'app:bundle';
    protected string $description = 'Bundle & compile OSHIM App for Android, iOS, Windows, Mac, Linux or All platforms';

    protected function configure(): void
    {
        $this->addOption('platform', 'p', Input::VALUE_OPTIONAL, 'Target platform: android, ios, windows, mac, linux, web, all', 'all');
    }

    public function execute(Input $input, Output $output): int
    {
        $platform = (string)$input->getOption('platform', 'all');
        $bundle = UniversalPackager::bundlePlatform($platform);

        $output->writeln("<bold><magenta>📦 OSHIM Universal Multi-Platform Packager & Compiler</magenta></bold>");
        $output->writeln("Application: <cyan>" . $bundle['app_name'] . " (v" . $bundle['version'] . ")</cyan>");
        $output->writeln("Build Platform: <yellow>" . strtoupper($platform) . "</yellow>");
        $output->writeln("Build Latency: <green>" . $bundle['build_time_ms'] . " ms</green>");
        $output->writeln("--------------------------------------------------");

        foreach ($bundle['bundles'] as $key => $info) {
            $output->writeln("<bold><green>✔ [" . strtoupper($key) . "]</green></bold> " . ($info['target'] ?? $key) . " -> <cyan>" . ($info['package_file'] ?? 'ready') . "</cyan>");
        }

        $output->writeln("--------------------------------------------------");
        $output->writeln("<bold><green>🎉 All Target Packages Compiled & Ready for Distribution!</green></bold>");
        return 0;
    }
}
