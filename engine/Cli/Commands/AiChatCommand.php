<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Ai\OshimAi;

class AiChatCommand extends Command
{
    protected string $name = 'ai:chat';
    protected string $description = 'Run native Pure PHP AI & LLM Tensor Inference in terminal';

    protected function configure(): void
    {
        $this->addArgument('prompt', Input::OPTIONAL, 'Prompt to send to OSHIM Native AI', 'Hello OSHIM AI, how is our cloud server doing?');
    }

    public function execute(Input $input, Output $output): int
    {
        $prompt = (string)$input->getArgument('prompt', 'Hello OSHIM AI, how is our cloud server doing?');

        $output->writeln("<bold><magenta>🤖 OSHIM Native AI & LLM Tensor Engine</magenta></bold>");
        $output->writeln("Prompt: <dim>{$prompt}</dim>");
        $output->writeln("Inference Mode: <yellow>Pure PHP 8.3+ Tensor Mathematics (No Python)</yellow>");

        $reply = OshimAi::chat($prompt);

        $output->writeln("<bold><green>OSHIM AI Reply:</green></bold>");
        $output->writeln($reply);
        return 0;
    }
}
