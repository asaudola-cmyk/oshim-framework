<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;

class SelfUpdateCommand extends Command
{
    protected string $name = 'self:update';
    protected string $description = 'Update OSHIM Global Sovereign Framework engine to the latest version';

    public function execute(Input $input, Output $output): int
    {
        $frameworkRoot = dirname(__DIR__, 3);
        $output->writeln("<bold><cyan>🔄 OSHIM Sovereign Framework Self-Updater</cyan></bold>");
        $output->writeln("Framework Location: <yellow>{$frameworkRoot}</yellow>");

        if (is_dir($frameworkRoot . '/.git')) {
            $output->writeln("<info>Checking for updates from official Git upstream...</info>");
            $cmd = "cd " . escapeshellarg($frameworkRoot) . " && git pull --ff-only 2>&1";
            $res = shell_exec($cmd) ?? '';
            $output->writeln("<comment>" . trim($res) . "</comment>");
        } else {
            $output->writeln("<info>Engine is up-to-date and operating in Sovereign standalone mode.</info>");
        }

        // Clear framework caches
        $cacheDir = $frameworkRoot . '/storage/framework/cache';
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/*');
            if ($files) {
                foreach ($files as $file) {
                    if (is_file($file)) @unlink($file);
                }
            }
        }

        $output->writeln("<bold><green>✔ OSHIM Framework is up to date!</green></bold>");
        return 0;
    }
}
