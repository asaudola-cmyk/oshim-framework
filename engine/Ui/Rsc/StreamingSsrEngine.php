<?php
declare(strict_types=1);

namespace Oshim\Ui\Rsc;

/**
 * Pure PHP Streaming SSR Engine (React 19 / Next.js Style Chunked Transfer).
 */
class StreamingSsrEngine
{
    /** @var array<SuspenseBoundary> */
    private array $boundaries = [];
    private string $pageTemplate;

    public function __construct(string $pageTemplate)
    {
        $this->pageTemplate = $pageTemplate;
    }

    public function addSuspense(SuspenseBoundary $boundary): self
    {
        $this->boundaries[$boundary->getId()] = $boundary;
        return $this;
    }

    /**
     * Render the initial shell with fallback skeletons.
     */
    public function renderInitialShell(): string
    {
        $html = $this->pageTemplate;
        foreach ($this->boundaries as $boundary) {
            $html = str_replace(
                "<!-- suspense:{$boundary->getId()} -->",
                $boundary->renderInitial(),
                $html
            );
        }
        return $html;
    }

    /**
     * Resolve all async suspense boundaries and return their stream chunks.
     *
     * @return array<array{id: string, stream_chunk: string}>
     */
    public function resolveStreamChunks(): array
    {
        $chunks = [];
        foreach ($this->boundaries as $boundary) {
            $chunks[] = $boundary->resolveChunk();
        }
        return $chunks;
    }

    /**
     * Stream full document to output or callback.
     */
    public function stream(callable $chunkWriter): void
    {
        // 1. Flush initial shell
        $initial = $this->renderInitialShell();
        $chunkWriter($initial);

        // 2. Stream resolved async chunks
        foreach ($this->boundaries as $boundary) {
            $chunk = $boundary->resolveChunk();
            $chunkWriter($chunk['stream_chunk']);
        }
    }
}
