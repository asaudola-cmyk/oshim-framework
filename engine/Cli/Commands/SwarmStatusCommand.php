<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Swarm\SwarmCluster;
use Oshim\Swarm\SwarmNode;

class SwarmStatusCommand extends Command
{
    protected string $name = 'swarm:status';
    protected string $description = 'Inspect active OSHIM Swarm cluster topology, nodes, and health';

    protected function configure(): void
    {
        $this->addOption('format', 'f', Input::VALUE_OPTIONAL, 'Output format (table|json)', 'table')
             ->addOption('secret', 's', Input::VALUE_OPTIONAL, 'Cluster secret token', 'oshim_sovereign_swarm_secret');
    }

    public function execute(Input $input, Output $output): int
    {
        $format = (string)$input->getOption('format', 'table');
        $secret = (string)$input->getOption('secret', 'oshim_sovereign_swarm_secret');

        $leader = new SwarmNode('node_master_01', '127.0.0.1', 9500, 'leader', 4, 8192, 1024);
        $cluster = new SwarmCluster($leader, $secret);

        $worker1 = new SwarmNode('node_worker_02', '127.0.0.1', 9501, 'worker', 8, 16384, 2048);
        $worker2 = new SwarmNode('node_worker_03', '127.0.0.1', 9502, 'worker', 8, 16384, 4096);
        $cluster->registerPeer($worker1);
        $cluster->registerPeer($worker2);

        $summary = $cluster->getClusterSummary();

        if (strtolower($format) === 'json') {
            $output->writeln((string)json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return 0;
        }

        $output->writeln("<bold><cyan>👑 OSHIM Sovereign Swarm Cluster Status</cyan></bold>");
        $output->writeln("Status:        <green>{$summary['cluster_status']}</green>");
        $output->writeln("Total Nodes:   <yellow>{$summary['total_nodes']}</yellow> (<green>{$summary['healthy_nodes']} Healthy</green>)");
        $output->writeln("Cluster CPU:   <cyan>{$summary['cluster_cpu_cores']} vCPUs</cyan>");
        $output->writeln("Cluster RAM:   <magenta>{$summary['cluster_memory_total_mb']} MB</magenta> (Used: {$summary['cluster_memory_used_mb']} MB)");
        $output->writeln("State Keys:    <yellow>{$summary['state_keys_count']}</yellow>");
        $output->writeln();
        $output->writeln("<bold>Active Nodes in Mesh:</bold>");

        foreach ($summary['nodes'] as $n) {
            $roleColor = $n['role'] === 'leader' ? 'yellow' : 'cyan';
            $statusColor = $n['status'] === 'HEALTHY' ? 'green' : 'red';
            $output->writeln("  • <{$roleColor}>[{$n['role']}]</{$roleColor}> <bold>{$n['node_id']}</bold> ({$n['host']}:{$n['port']}) — CPU: {$n['cpu_cores']} cores | RAM: {$n['memory_used_mb']}/{$n['memory_total_mb']}MB | <{$statusColor}>{$n['status']}</{$statusColor}>");
        }

        return 0;
    }
}
