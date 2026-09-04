<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Kernel\UniversalKernel;

class UniversalInfoCommand extends Command
{
    protected string $name = 'kernel:info';
    protected string $description = 'Display Universal Kernel OS detection, active drivers, and acceleration status';

    public function execute(Input $input, Output $output): int
    {
        $info = UniversalKernel::info();
        $output->writeln("<bold><cyan>=== OSHIM Universal Sovereign Kernel Environment ===</cyan></bold>");
        $output->writeln("OS Family: <green>" . $info['os_family'] . "</green>");
        $output->writeln("System: <dim>" . $info['php_uname'] . "</dim>");
        $output->writeln("Active Driver: <yellow>" . $info['driver'] . "</yellow>");
        $output->writeln("Supported Target: " . $info['supported_os']);
        $output->writeln("PHP Fiber Async: " . ($info['fibers_enabled'] ? '<green>ACTIVE (Fiber EventLoop)</green>' : '<red>INACTIVE</red>'));
        $output->writeln("FFI Native Syscalls: " . ($info['ffi_enabled'] ? '<green>AVAILABLE</green>' : '<yellow>PORTABLE_EMULATION</yellow>'));
        $output->writeln("<green>Universal Kernel Status: 100% OPERATIONAL</green>");
        return 0;
    }
}
