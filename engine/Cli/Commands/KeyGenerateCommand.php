<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Security\Cipher;
use Oshim\Security\Ed25519Signer;

class KeyGenerateCommand extends Command
{
    protected string $name = 'key:generate';
    protected string $description = 'Generate encryption keys and Ed25519 cluster keypairs';

    protected function configure(): void
    {
        $this->addOption('show', 's', Input::VALUE_NONE, 'Display the generated keys instead of modifying the environment file');
    }

    public function execute(Input $input, Output $output): int
    {
        $appKey = Cipher::generateKey();
        $keypair = Ed25519Signer::generateKeypair();

        if ($input->hasOption('show')) {
            $output->writeln("<bold>APP_KEY:</bold> <green>{$appKey}</green>");
            $output->writeln("<bold>ED25519_PUBLIC_KEY:</bold> <green>{$keypair['publicKey']}</green>");
            $output->writeln("<bold>ED25519_SECRET_KEY:</bold> <green>{$keypair['secretKey']}</green>");
            return 0;
        }

        $basePath = defined('OSHIM_BASE_PATH') ? OSHIM_BASE_PATH : dirname(__DIR__, 3);
        $envFile = $basePath . '/.env';

        $envContent = is_file($envFile) ? (string)file_get_contents($envFile) : '';

        $keys = [
            'APP_KEY'            => $appKey,
            'ED25519_PUBLIC_KEY' => $keypair['publicKey'],
            'ED25519_SECRET_KEY' => $keypair['secretKey'],
        ];

        foreach ($keys as $key => $val) {
            if (preg_match("/^{$key}=.*/m", $envContent)) {
                $envContent = preg_replace("/^{$key}=.*/m", "{$key}={$val}", $envContent);
            } else {
                $envContent .= (empty($envContent) ? '' : "\n") . "{$key}={$val}";
            }
        }

        file_put_contents($envFile, $envContent);

        $output->success("Application master keys and Ed25519 keypairs generated and updated in .env successfully.");
        return 0;
    }
}
