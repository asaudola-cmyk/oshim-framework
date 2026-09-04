<?php
declare(strict_types=1);

namespace Oshim\Ai\Tokenizer;

/**
 * Standard contract for all OSHIM Tokenizers.
 */
interface TokenizerInterface
{
    /**
     * Encode text into token IDs.
     *
     * @param string $text
     * @return array<int>
     */
    public static function encode(string $text): array;

    /**
     * Decode token IDs back into string.
     *
     * @param array<int> $tokens
     * @return string
     */
    public static function decode(array $tokens): string;

    /**
     * Generate dense normalized vector embeddings.
     *
     * @param string $text
     * @param int $dimensions
     * @return array<float>
     */
    public static function embed(string $text, int $dimensions = 64): array;

    /**
     * Register a special token with integer ID.
     */
    public static function registerSpecialToken(string $token, int $id): void;

    /**
     * Load vocabulary map.
     *
     * @param array<string, int> $vocab
     */
    public static function loadVocabulary(array $vocab): void;

    /**
     * Load ranked BPE merge rules.
     *
     * @param array<string|int, string|int> $merges
     */
    public static function loadMerges(array $merges): void;

    /**
     * Reset tokenizer vocabulary, merges, and state.
     */
    public static function reset(): void;
}
