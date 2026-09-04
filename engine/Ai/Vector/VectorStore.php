<?php
declare(strict_types=1);

namespace Oshim\Ai\Vector;

use Oshim\Ai\Tensor\MatrixMath;
use InvalidArgumentException;

/**
 * High-performance in-memory Vector Database with K-NN search and metadata filtering.
 */
class VectorStore
{
    public const METRIC_COSINE    = 'cosine';
    public const METRIC_EUCLIDEAN = 'euclidean';
    public const METRIC_DOT       = 'dot';

    /**
     * @var array<string, array{
     *     id: string,
     *     vector: array<float>,
     *     metadata: array<string, mixed>,
     *     text: string
     * }>
     */
    private array $entries = [];
    private string $metric;

    public function __construct(string $metric = self::METRIC_COSINE)
    {
        $this->metric = $metric;
    }

    /**
     * Insert or update a vector record.
     */
    public function upsert(string $id, array $vector, array $metadata = [], string $text = ''): void
    {
        $this->entries[$id] = [
            'id' => $id,
            'vector' => array_values(array_map('floatval', $vector)),
            'metadata' => $metadata,
            'text' => $text,
        ];
    }

    /**
     * Delete a vector record by ID.
     */
    public function delete(string $id): bool
    {
        if (isset($this->entries[$id])) {
            unset($this->entries[$id]);
            return true;
        }
        return false;
    }

    /**
     * Get a record by ID.
     */
    public function get(string $id): ?array
    {
        return $this->entries[$id] ?? null;
    }

    /**
     * Search for Top-K nearest neighbors.
     *
     * @param array<float> $queryVector
     * @param int $topK
     * @param callable|null $filter function(array $metadata): bool
     * @return array<array{id: string, score: float, metadata: array, text: string}>
     */
    public function search(array $queryVector, int $topK = 5, ?callable $filter = null): array
    {
        $queryVector = array_values(array_map('floatval', $queryVector));
        $results = [];

        foreach ($this->entries as $id => $entry) {
            if ($filter !== null && !$filter($entry['metadata'])) {
                continue;
            }

            $score = match ($this->metric) {
                self::METRIC_COSINE => MatrixMath::cosineSimilarity($queryVector, $entry['vector']),
                self::METRIC_EUCLIDEAN => 1.0 / (1.0 + $this->computeEuclideanDistance($queryVector, $entry['vector'])),
                self::METRIC_DOT => MatrixMath::dot($queryVector, $entry['vector']),
                default => MatrixMath::cosineSimilarity($queryVector, $entry['vector']),
            };

            $results[] = [
                'id' => $id,
                'score' => round($score, 6),
                'metadata' => $entry['metadata'],
                'text' => $entry['text'],
            ];
        }

        // Sort descending by score
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        if ($topK <= 0) {
            return [];
        }

        return array_slice($results, 0, $topK);
    }

    private function computeEuclideanDistance(array $a, array $b): float
    {
        $a = array_values($a);
        $b = array_values($b);
        $len = min(count($a), count($b));
        $sum = 0.0;
        for ($i = 0; $i < $len; $i++) {
            $diff = (float)$a[$i] - (float)$b[$i];
            $sum += $diff * $diff;
        }
        return sqrt($sum);
    }

    public function count(): int
    {
        return count($this->entries);
    }

    public function clear(): void
    {
        $this->entries = [];
    }

    public function exportJson(): string
    {
        return json_encode([
            'metric' => $this->metric,
            'entries' => array_values($this->entries),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function importJson(string $json): void
    {
        $data = json_decode($json, true);
        if (is_array($data) && isset($data['entries']) && is_array($data['entries'])) {
            $this->metric = $data['metric'] ?? $this->metric;
            $this->entries = [];
            foreach ($data['entries'] as $entry) {
                if (isset($entry['id'], $entry['vector'])) {
                    $this->upsert(
                        $entry['id'],
                        $entry['vector'],
                        $entry['metadata'] ?? [],
                        $entry['text'] ?? ''
                    );
                }
            }
        }
    }
}
