<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Testing\TestRunner;

class TestCommand extends Command
{
    protected string $name = 'test';
    protected string $description = 'Run the automated test suite';

    protected function configure(): void
    {
        $this->addOption('filter', 'f', Input::VALUE_OPTIONAL, 'Filter test class or method by pattern')
             ->addOption('unit', 'u', Input::VALUE_NONE, 'Run only unit tests')
             ->addOption('functional', null, Input::VALUE_NONE, 'Run only functional tests')
             ->addOption('e2e', null, Input::VALUE_NONE, 'Run only E2E tests');
    }

    public function execute(Input $input, Output $output): int
    {
        $basePath = defined('OSHIM_BASE_PATH') ? OSHIM_BASE_PATH : dirname(__DIR__, 3);
        $paths = [];

        if ($input->hasOption('unit')) {
            $paths[] = $basePath . '/tests/Unit';
        } elseif ($input->hasOption('functional')) {
            $paths[] = $basePath . '/tests/Functional';
        } elseif ($input->hasOption('e2e')) {
            $paths[] = $basePath . '/tests/E2E';
        } else {
            $paths = [
                $basePath . '/tests/Unit',
                $basePath . '/tests/Functional',
                $basePath . '/tests/E2E',
            ];
        }

        $filter = $input->getOption('filter');
        $runner = new TestRunner($output);

        return $runner->run($paths, $filter ? (string)$filter : null);
    }
}
