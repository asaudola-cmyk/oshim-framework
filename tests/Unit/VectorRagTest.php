<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Ai\Vector\VectorStore;
use Oshim\Ai\Vector\DocumentChunker;
use Oshim\Ai\Rag\RagPipeline;

final class VectorRagTest extends TestCase
{
    public function testVectorStoreUpsertAndCosineSearch(): void
    {
        $store = new VectorStore(VectorStore::METRIC_COSINE);

        // Vector A: [1.0, 0.0, 0.0]
        $store->upsert('doc1', [1.0, 0.0, 0.0], ['topic' => 'tech'], 'Text about tech');
        // Vector B: [0.9, 0.1, 0.0] (very close to A)
        $store->upsert('doc2', [0.9, 0.1, 0.0], ['topic' => 'tech'], 'Text about software');
        // Vector C: [0.0, 1.0, 0.0] (orthogonal to A)
        $store->upsert('doc3', [0.0, 1.0, 0.0], ['topic' => 'cooking'], 'Text about cooking');

        $this->assertSame(3, $store->count());

        $results = $store->search([1.0, 0.0, 0.0], 2);
        $this->assertCount(2, $results);
        $this->assertSame('doc1', $results[0]['id']);
        $this->assertSame('doc2', $results[1]['id']);
        $this->assertTrue($results[0]['score'] > $results[1]['score']);

        // Filter search
        $filtered = $store->search([1.0, 0.0, 0.0], 5, fn($meta) => $meta['topic'] === 'cooking');
        $this->assertCount(1, $filtered);
        $this->assertSame('doc3', $filtered[0]['id']);
    }

    public function testDocumentChunkerSplitsWithOverlap(): void
    {
        $text = "OSHIM Framework is sovereign. It features zero dependencies. It supports native micro-containers. It runs at ultra high speed.";
        $chunks = DocumentChunker::chunk($text, 50, 10);

        $this->assertTrue(count($chunks) >= 2);
        $this->assertNotEmpty($chunks[0]['text']);
        $this->assertSame(0, $chunks[0]['offset']);
    }

    public function testRagPipelineIngestAndAsk(): void
    {
        $pipeline = new RagPipeline();
        $doc = "OSHIM Cloud provides ultra-fast VPS hosting with 1.4M+ RPS. Dedicated KVM hardware microVMs boot in 1.8 milliseconds.";
        $indexed = $pipeline->ingestDocument('vps_doc', $doc, ['category' => 'hosting']);

        $this->assertTrue($indexed >= 1);

        $response = $pipeline->ask("How fast do VPS boot in OSHIM?", 2);
        $this->assertSame("How fast do VPS boot in OSHIM?", $response['query']);
        $this->assertNotEmpty($response['answer']);
        $this->assertNotEmpty($response['retrieved_contexts']);
        $this->assertContains('vps_doc', $response['source_docs']);
    }
}
