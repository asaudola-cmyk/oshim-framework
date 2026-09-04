<?php
declare(strict_types=1);

namespace Oshim\Ledger;

use JsonSerializable;

/**
 * Block: Sovereign Cryptographic Ledger Block.
 * Contains index, timestamp, previous hash, merkle root, nonce, and payload transactions.
 */
class Block implements JsonSerializable
{
    private int $index;
    private int $timestamp;
    /** @var list<array<string, mixed>|string> */
    private array $transactions;
    private string $previousHash;
    private string $hash;
    private int $nonce = 0;
    private string $merkleRoot;
    /** @var array<string, mixed> */
    private array $metadata = [];

    /**
     * @param list<array<string, mixed>|string> $transactions
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        int $index,
        int $timestamp,
        array $transactions,
        string $previousHash = '',
        int $nonce = 0,
        array $metadata = []
    ) {
        $this->index = $index;
        $this->timestamp = $timestamp;
        $this->transactions = array_values($transactions);
        $this->previousHash = $previousHash;
        $this->nonce = $nonce;
        $this->metadata = $metadata;

        // Calculate Merkle root of transactions
        $merkleTree = new MerkleTree($this->transactions);
        $this->merkleRoot = $merkleTree->getRoot();

        $this->hash = $this->calculateHash();
    }

    public function calculateHash(): string
    {
        $payload = sprintf(
            '%d|%d|%s|%s|%d|%s',
            $this->index,
            $this->timestamp,
            $this->previousHash,
            $this->merkleRoot,
            $this->nonce,
            json_encode($this->metadata, JSON_UNESCAPED_SLASHES)
        );

        return hash('sha256', $payload);
    }

    /**
     * Proof-of-Work mining with difficulty prefix zeros.
     */
    public function mine(int $difficulty = 2): void
    {
        $target = str_repeat('0', max(0, $difficulty));

        while (!str_starts_with($this->hash, $target)) {
            $this->nonce++;
            $this->hash = $this->calculateHash();
        }
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * @return list<array<string, mixed>|string>
     */
    public function getTransactions(): array
    {
        return $this->transactions;
    }

    public function getPreviousHash(): string
    {
        return $this->previousHash;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function getNonce(): int
    {
        return $this->nonce;
    }

    public function getMerkleRoot(): string
    {
        return $this->merkleRoot;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'index' => $this->index,
            'timestamp' => $this->timestamp,
            'transactions' => $this->transactions,
            'merkle_root' => $this->merkleRoot,
            'previous_hash' => $this->previousHash,
            'hash' => $this->hash,
            'nonce' => $this->nonce,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $block = new self(
            (int)($data['index'] ?? 0),
            (int)($data['timestamp'] ?? time()),
            (array)($data['transactions'] ?? []),
            (string)($data['previous_hash'] ?? ''),
            (int)($data['nonce'] ?? 0),
            (array)($data['metadata'] ?? [])
        );

        if (isset($data['hash'])) {
            $block->hash = (string)$data['hash'];
        }

        return $block;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
