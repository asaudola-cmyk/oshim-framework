<?php

declare(strict_types=1);

namespace Unum\Tensor;

use FFI;
use InvalidArgumentException;
use RuntimeException;
use Unum\HardwareExecutor;

/**
 * 👑 Sovereign Bare-Metal Vector Index & Neural Semantic Search
 * 
 * WHY: Python vector databases (Faiss, Chroma, Pinecone, Qdrant) introduce massive overhead,
 * network hops, serialization latency, and heavy memory footprints.
 * VectorIndex stores high-dimensional dense embeddings directly in native C float buffers,
 * calculating AVX2 / AVX-512 cosine similarities at silicon speed with microsecond latencies.
 */
final class VectorIndex
{
    private int $dimension;
    private HardwareExecutor $executor;

    /** @var array<int|string, FFI\CData> Native C float buffers for each vector */
    private array $vectors = [];

    /** @var array<int|string, array<string, mixed>> Metadata storage per vector */
    private array $metadata = [];

    /** @var list<int|string> Ordered list of vector IDs for fast iteration */
    private array $ids = [];

    public function __construct(int $dimension, ?HardwareExecutor $executor = null)
    {
        if ($dimension <= 0) {
            throw new InvalidArgumentException("Vector dimension must be a positive integer, got: {$dimension}");
        }

        $this->dimension = $dimension;
        $this->executor = $executor ?? new HardwareExecutor();
    }

    public function dimension(): int
    {
        return $this->dimension;
    }

    public function count(): int
    {
        return count($this->ids);
    }

    /**
     * Inserts a vector into the bare-metal index.
     * 
     * @param int|string $id Unique document/entity ID
     * @param array<int, float|int>|FFI\CData $vector High-dimensional dense embedding
     * @param array<string, mixed> $meta Arbitrary associative metadata
     */
    public function insert(int|string $id, array|FFI\CData $vector, array $meta = []): void
    {
        if ($vector instanceof FFI\CData) {
            /* Zero-copy reference */
            $cVector = $vector;
        } else {
            $count = count($vector);
            if ($count !== $this->dimension) {
                throw new InvalidArgumentException("Vector dimension mismatch: expected {$this->dimension}, got {$count}");
            }
            /* WHY: Pack PHP float array into a contiguous C float32 buffer */
            $cVector = $this->executor->newFloatBuffer($this->dimension);
            for ($i = 0; $i < $this->dimension; $i++) {
                $cVector[$i] = (float)$vector[$i];
            }
        }

        if (!isset($this->vectors[$id])) {
            $this->ids[] = $id;
        }

        $this->vectors[$id] = $cVector;
        $this->metadata[$id] = $meta;
    }

    /**
     * Searches the top-K nearest neighbors using AVX-accelerated cosine similarity.
     * 
     * @param array<int, float|int>|FFI\CData $queryVector Query embedding
     * @param int $topK Number of nearest neighbors to retrieve
     * @return list<array{id: int|string, score: float, metadata: array<string, mixed>}>
     */
    public function search(array|FFI\CData $queryVector, int $topK = 5): array
    {
        $total = count($this->ids);
        if ($total === 0) {
            return [];
        }

        if ($queryVector instanceof FFI\CData) {
            $cQuery = $queryVector;
        } else {
            $count = count($queryVector);
            if ($count !== $this->dimension) {
                throw new InvalidArgumentException("Query vector dimension mismatch: expected {$this->dimension}, got {$count}");
            }
            $cQuery = $this->executor->newFloatBuffer($this->dimension);
            for ($i = 0; $i < $this->dimension; $i++) {
                $cQuery[$i] = (float)$queryVector[$i];
            }
        }

        /* WHY: Fast linear scan with AVX-512/AVX2 cosine similarity */
        $scores = [];
        foreach ($this->ids as $id) {
            $cVec = $this->vectors[$id];
            $score = $this->executor->tensorCosineSimilarity($cQuery, $cVec, $this->dimension);
            $scores[] = [
                'id'       => $id,
                'score'    => $score,
                'metadata' => $this->metadata[$id] ?? [],
            ];
        }

        /* Sort descending by cosine similarity score */
        usort($scores, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($scores, 0, max(1, $topK));
    }

    /**
     * Retrieves metadata for a specific vector ID.
     * 
     * @return array<string, mixed>|null
     */
    public function getMetadata(int|string $id): ?array
    {
        return $this->metadata[$id] ?? null;
    }

    /**
     * Clears all indexed vectors and releases memory.
     */
    public function clear(): void
    {
        $this->vectors = [];
        $this->metadata = [];
        $this->ids = [];
    }
}
