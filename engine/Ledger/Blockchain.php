<?php
declare(strict_types=1);

namespace Oshim\Ledger;

use JsonSerializable;
use RuntimeException;

/**
 * Blockchain: Sovereign Zero-Dependency Distributed Ledger.
 * Provides cryptographically immutable records, audit trails, and Merkle verification.
 */
class Blockchain implements JsonSerializable
{
    /** @var list<Block> */
    private array $chain = [];

    /** @var list<array<string, mixed>|string> */
    private array $pendingTransactions = [];

    private int $difficulty;

    public function __construct(int $difficulty = 2)
    {
        $this->difficulty = $difficulty;
        $this->createGenesisBlock();
    }

    private function createGenesisBlock(): void
    {
        $genesisBlock = new Block(
            0,
            1700000000,
            [['type' => 'GENESIS', 'message' => 'OSHIM Sovereign Genesis Block', 'creator' => 'OSHIM_CORE']],
            '0000000000000000000000000000000000000000000000000000000000000000',
            0,
            ['network' => 'OSHIM_SOVEREIGN_V1']
        );
        $this->chain = [$genesisBlock];
    }

    public function getLatestBlock(): Block
    {
        return end($this->chain);
    }

    /**
     * Add a record or transaction to the pending pool (mempool).
     * @param array<string, mixed>|string $transaction
     */
    public function record(array|string $transaction): self
    {
        if (is_array($transaction) && !isset($transaction['recorded_at'])) {
            $transaction['recorded_at'] = microtime(true);
        }
        $this->pendingTransactions[] = $transaction;
        return $this;
    }

    /**
     * Mine pending transactions into a new sovereign block.
     */
    public function minePending(int $difficulty = -1): Block
    {
        $diff = ($difficulty >= 0) ? $difficulty : $this->difficulty;
        $prevBlock = $this->getLatestBlock();

        $newBlock = new Block(
            $prevBlock->getIndex() + 1,
            time(),
            $this->pendingTransactions,
            $prevBlock->getHash(),
            0,
            ['mined_by' => 'OSHIM_NODE']
        );

        $newBlock->mine($diff);

        $this->chain[] = $newBlock;
        $this->pendingTransactions = [];

        return $newBlock;
    }

    /**
     * Verify complete integrity of the chain.
     * Verifies:
     * 1. Hash recalculation matches stored block hash.
     * 2. Block previous_hash matches prior block's hash.
     * 3. Merkle root integrity of each block's transactions.
     * 4. Proof of work difficulty prefix zeros.
     */
    public function isValid(int $expectedDifficulty = -1): bool
    {
        $diff = ($expectedDifficulty >= 0) ? $expectedDifficulty : $this->difficulty;
        $target = str_repeat('0', max(0, $diff));

        $count = count($this->chain);
        for ($i = 1; $i < $count; $i++) {
            $current = $this->chain[$i];
            $previous = $this->chain[$i - 1];

            // 1. Recalculate hash
            if (!hash_equals($current->getHash(), $current->calculateHash())) {
                return false;
            }

            // 2. Previous hash link
            if (!hash_equals($current->getPreviousHash(), $previous->getHash())) {
                return false;
            }

            // 3. Merkle root check
            $tree = new MerkleTree($current->getTransactions());
            if (!hash_equals($current->getMerkleRoot(), $tree->getRoot())) {
                return false;
            }

            // 4. Proof of work check
            if ($diff > 0 && !str_starts_with($current->getHash(), $target)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Search and audit all occurrences of a key-value pair across all blocks.
     * @return list<array{block_index: int, block_hash: string, timestamp: int, transaction: array<string, mixed>|string}>
     */
    public function audit(string $key, mixed $expectedValue = null): array
    {
        $matches = [];

        foreach ($this->chain as $block) {
            foreach ($block->getTransactions() as $tx) {
                if (is_array($tx)) {
                    if (array_key_exists($key, $tx)) {
                        if ($expectedValue === null || $tx[$key] === $expectedValue) {
                            $matches[] = [
                                'block_index' => $block->getIndex(),
                                'block_hash' => $block->getHash(),
                                'timestamp' => $block->getTimestamp(),
                                'transaction' => $tx,
                            ];
                        }
                    }
                } elseif (is_string($tx) && str_contains($tx, $key)) {
                    $matches[] = [
                        'block_index' => $block->getIndex(),
                        'block_hash' => $block->getHash(),
                        'timestamp' => $block->getTimestamp(),
                        'transaction' => $tx,
                    ];
                }
            }
        }

        return $matches;
    }

    /**
     * @return list<Block>
     */
    public function getChain(): array
    {
        return $this->chain;
    }

    public function getBlockCount(): int
    {
        return count($this->chain);
    }

    public function getPendingCount(): int
    {
        return count($this->pendingTransactions);
    }

    /**
     * Save chain to JSON file for sovereign persistence.
     */
    public function saveToFile(string $filePath): void
    {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $json = json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Failed to encode blockchain to JSON');
        }

        file_put_contents($filePath, $json, LOCK_EX);
    }

    /**
     * Load chain from persistent JSON file.
     */
    public static function loadFromFile(string $filePath): self
    {
        if (!is_file($filePath)) {
            throw new RuntimeException("Blockchain file not found: {$filePath}");
        }

        $content = (string)file_get_contents($filePath);
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        $blockchain = new self((int)($data['difficulty'] ?? 2));
        $blockchain->chain = [];

        foreach ($data['chain'] ?? [] as $blockData) {
            $blockchain->chain[] = Block::fromArray($blockData);
        }

        $blockchain->pendingTransactions = (array)($data['pending_transactions'] ?? []);

        return $blockchain;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'difficulty' => $this->difficulty,
            'block_count' => count($this->chain),
            'chain' => array_map(fn(Block $b) => $b->toArray(), $this->chain),
            'pending_transactions' => $this->pendingTransactions,
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
