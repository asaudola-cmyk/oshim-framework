<?php
declare(strict_types=1);

namespace Oshim\Ledger;

/**
 * MerkleTree: Cryptographic Binary Hash Tree for Sovereign Immutable Ledger.
 * Provides O(log N) verification of transaction inclusion.
 */
class MerkleTree
{
    /** @var list<string> */
    private array $leaves = [];

    /** @var list<list<string>> */
    private array $layers = [];

    /**
     * @param list<string|array<string, mixed>> $transactions
     */
    public function __construct(array $transactions = [])
    {
        $this->leaves = array_map(function ($tx) {
            $serialized = is_string($tx) ? $tx : (json_encode($tx, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) ?: '');
            return hash('sha256', $serialized);
        }, $transactions);

        $this->buildTree();
    }

    private function buildTree(): void
    {
        if (empty($this->leaves)) {
            $this->layers = [[hash('sha256', '')]];
            return;
        }

        $this->layers = [$this->leaves];
        $currentLayer = $this->leaves;

        while (count($currentLayer) > 1) {
            $nextLayer = [];
            $count = count($currentLayer);

            for ($i = 0; $i < $count; $i += 2) {
                $left = $currentLayer[$i];
                $right = ($i + 1 < $count) ? $currentLayer[$i + 1] : $left; // Duplicate last if odd
                $nextLayer[] = hash('sha256', $left . $right);
            }

            $this->layers[] = $nextLayer;
            $currentLayer = $nextLayer;
        }
    }

    public function getRoot(): string
    {
        if (empty($this->layers)) {
            return hash('sha256', '');
        }
        $top = end($this->layers);
        return $top[0] ?? hash('sha256', '');
    }

    /**
     * Generate Merkle audit proof for a given leaf index.
     * @return list<array{position: 'left'|'right', hash: string}>
     */
    public function getProof(int $index): array
    {
        $leafCount = count($this->leaves);
        if ($index < 0 || $index >= $leafCount) {
            throw new \OutOfBoundsException("Leaf index {$index} is out of bounds for Merkle tree with {$leafCount} leaves.");
        }

        $proof = [];
        $layerCount = count($this->layers);

        for ($l = 0; $l < $layerCount - 1; $l++) {
            $layer = $this->layers[$l];
            $isRightNode = ($index % 2 === 1);
            $pairIndex = $isRightNode ? $index - 1 : $index + 1;

            if ($pairIndex < count($layer)) {
                $proof[] = [
                    'position' => $isRightNode ? 'left' : 'right',
                    'hash' => $layer[$pairIndex],
                ];
            } else {
                // If odd element, it paired with itself
                $proof[] = [
                    'position' => 'right',
                    'hash' => $layer[$index],
                ];
            }

            $index = intdiv($index, 2);
        }

        return $proof;
    }

    /**
     * Verify a Merkle proof against a root hash.
     * @param list<array{position: 'left'|'right', hash: string}> $proof
     */
    public static function verifyProof(string $leafHash, array $proof, string $root): bool
    {
        if ($leafHash === '' || $root === '') {
            return false;
        }

        $current = $leafHash;

        foreach ($proof as $p) {
            if (!is_array($p) || !isset($p['position'], $p['hash']) || !is_string($p['hash'])) {
                return false;
            }
            if ($p['position'] === 'left') {
                $current = hash('sha256', $p['hash'] . $current);
            } elseif ($p['position'] === 'right') {
                $current = hash('sha256', $current . $p['hash']);
            } else {
                return false;
            }
        }

        return hash_equals($root, $current);
    }

    /**
     * @return list<string>
     */
    public function getLeaves(): array
    {
        return $this->leaves;
    }
}
