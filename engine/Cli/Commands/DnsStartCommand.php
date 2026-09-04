<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

/**
 * CLI command alias 'dns:start' for the Authoritative DNS server.
 */
class DnsStartCommand extends DnsServeCommand
{
    protected string $name = 'dns:start';
    protected string $description = 'Start the OSHIM Authoritative DNS server daemon (UDP/TCP)';
}
