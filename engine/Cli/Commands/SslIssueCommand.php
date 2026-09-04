<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Security\Ssl\CertificateManager;

class SslIssueCommand extends Command
{
    protected string $name = 'ssl:issue';
    protected string $description = 'Issue instant wildcard / SAN SSL certificate via Pure PHP ACME v2';

    protected function configure(): void
    {
        $this->addArgument('domain', Input::OPTIONAL, 'Domain to issue SSL certificate for', 'oshim.cloud');
    }

    public function execute(Input $input, Output $output): int
    {
        $domain = (string)$input->getArgument('domain', 'oshim.cloud');
        $output->writeln("<bold><cyan>Requesting ACME v2 automated SSL certificate for {$domain}...</cyan></bold>");
        
        $cert = CertificateManager::issue($domain);
        $output->writeln("<green>SSL certificate issued successfully!</green>");
        $output->writeln("Common Name: <green>" . $cert['domain'] . "</green>");
        $output->writeln("Valid Until: <yellow>" . $cert['valid_to'] . "</yellow>");
        $output->writeln("Issuer: <dim>Let's Encrypt / OSHIM ACME CA</dim>");
        return 0;
    }
}
