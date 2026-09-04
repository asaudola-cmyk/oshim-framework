<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Ai\Embedding\TfIdfEmbedder;
use Oshim\Ai\Rag\RagPipeline;
use Oshim\Ai\Tensor\MatrixMath;

final class TfIdfAiTest extends TestCase
{
    public function testTfIdfEmbeddingAndCosineSimilarity(): void
    {
        TfIdfEmbedder::resetIndex();

        $textA = "High performance bare-metal Linux MicroVM cloud virtualization.";
        $textB = "Linux virtual machine cloud computing server.";
        $textC = "Chocolate chip cookies recipe with butter and flour.";

        TfIdfEmbedder::indexDocument($textA);
        TfIdfEmbedder::indexDocument($textB);
        TfIdfEmbedder::indexDocument($textC);

        $vecA = TfIdfEmbedder::embed($textA);
        $vecB = TfIdfEmbedder::embed($textB);
        $vecC = TfIdfEmbedder::embed($textC);

        $this->assertCount(64, $vecA);
        $this->assertCount(64, $vecB);

        $simAB = MatrixMath::cosineSimilarity($vecA, $vecB);
        $simAC = MatrixMath::cosineSimilarity($vecA, $vecC);

        $this->assertTrue($simAB > 0.1, "Related texts must have positive semantic similarity (got {$simAB})");
        $this->assertTrue($simAB > $simAC, "Related texts must score higher than unrelated texts ({$simAB} > {$simAC})");
    }

    public function testGroundedRagPipelineQuery(): void
    {
        $pipeline = new RagPipeline();
        $pipeline->ingestDocument('doc_boot', 'OSHIM Cloud MicroVM boots in 1.8 milliseconds using bare-metal KVM ioctl.', ['source' => 'kvm']);

        $res = $pipeline->ask('How fast does the MicroVM boot?');

        $this->assertNotEmpty($res['retrieved_contexts']);
        $this->assertTrue($res['retrieved_contexts'][0]['score'] > 0);
        $this->assertStringContainsString('1.8 milliseconds', $res['retrieved_contexts'][0]['text']);
        $this->assertStringContainsString('1.8 milliseconds', $res['answer']);
    }
}
