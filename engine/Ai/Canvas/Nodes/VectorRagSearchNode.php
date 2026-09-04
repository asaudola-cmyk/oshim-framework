<?php
declare(strict_types=1);

namespace Oshim\Ai\Canvas\Nodes;

use Oshim\Ai\Canvas\AbstractNode;
use Oshim\Ai\OshimAi;
use Oshim\Ai\Embedding\TfIdfEmbedder;
use Oshim\Ai\Vector\VectorStore;
use Oshim\Ai\Vector\DocumentChunker;

/**
 * Vector RAG Search Node: Semantic vector retrieval and knowledge context assembly.
 */
class VectorRagSearchNode extends AbstractNode
{
    protected string $type = 'vector_rag';
    protected string $title = 'Vector RAG Search';

    private ?VectorStore $vectorStore = null;

    protected function definePorts(): void
    {
        $this->registerInputPort('query', 'string', 'Semantic search query string', true, '');
        $this->registerInputPort('top_k', 'int', 'Number of nearest neighbors to retrieve', false, 3);
        $this->registerInputPort('min_score', 'float', 'Minimum similarity score threshold', false, 0.0);
        $this->registerInputPort('collection', 'string', 'Vector collection or namespace', false, 'default');
        $this->registerInputPort('documents', 'array', 'Inline documents or knowledge items to index', false, []);

        $this->registerOutputPort('rag_context', 'string', 'Consolidated context string for downstream LLM');
        $this->registerOutputPort('retrieved_docs', 'array', 'List of matching document snippets');
        $this->registerOutputPort('matches', 'array', 'Raw match records');
        $this->registerOutputPort('top_score', 'float', 'Top similarity score');
        $this->registerOutputPort('count', 'int', 'Count of matched results');
    }

    public function setVectorStore(VectorStore $store): static
    {
        $this->vectorStore = $store;
        return $this;
    }

    public function getVectorStore(): VectorStore
    {
        if ($this->vectorStore === null) {
            $this->vectorStore = new VectorStore(VectorStore::METRIC_COSINE);
        }
        return $this->vectorStore;
    }

    protected function process(array $inputs): array
    {
        $query = (string)($inputs['query'] ?? $inputs['prompt'] ?? $inputs['user_query'] ?? $this->getConfigValue('default_query', ''));
        $topK = (int)($inputs['top_k'] ?? $this->getConfigValue('top_k', 3));
        $minScore = (float)($inputs['min_score'] ?? $this->getConfigValue('min_score', 0.0));
        $collection = (string)($inputs['collection'] ?? $this->getConfigValue('collection', 'default'));

        $store = $this->getVectorStore();

        // 1. Ingest inline documents from config or inputs if provided
        $docs = (array)($this->getConfigValue('documents', []));
        if (isset($inputs['documents']) && is_array($inputs['documents'])) {
            $docs = array_merge($docs, $inputs['documents']);
        }

        foreach ($docs as $idx => $doc) {
            $docId = is_array($doc) ? (string)($doc['id'] ?? "doc_{$idx}") : "doc_{$idx}";
            $text = is_array($doc) ? (string)($doc['content'] ?? $doc['text'] ?? '') : (string)$doc;
            $meta = is_array($doc) ? (array)($doc['metadata'] ?? []) : [];
            $meta['collection'] = $collection;

            if (!empty($text)) {
                TfIdfEmbedder::indexDocument($text);
                $embedding = OshimAi::embed($text);
                $store->upsert($docId, $embedding, $meta, $text);
            }
        }

        if (trim($query) === '') {
            return [
                'rag_context' => '',
                'retrieved_docs' => [],
                'matches' => [],
                'top_score' => 0.0,
                'count' => 0,
            ];
        }

        // 2. Perform vector search
        $queryVec = OshimAi::embed($query);
        $rawMatches = $store->search($queryVec, max(1, $topK), function (array $meta) use ($collection) {
            if ($collection !== 'default' && isset($meta['collection']) && $meta['collection'] !== $collection) {
                return false;
            }
            return true;
        });

        // 3. Filter by minScore
        $filteredMatches = [];
        $snippets = [];
        $topScore = 0.0;

        foreach ($rawMatches as $m) {
            $score = (float)($m['score'] ?? 0.0);
            if ($score >= $minScore) {
                $filteredMatches[] = $m;
                $snippets[] = $m['text'] ?? '';
                if ($score > $topScore) {
                    $topScore = $score;
                }
            }
        }

        $ragContext = implode("\n---\n", $snippets);

        return [
            'rag_context' => $ragContext,
            'retrieved_docs' => $snippets,
            'matches' => $filteredMatches,
            'top_score' => round($topScore, 4),
            'count' => count($filteredMatches),
        ];
    }
}
