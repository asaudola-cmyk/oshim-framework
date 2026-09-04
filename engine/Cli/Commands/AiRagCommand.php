<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Ai\Rag\RagPipeline;

class AiRagCommand extends Command
{
    protected string $name = 'ai:rag';
    protected string $description = 'Query the Sovereign AI Vector Database with Retrieval-Augmented Generation (RAG)';

    protected function configure(): void
    {
        $this->addArgument('query', Input::OPTIONAL, 'The prompt/question to ask the RAG knowledge base', 'What is OSHIM Cloud?');
    }

    public function execute(Input $input, Output $output): int
    {
        $query = $input->getArgument('query') ?? 'What is OSHIM Framework?';

        $output->writeln("\n<info>🧠 OSHIM Sovereign Vector RAG Pipeline</info>");
        $output->writeln("Query: <comment>{$query}</comment>");
        $output->writeln("Vector Metric: Cosine Similarity (In-Memory K-NN)");

        $pipeline = new RagPipeline();

        // Ingest default sovereign knowledge base
        $pipeline->ingestDocument('doc_architecture', "OSHIM Framework is a sovereign universal meta-framework written in pure PHP 8.3+ with zero dependencies. It achieves 1.4M+ RPS single-core throughput using memory ring buffers and Linux io_uring.", ['category' => 'architecture']);
        $pipeline->ingestDocument('doc_virt', "OSHIM Cloud features bare-metal KVM MicroVM virtualization booting in 1.8 milliseconds with OverlayFS layer caching and Cgroups v2 limits.", ['category' => 'cloud']);
        $pipeline->ingestDocument('doc_frontend', "OSHIM Frontend engine rivals Next.js 15 with React Server Components, Streaming SSR with Suspense, Fine-Grained Signals, and a < 4KB soft SPA client runtime.", ['category' => 'frontend']);

        $start = microtime(true);
        $result = $pipeline->ask($query, 2);
        $elapsed = round((microtime(true) - $start) * 1000, 2);

        $output->writeln("\n<info>🔍 Retrieved Grounded Contexts:</info>");
        foreach ($result['retrieved_contexts'] as $idx => $ctx) {
            $score = round($ctx['score'] * 100, 1);
            $docId = $ctx['metadata']['doc_id'] ?? 'unknown';
            $output->writeln("  [{$idx}] Doc: <comment>{$docId}</comment> (Match: <info>{$score}%</info>) -> {$ctx['text']}");
        }

        $output->writeln("\n<info>🤖 OSHIM AI Synthesized Answer:</info>");
        $output->writeln($result['answer']);
        $output->writeln("\n<comment>Execution Time: {$elapsed} ms | Zero External Dependencies</comment>\n");

        return 0;
    }
}
