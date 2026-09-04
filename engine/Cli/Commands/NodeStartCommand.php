<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Virtualization\Node\NodeDaemon;
use Oshim\Virtualization\VirtualizationEnvironment;

/**
 * CLI command to boot and run the OSHIM Node Virtualization Daemon.
 */
class NodeStartCommand extends Command
{
    protected string $name = 'node:start';
    protected string $description = 'Start the OSHIM Node Virtualization JSON-RPC Daemon';

    protected function configure(): void
    {
        $this->addOption('host', 'H', Input::VALUE_OPTIONAL, 'Listen host interface', '0.0.0.0')
             ->addOption('port', 'p', Input::VALUE_OPTIONAL, 'Listen TCP port', '9090')
             ->addOption('socket', 's', Input::VALUE_OPTIONAL, 'Unix domain socket path', '')
             ->addOption('node-id', null, Input::VALUE_OPTIONAL, 'Cluster node identifier', 'node-local')
             ->addOption('secret', null, Input::VALUE_OPTIONAL, 'Cluster encryption secret key', '')
             ->addOption('driver', 'd', Input::VALUE_OPTIONAL, 'Virtualization driver (auto, native, mock)', 'auto');
    }

    public function execute(Input $input, Output $output): int
    {
        $host = (string)($input->getOption('host') ?? '0.0.0.0');
        $port = (int)($input->getOption('port') ?? 9090);
        $socketPath = (string)($input->getOption('socket') ?? '');
        $nodeId = (string)($input->getOption('node-id') ?? 'node-local');
        $secret = (string)($input->getOption('secret') ?? getenv('OSHIM_CLUSTER_SECRET') ?: '');
        $driverChoice = (string)($input->getOption('driver') ?? 'auto');

        $driver = VirtualizationEnvironment::resolveDriver($driverChoice);
        $driverName = (new \ReflectionClass($driver))->getShortName();

        $output->writeln("<bold><cyan>OSHIM Cloud</cyan> Node Daemon v1.0.0</bold>");
        $output->writeln("Node ID:  <yellow>{$nodeId}</yellow>");
        $output->writeln("Driver:   <green>{$driverName}</green>");

        $daemon = new NodeDaemon($driver, $nodeId, $secret !== '' ? $secret : null);

        if (!empty($socketPath)) {
            $output->writeln("Binding UNIX socket at <green>{$socketPath}</green>...");
            $daemon->listenUnix($socketPath);
        } else {
            $output->writeln("Listening on <green>tcp://{$host}:{$port}</green>...");
            $daemon->listenTcp($host, $port);
        }

        $output->writeln("Node Daemon initialized successfully. Press Ctrl+C to exit.");
        $daemon->run();

        return 0;
    }
}
