<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Dns\Parser\BindZoneParser;
use Oshim\Dns\Server\DnsServer;
use Oshim\Dns\Server\DnsServerConfig;
use Oshim\Dns\Zone\MemoryZoneRepository;

/**
 * CLI command to start the Authoritative DNS Protocol Server.
 */
class DnsServeCommand extends Command
{
    protected string $name = 'dns:serve';
    protected string $description = 'Start the OSHIM Authoritative DNS server (UDP/TCP)';

    protected function configure(): void
    {
        $this->addOption('host', 'H', Input::VALUE_OPTIONAL, 'The host address to bind DNS listener on', '0.0.0.0')
             ->addOption('port', 'p', Input::VALUE_OPTIONAL, 'The port to bind DNS listener on', '53')
             ->addOption('zone-file', 'z', Input::VALUE_OPTIONAL, 'Path to initial BIND zone file', '')
             ->addOption('daemon', 'd', Input::VALUE_NONE, 'Run server in daemon background mode');
    }

    public function execute(Input $input, Output $output): int
    {
        $host = (string)$input->getOption('host', '0.0.0.0');
        $port = (int)$input->getOption('port', 53);
        $zoneFile = (string)$input->getOption('zone-file', '');

        $repo = new MemoryZoneRepository();

        if ($zoneFile !== '' && is_file($zoneFile)) {
            $output->writeln("<cyan>Loading BIND zone file:</cyan> {$zoneFile}");
            $zone = BindZoneParser::parseFile($zoneFile);
            $repo->saveZone($zone);
            $output->writeln("<green>Zone '{$zone->getName()}' loaded with " . count($zone->getRecords()) . " records.</green>");
        }

        $config = new DnsServerConfig($host, $port);
        $server = new DnsServer($repo, $config);

        $output->writeln("<bold><cyan>OSHIM Cloud Authoritative DNS Server</cyan></bold>");
        $output->writeln("Listening on <green>UDP/TCP {$host}:{$port}</green>");
        $output->writeln("Press Ctrl+C to stop the DNS server.");
        $output->writeln();

        try {
            $server->start();
        } catch (\Throwable $e) {
            $output->writeln("<red>Error starting DNS server:</red> " . $e->getMessage());
            if ($port === 53 && str_contains(strtolower($e->getMessage()), 'permission denied')) {
                $output->writeln("<yellow>Note: Port 53 is privileged. Run with sudo or use '--port=5353' for unprivileged execution.</yellow>");
            }
            return 1;
        }

        return 0;
    }
}
