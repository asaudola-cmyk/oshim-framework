<?php
declare(strict_types=1);

namespace Oshim\Ai\Tokenizer;

use Oshim\Ai\Embedding\TfIdfEmbedder;
use Oshim\Ai\Tensor\Tensor;
use Oshim\Ai\Tensor\MatrixMath;

/**
 * Enterprise BPE & SentencePiece Tokenizer for GGUF/LLaMA/Mistral Models.
 * Supports ranked merge rules, SentencePiece whitespace markers (' '),
 * byte-level fallbacks (<0xXX>), special tokens, and dense neural embeddings.
 */
class GgufTokenizer implements TokenizerInterface
{
    private static array $vocab = [];
    private static array $reverseVocab = [];
    private static array $mergeRanks = [];
    private static bool $userVocabLoaded = false;

    private static array $specialTokens = [
        '<unk>'             => 0,
        '<s>'               => 1,
        '</s>'              => 2,
        '<bos>'             => 1,
        '<eos>'             => 2,
        '[INST]'            => 3,
        '[/INST]'           => 4,
        '<pad>'             => 32000,
        '<|im_start|>'      => 32001,
        '<|im_end|>'        => 32002,
        '<|begin_of_text|>' => 128000,
        '<|end_of_text|>'   => 128001,
        '<|eot_id|>'        => 128009,
    ];

    private static array $canonicalSpecialReverse = [
        0      => '<unk>',
        1      => '<s>',
        2      => '</s>',
        3      => '[INST]',
        4      => '[/INST]',
        32000  => '<pad>',
        32001  => '<|im_start|>',
        32002  => '<|im_end|>',
        128000 => '<|begin_of_text|>',
        128001 => '<|end_of_text|>',
        128009 => '<|eot_id|>',
    ];

    public const SENTENCEPIECE_PREFIX = "\xE2\x96\x81"; // ' ' U+2581

    public static function loadVocabulary(array $vocab): void
    {
        self::$vocab = $vocab;
        self::$reverseVocab = array_flip($vocab);
        self::$userVocabLoaded = true;
    }

    public static function getVocabulary(): array
    {
        return self::$vocab;
    }

    public static function registerSpecialToken(string $token, int $id): void
    {
        self::$specialTokens[$token] = $id;
        self::$canonicalSpecialReverse[$id] = $token;
        self::$vocab[$token] = $id;
        self::$reverseVocab[$id] = $token;
    }

    public static function getSpecialTokens(): array
    {
        return self::$specialTokens;
    }

    public static function loadMerges(array $merges): void
    {
        self::$mergeRanks = [];
        $rank = 0;
        foreach ($merges as $key => $val) {
            if (is_int($key) && is_string($val)) {
                self::$mergeRanks[trim($val)] = $rank++;
            } elseif (is_string($key) && is_int($val)) {
                self::$mergeRanks[trim($key)] = $val;
            } elseif (is_string($key) && is_string($val)) {
                self::$mergeRanks[trim($key . ' ' . $val)] = $rank++;
            }
        }
    }

    public static function addMerge(string $a, string $b): void
    {
        $pair = trim($a . ' ' . $b);
        if (!isset(self::$mergeRanks[$pair])) {
            self::$mergeRanks[$pair] = count(self::$mergeRanks);
        }
    }

    public static function addRankedMerge(string $a, string $b, int $rank): void
    {
        $pair = trim($a . ' ' . $b);
        self::$mergeRanks[$pair] = $rank;
    }

    public static function getMerges(): array
    {
        return self::$mergeRanks;
    }

    public static function reset(): void
    {
        self::$vocab = [];
        self::$reverseVocab = [];
        self::$mergeRanks = [];
        self::$userVocabLoaded = false;
        self::$specialTokens = [
            '<unk>'             => 0,
            '<s>'               => 1,
            '</s>'              => 2,
            '<bos>'             => 1,
            '<eos>'             => 2,
            '[INST]'            => 3,
            '[/INST]'           => 4,
            '<pad>'             => 32000,
            '<|im_start|>'      => 32001,
            '<|im_end|>'        => 32002,
            '<|begin_of_text|>' => 128000,
            '<|end_of_text|>'   => 128001,
            '<|eot_id|>'        => 128009,
        ];
        self::$canonicalSpecialReverse = [
            0      => '<unk>',
            1      => '<s>',
            2      => '</s>',
            3      => '[INST]',
            4      => '[/INST]',
            32000  => '<pad>',
            32001  => '<|im_start|>',
            32002  => '<|im_end|>',
            128000 => '<|begin_of_text|>',
            128001 => '<|end_of_text|>',
            128009 => '<|eot_id|>',
        ];
    }

    public static function tokenize(string $text): array
    {
        return self::encode($text);
    }

