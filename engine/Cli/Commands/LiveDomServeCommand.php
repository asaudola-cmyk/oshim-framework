<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Ui\LiveDom\WebSocketServer;

class LiveDomServeCommand extends Command
{
    protected string $name = 'livedom:serve';
    protected string $description = 'Start the ultra-fast OSHIM LiveDOM WebSocket server';

    public function execute(Input $input, Output $output): int
    {
        $output->writeln("<bold><cyan>⚡ Booting OSHIM LiveDOM WebSocket Server</cyan></bold>");
        
        $ws = new WebSocketServer('0.0.0.0', 8080);
        $ws->start();
        
        return 0;
    }
}
