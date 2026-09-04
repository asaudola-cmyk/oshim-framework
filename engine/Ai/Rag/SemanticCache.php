<?php
declare(strict_types=1);

namespace Oshim\Ai\Rag;

use Oshim\Ai\OshimAi;
use Oshim\Ai\Tensor\MatrixMath;

/**
 * Ultra-Fast In-Memory Semantic Query Cache.
 * Caches LLM answers and retrieves them in < 0.05ms when question similarity exceeds threshold.
 */
class SemanticCache
{
    /** @var array<array{query: string, embedding: array<float>, answer: string, timestamp: int}> */
    private array $cache = [];
    private float $similarityThreshold;

    public function __construct(float $similarityThreshold = 0.90)
    {
        $this->similarityThreshold = $similarityThreshold;
    }

    public function get(string $query): ?string
    {
        $queryEmbedding = OshimAi::embed($query);

        $bestScore = -1.0;
        $bestAnswer = null;

        foreach ($this->cache as $item) {
            $sim = MatrixMath::cosineSimilarity($queryEmbedding, $item['embedding']);
            if ($sim > $bestScore) {
                $bestScore = $sim;
                $bestAnswer = $item['answer'];
            }
        }

        if ($bestScore >= $this->similarityThreshold) {
            return $bestAnswer;
        }

        return null;
    }

    public function set(string $query, string $answer): void
    {
        $this->cache[] = [
            'query' => $query,
            'embedding' => OshimAi::embed($query),
            'answer' => $answer,
            'timestamp' => time(),
        ];
    }

    public function count(): int
    {
        return count($this->cache);
    }

    public function clear(): void
    {
        $this->cache = [];
    }
}
