<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Container\Container;
use Oshim\Database\Migrations\Migrator;

class RollbackCommand extends Command
{
    protected string $name = 'migrate:rollback';
    protected string $description = 'Rollback the last database migration batch';

    protected function configure(): void
    {
        $this->addOption('step', 's', Input::VALUE_OPTIONAL, 'The number of batches or steps to rollback', '1')
             ->addOption('path', null, Input::VALUE_OPTIONAL, 'The path to migration files');
    }

    public function execute(Input $input, Output $output): int
    {
        $container = Container::getInstance();
        /** @var Migrator $migrator */
        $migrator = $container->make(Migrator::class);

        $step = (int)$input->getOption('step', '1');
        $path = $input->getOption('path');
        $paths = $path ? [(string)$path] : [];

        $output->writeln("<yellow>Rolling back migrations (steps: {$step})...</yellow>");
        $rolledBack = $migrator->rollback($paths, $step);

        if (empty($rolledBack)) {
            $output->info("No migrations were rolled back.");
        } else {
            foreach ($rolledBack as $file) {
                $output->success("Rolled back: {$file}");
            }
        }

        return 0;
    }
}
