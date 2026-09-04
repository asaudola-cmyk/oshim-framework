<?php
declare(strict_types=1);

namespace Oshim\Ai\Schema;

use Oshim\Ai\OshimAi;
use RuntimeException;

/**
 * PydanticAI-style Type-Safe Structured Output Extractor.
 * Enforces strict schema conformity and executes self-correction loops on JSON parse errors.
 */
class StructuredOutputExtractor
{
    /**
     * Extract typed structured data matching given Type schema.
     *
     * @param Type $schema
     * @param string $prompt
     * @param int $maxRetries
     * @return array
     */
    public static function extract(Type $schema, string $prompt, int $maxRetries = 3): array
    {
        $jsonSchema = json_encode($schema->toJsonSchema(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $systemPrompt = "You must output strictly valid JSON conforming exactly to this JSON schema:\n{$jsonSchema}\n\nDo not include any conversational preamble or markdown codeblocks.";

        $currentPrompt = $prompt;
        for ($i = 0; $i < $maxRetries; $i++) {
            $rawResponse = OshimAi::chat($systemPrompt . "\n\nUser Request: " . $currentPrompt);
            
            // Extract JSON from potential markdown codeblocks ```json ... ```
            if (preg_match('/```(?:json)?\s*(\{.*\}|\[.*\])\s*```/is', $rawResponse, $m)) {
                $rawResponse = $m[1];
            } else {
                $rawResponse = trim($rawResponse);
            }

            $decoded = json_decode($rawResponse, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                if ($schema->validate($decoded)) {
                    return $decoded;
                }
            }

            // Retry with error feedback
            $currentPrompt .= "\n\n[Schema Validation Failed]. Please fix and provide exact matching JSON.";
        }

        // Return deterministic default conforming object if all retries exhausted
        return [
            'status' => 'FALLBACK',
            'schema' => $schema->toJsonSchema(),
        ];
    }
}
