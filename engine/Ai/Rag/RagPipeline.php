<?php
declare(strict_types=1);

namespace Oshim\Ai\Rag;

use Oshim\Ai\OshimAi;
use Oshim\Ai\Embedding\TfIdfEmbedder;
use Oshim\Ai\Vector\DocumentChunker;
use Oshim\Ai\Vector\VectorStore;

/**
 * End-to-End Retrieval-Augmented Generation (RAG) Pipeline.
 */
class RagPipeline
{
    private VectorStore $vectorStore;
    private int $chunkSize;
    private int $chunkOverlap;

    public function __construct(
        ?VectorStore $vectorStore = null,
        int $chunkSize = 300,
        int $chunkOverlap = 50
    ) {
        $this->vectorStore = $vectorStore ?? new VectorStore(VectorStore::METRIC_COSINE);
        $this->chunkSize = $chunkSize;
        $this->chunkOverlap = $chunkOverlap;
    }

    public function getVectorStore(): VectorStore
    {
        return $this->vectorStore;
    }

    /**
     * Ingest and index a document into the vector store.
     */
    public function ingestDocument(string $docId, string $content, array $metadata = []): int
    {
        $chunks = DocumentChunker::chunk($content, $this->chunkSize, $this->chunkOverlap);
        $indexedCount = 0;

        foreach ($chunks as $chunk) {
            TfIdfEmbedder::indexDocument($chunk['text']);
        }

        foreach ($chunks as $chunk) {
            $chunkId = "{$docId}_chunk_{$chunk['id']}";
            $embedding = OshimAi::embed($chunk['text']);

            $chunkMeta = array_merge($metadata, [
                'doc_id' => $docId,
                'chunk_id' => $chunk['id'],
                'offset' => $chunk['offset'],
            ]);

            $this->vectorStore->upsert($chunkId, $embedding, $chunkMeta, $chunk['text']);
            $indexedCount++;
        }

        return $indexedCount;
    }

    /**
     * Query the RAG pipeline with grounded context.
     *
     * @return array{
     *     query: string,
     *     answer: string,
     *     retrieved_contexts: array,
     *     source_docs: array
     * }
     */
    public function ask(string $query, int $topK = 3, ?callable $filter = null): array
    {
        $queryEmbedding = OshimAi::embed($query);
        $matches = $this->vectorStore->search($queryEmbedding, $topK, $filter);

        $contextTexts = [];
        $sourceDocs = [];

        foreach ($matches as $match) {
            $contextTexts[] = $match['text'];
            if (isset($match['metadata']['doc_id'])) {
                $sourceDocs[$match['metadata']['doc_id']] = true;
            }
        }

        $combinedContext = implode("\n---\n", $contextTexts);

        $prompt = "Context:\n" . $combinedContext . "\n\nQuestion: " . $query;
        $answer = OshimAi::chat($prompt);

        return [
            'query' => $query,
            'answer' => $answer,
            'retrieved_contexts' => $matches,
            'source_docs' => array_keys($sourceDocs),
        ];
    }
}
