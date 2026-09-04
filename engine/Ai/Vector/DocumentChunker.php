<?php
declare(strict_types=1);

namespace Oshim\Ai\Vector;

/**
 * Intelligent Document Chunker for RAG Pipelines.
 */
class DocumentChunker
{
    /**
     * Split text into overlapping chunks.
     *
     * @param string $text
     * @param int $chunkSize Characters per chunk
     * @param int $chunkOverlap Characters of overlap between consecutive chunks
     * @return array<array{id: int, text: string, offset: int}>
     */
    public static function chunk(string $text, int $chunkSize = 300, int $chunkOverlap = 50): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $length = mb_strlen($text);
        if ($length <= $chunkSize) {
            return [[
                'id' => 0,
                'text' => $text,
                'offset' => 0,
            ]];
        }

        $chunks = [];
        $offset = 0;
        $step = max(1, $chunkSize - $chunkOverlap);
        $chunkIndex = 0;

        while ($offset < $length) {
            $chunkText = mb_substr($text, $offset, $chunkSize);

            // Attempt to snap to nearest sentence or paragraph boundary if not at end
            if ($offset + $chunkSize < $length) {
                $lastBreak = max(
                    mb_strrpos($chunkText, "\n\n") ?: 0,
                    mb_strrpos($chunkText, ". ") ?: 0,
                    mb_strrpos($chunkText, "। ") ?: 0 // Bengali sentence end
                );

                if ($lastBreak > (int)($chunkSize * 0.5)) {
                    $chunkText = mb_substr($chunkText, 0, $lastBreak + 1);
                }
            }

            $chunkText = trim($chunkText);
            if ($chunkText !== '') {
                $chunks[] = [
                    'id' => $chunkIndex++,
                    'text' => $chunkText,
                    'offset' => $offset,
                ];
            }

            $offset += $step;
        }

        return $chunks;
    }
}
