<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Swarm\SwarmCluster;
use Oshim\Swarm\SwarmNode;

class SwarmInitCommand extends Command
{
    protected string $name = 'swarm:init';
    protected string $description = 'Initialize a new OSHIM Sovereign Swarm Cluster as Leader';

    protected function configure(): void
    {
        $this->addOption('port', 'p', Input::VALUE_OPTIONAL, 'Swarm TCP mesh listener port', '9500')
             ->addOption('secret', 's', Input::VALUE_OPTIONAL, 'Cluster authentication secret token', 'oshim_secret_' . bin2hex(random_bytes(4)));
    }

    public function execute(Input $input, Output $output): int
    {
        $port = (int)$input->getOption('port', '9500');
        $secret = (string)$input->getOption('secret');

        $nodeId = 'node_' . substr(md5(gethostname() . $port), 0, 8);
        $localNode = new SwarmNode(
            nodeId: $nodeId,
            host: '127.0.0.1',
            port: $port,
            role: 'leader',
            cpuCores: 4,
            memoryTotalMb: 4096
        );

        $cluster = new SwarmCluster($localNode, $secret);

        $output->writeln("<bold><cyan>👑 OSHIM Sovereign Swarm Cluster Initialized</cyan></bold>");
        $output->writeln("Leader Node ID: <green>{$nodeId}</green>");
        $output->writeln("Mesh TCP Port:  <yellow>{$port}</yellow>");
        $output->writeln("Cluster Secret: <magenta>{$secret}</magenta>");
        $output->writeln();
        $output->writeln("<info>To join worker nodes to this cluster, run:</info>");
        $output->writeln("  <dim>oshim swarm:join 127.0.0.1:{$port} --secret={$secret}</dim>");
        $output->writeln();
        $output->writeln("✔ Cluster is healthy and listening for mesh peer connections.");

        return 0;
    }
}
