<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Container\Container;
use Oshim\Database\Migrations\Migrator;
use Oshim\Database\Schema\Schema;
use Oshim\Database\Connection;

class MigrateCommand extends Command
{
    protected string $name = 'migrate';
    protected string $description = 'Run pending database migrations';

    protected function configure(): void
    {
        $this->addOption('fresh', null, Input::VALUE_NONE, 'Drop all tables and re-run all migrations')
             ->addOption('seed', null, Input::VALUE_NONE, 'Seed the database after running migrations')
             ->addOption('path', null, Input::VALUE_OPTIONAL, 'The path to migration files');
    }

    public function execute(Input $input, Output $output): int
    {
        $container = Container::getInstance();
        /** @var Migrator $migrator */
        $migrator = $container->make(Migrator::class);

        $path = $input->getOption('path');
        $paths = $path ? [(string)$path] : [];

        if ($input->hasOption('fresh')) {
            $output->warning("Dropping all existing database tables...");
            /** @var Connection $connection */
            $connection = $container->make(Connection::class);
            $tables = Schema::connection($connection->getName())->getColumnListing('sqlite_master');

            // SQLite table drop
            $tableRecords = $connection->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'");
            foreach ($tableRecords as $rec) {
                $t = $rec['name'];
                $connection->statement("DROP TABLE IF EXISTS \"{$t}\"");
            }
            $output->info("Tables dropped successfully.");
        }

        $output->writeln("<cyan>Running database migrations...</cyan>");
        $executed = $migrator->run($paths);

        if (empty($executed)) {
            $output->info("Nothing to migrate. Database is up to date.");
        } else {
            foreach ($executed as $file) {
                $output->success("Migrated: {$file}");
            }
        }

        if ($input->hasOption('seed')) {
            $seedCmd = new SeedCommand();
            $seedCmd->execute(new Input(['oshim', 'db:seed']), $output);
        }

        return 0;
    }
}
