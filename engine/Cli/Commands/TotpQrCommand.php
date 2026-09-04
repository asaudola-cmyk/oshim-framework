<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Security\Totp\TotpEngine;

class TotpQrCommand extends Command
{
    protected string $name = 'auth:totp';
    protected string $description = 'Generate RFC 6238 TOTP 2FA secret and verification code for Google Authenticator';

    protected function configure(): void
    {
        $this->addArgument('account', Input::OPTIONAL, 'User account email or name', 'admin@oshim.cloud');
    }

    public function execute(Input $input, Output $output): int
    {
        $account = $input->getArgument('account') ?? 'admin@oshim.cloud';

        $secret = TotpEngine::generateSecret(20);
        $now = time();
        $code = TotpEngine::generateCode($secret, $now);
        $uri = TotpEngine::getProvisioningUri($secret, $account, 'OSHIM Cloud');

        $output->writeln("\n<info>🔐 RFC 6238 TOTP Two-Factor Authentication (2FA)</info>");
        $output->writeln("  Account: <comment>{$account}</comment>");
        $output->writeln("  Base32 Secret: <info>{$secret}</info>");
        $output->writeln("  Current 6-Digit OTP: <comment>{$code}</comment> (Valid for 30s)");
        $output->writeln("\n<info>📲 Google Authenticator / Authy Provisioning URI:</info>");
        $output->writeln("  <comment>{$uri}</comment>");
        $output->writeln("\nPaste this URI or secret directly into Google Authenticator or mobile 2FA app.\n");

        return 0;
    }
}
