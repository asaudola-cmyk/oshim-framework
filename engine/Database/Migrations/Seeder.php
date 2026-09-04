<?php
declare(strict_types=1);

namespace Oshim\Database\Migrations;

use Oshim\Container\Container;

abstract class Seeder
{
    abstract public function run(): void;

    public function call(string|array $class): void
    {
        $classes = (array)$class;
        $container = Container::getInstance();

        foreach ($classes as $cls) {
            $seeder = $container->make($cls);
            if ($seeder instanceof Seeder) {
                $seeder->run();
            }
        }
    }
}
