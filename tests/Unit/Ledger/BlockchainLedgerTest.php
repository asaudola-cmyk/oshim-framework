<?php
declare(strict_types=1);

namespace Tests\Unit\Ledger;

use Oshim\Cli\Commands\LedgerAuditCommand;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Ledger\Block;
use Oshim\Ledger\Blockchain;
use Oshim\Ledger\MerkleTree;
use Oshim\Testing\TestCase;

class BlockchainLedgerTest extends TestCase
{
    public function testMerkleTreeRootAndProofVerification(): void
    {
        $transactions = [
            ['tx_id' => 'tx_1', 'amount' => 100, 'sender' => 'Alice', 'receiver' => 'Bob'],
            ['tx_id' => 'tx_2', 'amount' => 250, 'sender' => 'Charlie', 'receiver' => 'Dave'],
            ['tx_id' => 'tx_3', 'amount' => 50, 'sender' => 'Eve', 'receiver' => 'Alice'],
            ['tx_id' => 'tx_4', 'amount' => 500, 'sender' => 'Bob', 'receiver' => 'Charlie'],
        ];

        $tree = new MerkleTree($transactions);
        $root = $tree->getRoot();

        $this->assertNotEmpty($root);
        $this->assertSame(64, strlen($root)); // SHA-256 is 64 hex chars

        // Verify Merkle Proof for leaf 1
        $leaves = $tree->getLeaves();
        $proof = $tree->getProof(1);

        $this->assertNotEmpty($proof);
        $isValidProof = MerkleTree::verifyProof($leaves[1], $proof, $root);
        $this->assertTrue($isValidProof);

        // Tampered leaf must fail verification
        $tamperedLeaf = hash('sha256', 'fake_transaction');
        $this->assertFalse(MerkleTree::verifyProof($tamperedLeaf, $proof, $root));
    }

    public function testBlockchainMiningAndIntegrityValidation(): void
    {
        $blockchain = new Blockchain(difficulty: 1);

        $this->assertSame(1, $blockchain->getBlockCount());
        $this->assertSame(0, $blockchain->getLatestBlock()->getIndex());
        $this->assertTrue($blockchain->isValid());

        // Add transactions to mempool
        $blockchain->record(['action' => 'deploy_contract', 'name' => 'SovereignToken', 'author' => 'Oshim']);
        $blockchain->record(['action' => 'transfer', 'amount' => 1000, 'to' => 'user_99']);

        $this->assertSame(2, $blockchain->getPendingCount());

        // Mine block
        $block = $blockchain->minePending(difficulty: 1);

        $this->assertSame(1, $block->getIndex());
        $this->assertSame(2, $blockchain->getBlockCount());
        $this->assertSame(0, $blockchain->getPendingCount());
        $this->assertTrue($blockchain->isValid());
        $this->assertStringStartsWith('0', $block->getHash());

        // Check audit trail
        $audit = $blockchain->audit('name', 'SovereignToken');
        $this->assertCount(1, $audit);
        $this->assertSame('SovereignToken', $audit[0]['transaction']['name']);
    }

    public function testTamperDetection(): void
    {
        $blockchain = new Blockchain(difficulty: 1);
        $blockchain->record(['secret' => 'original_data']);
        $blockchain->minePending();

        $this->assertTrue($blockchain->isValid());

        // Tamper with serialized chain
        $data = $blockchain->toArray();
        $data['chain'][1]['transactions'][0]['secret'] = 'hacked_data';

        $tamperedJson = json_encode($data);
        $tmpFile = tempnam(sys_get_temp_dir(), 'ledger_tamper_');
        file_put_contents($tmpFile, $tamperedJson);

        $loaded = Blockchain::loadFromFile($tmpFile);
        $this->assertFalse($loaded->isValid(), 'Tampered block content must fail chain validation');
        @unlink($tmpFile);
    }

    public function testLedgerAuditCommand(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'ledger_cli_');
        $blockchain = new Blockchain(difficulty: 1);
        $blockchain->record(['event' => 'user_signup', 'user_id' => 42]);
        $blockchain->minePending();
        $blockchain->saveToFile($tmpFile);

        $cmd = new LedgerAuditCommand();
        $input = new Input(['oshim', '--file=' . $tmpFile, '--verify', '--query=user_id=42']);
        $output = new Output();

        ob_start();
        $code = $cmd->execute($input, $output);
        $text = ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('OSHIM Sovereign Cryptographic Ledger', $text);
        $this->assertStringContainsString('100% CRYPTOGRAPHICALLY VALID', $text);
        $this->assertStringContainsString('user_id', $text);

        @unlink($tmpFile);
    }
}
