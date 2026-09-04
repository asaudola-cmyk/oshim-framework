<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Ai\Rag\HybridSearchEngine;
use Oshim\Ai\Rag\SemanticCache;

final class HybridRagTest extends TestCase
{
    public function testHybridSearchRRF(): void
    {
        $engine = new HybridSearchEngine();
        $engine->index('d1', 'OSHIM Cloud MicroVM boots in 1.8 milliseconds with KVM.', ['type' => 'virt']);
        $engine->index('d2', 'OSHIM achieves 1.4M RPS zero-dependency throughput.', ['type' => 'perf']);
        $engine->index('d3', 'Baking delicious homemade sourdough bread with yeast.', ['type' => 'misc']);

        $results = $engine->search('How fast does the MicroVM boot?');

        $this->assertNotEmpty($results);
        $this->assertSame('d1', $results[0]['doc_id']);
        $this->assertTrue($results[0]['score'] > 0);
    }

    public function testSemanticCache(): void
    {
        $cache = new SemanticCache(0.85);
        $cache->set('How fast is OSHIM?', 'OSHIM handles 1.4M+ RPS with zero dependencies.');

        $this->assertSame(1, $cache->count());
        $cached = $cache->get('How fast is OSHIM?');
        $this->assertNotNull($cached);
        $this->assertStringContainsString('1.4M+', $cached);

        $unrelated = $cache->get('What is the capital of France?');
        $this->assertNull($unrelated);
    }
}
