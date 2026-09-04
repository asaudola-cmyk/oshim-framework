<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Ledger\Blockchain;

/**
 * CLI Command: oshim ledger:audit [--verify] [--mine] [--stats]
 * Sovereign Cryptographic Blockchain Ledger Inspector and Auditor.
 */
class LedgerAuditCommand extends Command
{
    protected string $name = 'ledger:audit';
    protected string $description = 'Inspect and verify sovereign cryptographic blockchain ledger integrity';

    protected function configure(): void
    {
        $this->addOption('verify', 'v', Input::VALUE_NONE, 'Verify cryptographic integrity and Merkle roots across all blocks')
            ->addOption('mine', 'm', Input::VALUE_NONE, 'Mine any pending transactions in mempool')
            ->addOption('stats', 's', Input::VALUE_NONE, 'Display ledger summary statistics')
            ->addOption('query', 'q', Input::VALUE_OPTIONAL, 'Search audit records by key=value pair (e.g. user_id=42)')
            ->addOption('file', 'f', Input::VALUE_OPTIONAL, 'Path to ledger storage file', 'storage/ledger/blockchain.json');
    }

    public function execute(Input $input, Output $output): int
    {
        $output->writeln("<bold><cyan>⛓️  OSHIM Sovereign Cryptographic Ledger</cyan></bold>");

        $filePath = (string)$input->getOption('file', 'storage/ledger/blockchain.json');
        $blockchain = is_file($filePath) ? Blockchain::loadFromFile($filePath) : new Blockchain(1);

        if ($input->getOption('mine')) {
            $output->writeln("Mining pending ledger transactions...");
            $mined = $blockchain->minePending();
            $output->writeln("<green>✔ Mined Block #{$mined->getIndex()} | Hash: {$mined->getHash()}</green>");
            $blockchain->saveToFile($filePath);
        }

        $isValid = $blockchain->isValid();
        $blockCount = $blockchain->getBlockCount();
        $pendingCount = $blockchain->getPendingCount();

        $output->writeln("• Total Blocks:   <yellow>{$blockCount}</yellow>");
        $output->writeln("• Mempool Items:  <yellow>{$pendingCount}</yellow>");
        $output->writeln("• Chain Status:   " . ($isValid ? "<green>✔ 100% CRYPTOGRAPHICALLY VALID</green>" : "<red>✘ TAMPERED OR INVALID</red>"));
        $output->writeln("• Latest Hash:    <dim>{$blockchain->getLatestBlock()->getHash()}</dim>");
        $output->writeln("• Merkle Root:    <dim>{$blockchain->getLatestBlock()->getMerkleRoot()}</dim>");

        $query = $input->getOption('query');
        if ($query) {
            $parts = explode('=', (string)$query, 2);
            $key = $parts[0];
            $val = $parts[1] ?? null;

            $output->writeln("\n<bold>Auditing Key: {$key}</bold>");
            $records = $blockchain->audit($key, $val);
            if (empty($records)) {
                $output->writeln("<dim>No matching records found in immutable history.</dim>");
            } else {
                foreach ($records as $r) {
                    $output->writeln("  [Block #{$r['block_index']}] " . json_encode($r['transaction']));
                }
            }
        }

        return $isValid ? 0 : 1;
    }
}
