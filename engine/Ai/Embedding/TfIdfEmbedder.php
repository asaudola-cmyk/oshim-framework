<?php
declare(strict_types=1);

namespace Oshim\Ai\Embedding;

/**
 * Pure PHP TF-IDF & Subword N-Gram Semantic Embedding Engine.
 * Generates deterministic, dense semantic vector representations for text
 * allowing accurate Cosine Similarity ranking and K-NN search.
 */
class TfIdfEmbedder
{
    /** @var array<string, int> Inverted index document frequency */
    private static array $docFrequencies = [];
    private static int $totalDocsIndexed = 0;
    
    /** @var array<string, true> Common English and Bangla stop words */
    private static array $stopWords = [
        'the' => true, 'is' => true, 'at' => true, 'which' => true, 'on' => true,
        'a' => true, 'an' => true, 'and' => true, 'or' => true, 'in' => true,
        'to' => true, 'of' => true, 'for' => true, 'by' => true, 'with' => true,
        'this' => true, 'that' => true, 'it' => true, 'as' => true, 'are' => true,
        'was' => true, 'be' => true, 'has' => true, 'have' => true, 'had' => true,
        'এবং' => true, 'ও' => true, 'বা' => true, 'যে' => true, 'সে' => true,
        'এই' => true, 'তা' => true, 'কি' => true, 'কী' => true, 'একটি' => true,
        'এর' => true, 'হলো' => true, 'হয়' => true, 'হতে' => true, 'আছে' => true,
    ];

    /**
     * Tokenize text into normalized word and subword n-gram terms.
     * @return array<string>
     */
    public static function tokenize(string $text): array
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        // Extract words (supports alphanumeric and UTF-8 script like Bengali)
        preg_match_all('/[\p{L}\p{N}\_\-]+/u', $text, $matches);
        $words = $matches[0] ?? [];

        $tokens = [];
        foreach ($words as $word) {
            $w = (string)$word;
            if (isset(self::$stopWords[$w]) || mb_strlen($w, 'UTF-8') < 2) {
                continue;
            }
            $tokens[] = $w;

            // Character 3-grams for subword morphological matching
            $len = mb_strlen($w, 'UTF-8');
            if ($len >= 4) {
                for ($i = 0; $i <= $len - 3; $i++) {
                    $tri = mb_substr($w, $i, 3, 'UTF-8');
                    $tokens[] = "##" . $tri;
                }
            }
        }

        return $tokens;
    }

    /**
     * Ingest document tokens into document frequency index.
     */
    public static function indexDocument(string $text): void
    {
        $tokens = array_unique(self::tokenize($text));
        self::$totalDocsIndexed++;

        foreach ($tokens as $token) {
            $t = (string)$token;
            self::$docFrequencies[$t] = (self::$docFrequencies[$t] ?? 0) + 1;
        }
    }

    /**
     * Generate dense semantic vector embedding (default 64-dimensions)
     * using hashing trick + TF-IDF weighting and L2 normalization.
     *
     * @return array<float>
     */
    public static function embed(string $text, int $dimensions = 64): array
    {
        $tokens = self::tokenize($text);
        if (empty($tokens)) {
            return array_fill(0, $dimensions, 0.0);
        }

        // 1. Calculate Term Frequencies
        $tf = [];
        foreach ($tokens as $t) {
            $termStr = (string)$t;
            $tf[$termStr] = ($tf[$termStr] ?? 0) + 1;
        }
        $totalTokens = count($tokens);

        // 2. Initialize dense embedding vector
        $vector = array_fill(0, $dimensions, 0.0);

        // 3. Project terms into dense space with TF-IDF weights
        foreach ($tf as $term => $count) {
            $termStr = (string)$term;
            $termFreq = $count / $totalTokens;

            // Inverse Document Frequency with smoothing
            $df = self::$docFrequencies[$termStr] ?? 1;
            $n = max(self::$totalDocsIndexed, 1);
            $idf = log(1.0 + ($n / (1.0 + $df))) + 1.0;

            $weight = $termFreq * $idf;

            // Hash term into dimension slot
            $hash = crc32($termStr);
            $idx = abs($hash) % $dimensions;
            $sign = ($hash & 1) ? 1.0 : -1.0;

            $vector[$idx] += $sign * $weight;

            // Secondary hash for cross-feature density
            $idx2 = (abs($hash >> 8) + 7) % $dimensions;
            $vector[$idx2] += ($sign * 0.5) * $weight;
        }

        // 4. L2-Normalize vector to unit length
        $normSq = 0.0;
        foreach ($vector as $v) {
            $normSq += $v * $v;
        }

        if ($normSq > 0.0) {
            $norm = sqrt($normSq);
            foreach ($vector as $i => $v) {
                $vector[$i] = round($v / $norm, 6);
            }
        }

        return $vector;
    }

    /**
     * Reset indexing state.
     */
    public static function resetIndex(): void
    {
        self::$docFrequencies = [];
        self::$totalDocsIndexed = 0;
    }
}
