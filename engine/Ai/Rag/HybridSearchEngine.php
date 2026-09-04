<?php
declare(strict_types=1);

namespace Oshim\Ai\Rag;

use Oshim\Ai\Embedding\TfIdfEmbedder;
use Oshim\Ai\Vector\VectorStore;

/**
 * Hybrid Search Engine: Combines Dense Vector K-NN + Sparse BM25 Keyword Search
 * using Reciprocal Rank Fusion (RRF) algorithm.
 */
class HybridSearchEngine
{
    private VectorStore $vectorStore;
    /** @var array<string, array{doc_id: string, text: string, terms: array<string, int>}> */
    private array $sparseDocs = [];
    private int $totalWords = 0;

    public function __construct(?VectorStore $vectorStore = null)
    {
        $this->vectorStore = $vectorStore ?? new VectorStore();
    }

    public function index(string $docId, string $text, array $metadata = []): void
    {
        // 1. Index Dense Vector
        TfIdfEmbedder::indexDocument($text);
        $denseVec = TfIdfEmbedder::embed($text);
        $this->vectorStore->upsert($docId, $denseVec, $metadata, $text);

        // 2. Index Sparse BM25 Tokens
        $tokens = TfIdfEmbedder::tokenize($text);
        $tf = [];
        foreach ($tokens as $t) {
            $tStr = (string)$t;
            $tf[$tStr] = ($tf[$tStr] ?? 0) + 1;
        }

        $this->sparseDocs[$docId] = [
            'doc_id' => $docId,
            'text' => $text,
            'terms' => $tf,
            'length' => count($tokens),
        ];
        $this->totalWords += count($tokens);
    }

    /**
     * Hybrid Search using Reciprocal Rank Fusion (RRF)
     * RRF_Score(d) = 1/(k + Rank_dense(d)) + 1/(k + Rank_sparse(d))
     *
     * @param string $query
     * @param int $topK
     * @param int $rrfK
     * @return array<array{doc_id: string, text: string, score: float, source: string}>
     */
    public function search(string $query, int $topK = 5, int $rrfK = 60): array
    {
        // 1. Dense Search Ranking
        $queryVec = TfIdfEmbedder::embed($query);
        $denseResults = $this->vectorStore->search($queryVec, count($this->sparseDocs) ?: 10);
        $denseRanks = [];
        foreach ($denseResults as $rank => $res) {
            $denseRanks[$res['id']] = $rank + 1;
        }

        // 2. Sparse BM25 Lexical Ranking
        $qTokens = TfIdfEmbedder::tokenize($query);
        $sparseScores = [];
        $avgDocLen = count($this->sparseDocs) > 0 ? ($this->totalWords / count($this->sparseDocs)) : 1;

        foreach ($this->sparseDocs as $id => $doc) {
            $score = 0.0;
            $docLen = $doc['length'];
            foreach ($qTokens as $term) {
                $termStr = (string)$term;
                if (isset($doc['terms'][$termStr])) {
                    $tf = $doc['terms'][$termStr];
                    // BM25 formula: (tf * (k1 + 1)) / (tf + k1 * (1 - b + b * (docLen / avgLen)))
                    $score += ($tf * 2.2) / ($tf + 1.2 * (0.25 + 0.75 * ($docLen / max(1, $avgDocLen))));
                }
            }
            if ($score > 0) {
                $sparseScores[$id] = $score;
            }
        }

        arsort($sparseScores);
        $sparseRanks = [];
        $rankIdx = 1;
        foreach (array_keys($sparseScores) as $id) {
            $sparseRanks[$id] = $rankIdx++;
        }

        // 3. Reciprocal Rank Fusion (RRF)
        $allDocIds = array_unique(array_merge(array_keys($denseRanks), array_keys($sparseRanks)));
        $combined = [];

        foreach ($allDocIds as $id) {
            $denseRank = $denseRanks[$id] ?? 999;
            $sparseRank = $sparseRanks[$id] ?? 999;

            $rrfScore = (1.0 / ($rrfK + $denseRank)) + (1.0 / ($rrfK + $sparseRank));
            $docText = $this->sparseDocs[$id]['text'] ?? ($this->vectorStore->get($id)['text'] ?? '');

            $combined[] = [
                'doc_id' => $id,
                'text' => $docText,
                'score' => round($rrfScore, 6),
                'dense_rank' => $denseRank,
                'sparse_rank' => $sparseRank,
            ];
        }

        usort($combined, fn($a, $b) => $b['score'] <=> $a['score']);
        if ($topK <= 0) {
            return [];
        }
        return array_slice($combined, 0, $topK);
    }
}
