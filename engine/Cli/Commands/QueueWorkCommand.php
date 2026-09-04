<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Queue\Worker\QueueWorker;
use Oshim\Queue\Queue;

class QueueWorkCommand extends Command
{
    protected string $name = 'queue:work';
    protected string $description = 'Start processing background jobs from the queue';

    protected function configure(): void
    {
        $this->addOption('queue', 'q', Input::VALUE_OPTIONAL, 'The queue to process', 'default')
             ->addOption('once', 'o', Input::VALUE_NONE, 'Only process the next available job and exit');
    }

    public function execute(Input $input, Output $output): int
    {
        $queue = $input->getOption('queue', 'default');
        $once = $input->getOption('once', false);

        $worker = new QueueWorker(Queue::getManager());

        $output->writeln("\n<info>⚡ OSHIM Sovereign Queue Worker</info>");
        $output->writeln("Processing queue: <comment>{$queue}</comment>\n");

        if ($once) {
            $ran = $worker->runNext((string)$queue);
            if ($ran) {
                $output->writeln("<info>✔ Processed 1 job successfully.</info>");
            } else {
                $output->writeln("<comment>No jobs available in queue.</comment>");
            }
            return 0;
        }

        $worker->daemon((string)$queue, 500000);
        return 0;
    }
}
