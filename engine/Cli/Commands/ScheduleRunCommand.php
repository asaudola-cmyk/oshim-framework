<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Cron\Scheduler;

class ScheduleRunCommand extends Command
{
    protected string $name = 'schedule:run';
    protected string $description = 'Run the scheduled cron tasks due at current timestamp';

    public function execute(Input $input, Output $output): int
    {
        $output->writeln("<info>⏱️  Running OSHIM Sovereign Scheduled Tasks...</info>");

        $scheduler = Scheduler::getInstance();
        $executed = $scheduler->runDue();

        if (empty($executed)) {
            $output->writeln("<comment>No scheduled tasks are due at this minute.</comment>");
            return 0;
        }

        foreach ($executed as $task) {
            $output->writeln("<info>✔ Executed:</info> {$task['description']} ({$task['expression']}) in {$task['duration_ms']}ms");
        }

        $output->writeln("<info>All scheduled tasks executed successfully.</info>");
        return 0;
    }
}
