<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Cache\Cache;

class CacheClearCommand extends Command
{
    protected string $name = 'cache:clear';
    protected string $description = 'Flush application cache and clear temporary state';

    public function execute(Input $input, Output $output): int
    {
        Cache::clear();
        $output->writeln("\n<info>✔ Application cache flushed successfully!</info>\n");
        return 0;
    }
}