    public static function encode(string $text): array
    {
        if ($text === '') {
            return [];
        }

        // 1. Build special tokens pattern (sorted longest first to avoid prefix shadowing)
        $specialKeys = array_keys(self::$specialTokens);
        usort($specialKeys, fn($a, $b) => strlen((string)$b) <=> strlen((string)$a));
        $quotedSpecial = array_map(fn($t) => preg_quote((string)$t, '/'), $specialKeys);
        $pattern = '/(' . implode('|', $quotedSpecial) . ')/u';

        // Split text around special tokens
        $segments = @preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if ($segments === false || preg_last_error() !== PREG_NO_ERROR) {
            // Raw binary / invalid UTF-8 fallback: encode each byte as <0xXX>
            $tokenIds = [];
            $len = strlen($text);
            for ($i = 0; $i < $len; $i++) {
                $byteVal = ord($text[$i]);
                $byteToken = sprintf('<0x%02X>', $byteVal);
                if (isset(self::$vocab[$byteToken])) {
                    $tokenIds[] = self::$vocab[$byteToken];
                } else {
                    $id = count(self::$vocab) + 100;
                    self::$vocab[$byteToken] = $id;
                    self::$reverseVocab[$id] = $byteToken;
                    $tokenIds[] = $id;
                }
            }
            return $tokenIds;
        }

        $tokenIds = [];

        foreach ($segments as $segment) {
            // Check if segment is a special token
            if (isset(self::$specialTokens[$segment])) {
                $tokenIds[] = self::$specialTokens[$segment];
                continue;
            }

            // Check if entire segment exists in loaded vocabulary
            if (self::$userVocabLoaded && isset(self::$vocab[$segment])) {
                $tokenIds[] = self::$vocab[$segment];
                continue;
            }

            // 2. Pre-process words with SentencePiece space marker
            $parts = preg_split('/(\s+)/u', $segment, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            if ($parts === false) {
                $parts = [$segment];
            }

            $hasLeadingSpace = false;
            foreach ($parts as $part) {
                if (preg_match('/^\s+$/u', $part)) {
                    $hasLeadingSpace = true;
                    continue;
                }

                $word = $hasLeadingSpace ? self::SENTENCEPIECE_PREFIX . $part : $part;
                $hasLeadingSpace = false;

                // If user vocab is loaded
                if (self::$userVocabLoaded) {
                    if (isset(self::$vocab[$word])) {
                        $tokenIds[] = self::$vocab[$word];
                        continue;
                    }

                    if (str_starts_with($word, self::SENTENCEPIECE_PREFIX) && isset(self::$vocab[substr($word, strlen(self::SENTENCEPIECE_PREFIX))])) {
                        $cleanWord = substr($word, strlen(self::SENTENCEPIECE_PREFIX));
                        $tokenIds[] = self::$vocab[$cleanWord];
                        continue;
                    }

                    // Apply BPE if merges exist
                    if (!empty(self::$mergeRanks)) {
                        $subwords = self::applyBpe($part);
                        foreach ($subwords as $sub) {
                            if (isset(self::$specialTokens[$sub])) {
                                $tokenIds[] = self::$specialTokens[$sub];
                            } elseif (isset(self::$vocab[$sub])) {
                                $tokenIds[] = self::$vocab[$sub];
                            } else {
                                // Character/byte fallback
                                $chars = mb_str_split($sub, 1, 'UTF-8');
                                foreach ($chars as $ch) {
                                    if (isset(self::$vocab[$ch])) {
                                        $tokenIds[] = self::$vocab[$ch];
                                    } else {
                                        $bytes = unpack('C*', $ch) ?: [];
                                        foreach ($bytes as $b) {
                                            $byteToken = sprintf('<0x%02X>', $b);
                                            $id = self::$vocab[$byteToken] ?? (count(self::$vocab) + 100);
                                            self::$vocab[$byteToken] = $id;
                                            self::$reverseVocab[$id] = $byteToken;
                                            $tokenIds[] = $id;
                                        }
                                    }
                                }
                            }
                        }
                        continue;
                    }

                    // No merges loaded, lookup chars or bytes in user vocab
                    $chars = mb_str_split($part, 1, 'UTF-8');
                    foreach ($chars as $ch) {
                        if (isset(self::$vocab[$ch])) {
                            $tokenIds[] = self::$vocab[$ch];
                        } else {
                            $bytes = unpack('C*', $ch) ?: [];
                            foreach ($bytes as $b) {
                                $byteToken = sprintf('<0x%02X>', $b);
                                $id = self::$vocab[$byteToken] ?? (count(self::$vocab) + 100);
                                self::$vocab[$byteToken] = $id;
                                self::$reverseVocab[$id] = $byteToken;
                                $tokenIds[] = $id;
                            }
                        }
                    }
                    continue;
                }

                // Dynamic vocabulary mode (zero pre-loaded vocab)
                if (!empty(self::$mergeRanks)) {
                    $subwords = self::applyBpe($part);
                    foreach ($subwords as $sub) {
                        if (isset(self::$specialTokens[$sub])) {
                            $tokenIds[] = self::$specialTokens[$sub];
                        } elseif (isset(self::$vocab[$sub])) {
                            $tokenIds[] = self::$vocab[$sub];
                        } else {
                            $id = count(self::$vocab) + 100;
                            self::$vocab[$sub] = $id;
                            self::$reverseVocab[$id] = $sub;
                            $tokenIds[] = $id;
                        }
                    }
                    continue;
                }

                // Default dynamic word token
                if (!isset(self::$vocab[$word])) {
                    $id = count(self::$vocab) + 100;
                    self::$vocab[$word] = $id;
                    self::$reverseVocab[$id] = $word;
                }
                $tokenIds[] = self::$vocab[$word];
            }
        }

        return $tokenIds;
    }

    public static function decode(array $tokenIds): string
    {
        $text = '';

        foreach ($tokenIds as $id) {
            $piece = null;
            if (isset(self::$canonicalSpecialReverse[$id])) {
                $piece = self::$canonicalSpecialReverse[$id];
                $text .= (strlen($text) > 0 && !str_ends_with($text, ' ') ? ' ' : '') . $piece . ' ';
                continue;
            } elseif (isset(self::$reverseVocab[$id])) {
                $piece = self::$reverseVocab[$id];
            } elseif (!empty(self::$vocab)) {
                $rev = array_flip(self::$vocab);
                $piece = $rev[$id] ?? '';
            }

            if ($piece === null || $piece === '') {
                continue;
            }

            // Byte fallback: <0xXX> -> raw byte
            if (preg_match('/^<0x([0-9A-Fa-f]{2})>$/', $piece, $m)) {
                $text .= chr((int)hexdec($m[1]));
                continue;
            }

            // SentencePiece prefix conversion
            if (str_starts_with($piece, self::SENTENCEPIECE_PREFIX)) {
                $clean = substr($piece, strlen(self::SENTENCEPIECE_PREFIX));
                $text .= (strlen($text) > 0 && !str_ends_with($text, ' ') ? ' ' : '') . $clean;
            } else {
                // Natural word boundary spacing
                if (strlen($text) > 0 && !str_ends_with($text, ' ') && !preg_match('/^[.,!?;:\'")\]\}]/u', $piece) && !preg_match('/[([{\'"]$/u', $text)) {
                    $text .= ' ' . $piece;
                } else {
                    $text .= $piece;
                }
            }
        }

        return $text;
    }

    private static function applyBpe(string $word): array
    {
        if (isset(self::$vocab[$word])) {
            return [$word];
        }

        $symbols = mb_str_split($word, 1, 'UTF-8');
        if (count($symbols) <= 1) {
            return $symbols;
        }

        while (count($symbols) > 1) {
            $minRank = PHP_INT_MAX;
            $bestPair = null;

            for ($i = 0; $i < count($symbols) - 1; $i++) {
                $pair = $symbols[$i] . ' ' . $symbols[$i + 1];
                if (isset(self::$mergeRanks[$pair])) {
                    $rank = self::$mergeRanks[$pair];
                    if ($rank < $minRank) {
                        $minRank = $rank;
                        $bestPair = $pair;
                    }
                }
            }

            if ($bestPair === null) {
                break;
            }

            $newSymbols = [];
            $i = 0;
            $pairParts = explode(' ', $bestPair, 2);
            while ($i < count($symbols)) {
                if ($i < count($symbols) - 1 && $symbols[$i] === $pairParts[0] && $symbols[$i + 1] === $pairParts[1]) {
                    $newSymbols[] = $symbols[$i] . $symbols[$i + 1];
                    $i += 2;
                } else {
                    $newSymbols[] = $symbols[$i];
                    $i++;
                }
            }
            $symbols = $newSymbols;
        }

        // Check if the entire merged symbol is in vocabulary
        if (count($symbols) === 1 && isset(self::$vocab[$symbols[0]])) {
            return $symbols;
        }

        return $symbols;
    }

    /**
     * Generate Dense Neural Embeddings using Token Projection, TF-IDF weighting, and L2 Normalization.
     */
    public static function embed(string $text, int $dimensions = 64): array
    {
        return TfIdfEmbedder::embed($text, $dimensions);
    }

    /**
     * Inspect GGUF model binary file headers.
     */
    public static function loadFromGgufFile(string $filePath): ?array
    {
        if (!file_exists($filePath)) {
            return null;
        }

        $fp = @fopen($filePath, 'rb');
        if (!$fp) {
            return null;
        }

        $magic = fread($fp, 4);
        if ($magic !== 'GGUF') {
            fclose($fp);
            return null;
        }

        $version = unpack('V', fread($fp, 4))[1] ?? 0;
        $tensorCount = unpack('P', fread($fp, 8))[1] ?? 0;
        $kvCount = unpack('P', fread($fp, 8))[1] ?? 0;

        fclose($fp);

        return [
            'format' => 'GGUF',
            'version' => $version,
            'tensor_count' => $tensorCount,
            'kv_count' => $kvCount,
            'file_size' => filesize($filePath),
        ];
    }
}
