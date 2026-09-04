<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Virtualization\MicroVmManager;

class VmSpawnCommand extends Command
{
    protected string $name = 'vm:spawn';
    protected string $description = 'Spawn instant <50ms MicroVM on active Universal Kernel driver';

    protected function configure(): void
    {
        $this->addArgument('name', Input::OPTIONAL, 'Name for the MicroVM', 'apex-vps-01');
    }

    public function execute(Input $input, Output $output): int
    {
        $name = (string)$input->getArgument('name', 'apex-vps-01');
        $output->writeln("<bold><cyan>Spawning instant MicroVM: {$name}...</cyan></bold>");
        
        $res = MicroVmManager::spawn($name, [
            'cpu' => 4,
            'ram_mb' => 8192,
            'disk_gb' => 160,
            'os' => 'ubuntu-24.04-lts',
        ]);

        $vm = $res['vm'];
        $output->writeln("<green>MicroVM spawned in {$vm['boot_time_ms']} ms!</green>");
        $output->writeln("VM ID: <yellow>" . $vm['id'] . "</yellow>");
        $output->writeln("IP Address: <cyan>" . $vm['ip_address'] . "</cyan>");
        $output->writeln("Driver: <dim>" . $vm['driver'] . "</dim>");
        return 0;
    }
}
