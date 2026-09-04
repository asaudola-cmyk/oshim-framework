<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Container\Container;
use Oshim\Database\Migrations\Seeder;

class SeedCommand extends Command
{
    protected string $name = 'db:seed';
    protected string $description = 'Seed the database with records';

    protected function configure(): void
    {
        $this->addOption('class', 'c', Input::VALUE_OPTIONAL, 'The class name of the root seeder', 'DatabaseSeeder');
    }

    public function execute(Input $input, Output $output): int
    {
        $class = (string)$input->getOption('class', 'DatabaseSeeder');
        $fullClass = str_starts_with($class, 'Database\\Seeders\\') ? $class : "Database\\Seeders\\{$class}";

        if (!class_exists($fullClass)) {
            // Also try App\Seeders or root
            if (class_exists($class)) {
                $fullClass = $class;
            } else {
                $basePath = defined('OSHIM_BASE_PATH') ? OSHIM_BASE_PATH : dirname(__DIR__, 3);
                $seederFile = $basePath . "/database/seeders/{$class}.php";
                if (is_file($seederFile)) {
                    require_once $seederFile;
                }
            }
        }

        if (!class_exists($fullClass) && !class_exists($class)) {
            $output->warning("Seeder class [{$class}] does not exist. Skipping seeding.");
            return 0;
        }

        $targetClass = class_exists($fullClass) ? $fullClass : $class;
        $container = Container::getInstance();
        $seeder = $container->make($targetClass);

        if (!$seeder instanceof Seeder) {
            $output->error("Class [{$targetClass}] must extend " . Seeder::class);
            return 1;
        }

        $output->writeln("<cyan>Seeding database using [{$targetClass}]...</cyan>");
        $seeder->run();
        $output->success("Database seeding completed successfully.");

        return 0;
    }
}
