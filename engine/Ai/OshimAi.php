<?php
declare(strict_types=1);

namespace Oshim\Ai;

use Oshim\Ai\Inference\OshimLlmEngine;
use Oshim\Ai\Tensor\MatrixMath;

class OshimAi
{
    private static ?OshimLlmEngine $defaultEngine = null;

    public static function model(string $name = 'oshim-sovereign-7b', float $temperature = 0.7): OshimLlmEngine
    {
        return new OshimLlmEngine($name, $temperature);
    }

    public static function chat(string $prompt): string
    {
        if (self::$defaultEngine === null) {
            self::$defaultEngine = new OshimLlmEngine();
        }
        $res = self::$defaultEngine->generate($prompt);
        return $res['reply'];
    }

    public static function embed(string $text): array
    {
        if (self::$defaultEngine === null) {
            self::$defaultEngine = new OshimLlmEngine();
        }
        return self::$defaultEngine->generateEmbeddings($text);
    }

    public static function semanticSimilarity(string $textA, string $textB): float
    {
        $vecA = self::embed($textA);
        $vecB = self::embed($textB);
        return MatrixMath::cosineSimilarity($vecA, $vecB);
    }
}
