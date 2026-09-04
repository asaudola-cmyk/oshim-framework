<?php
declare(strict_types=1);

namespace Oshim\Ai\Healing;

use ParseError;
use Throwable;

/**
 * SyntaxValidator: Pure PHP code syntax and static integrity validator.
 * Uses PHP token analysis and isolated lint evaluation to prevent breaking hotfixes.
 */
class SyntaxValidator
{
    /**
     * Validate PHP code string without executing it.
     * @return array{valid: bool, error: ?string, line: ?int}
     */
    public static function validateString(string $code): array
    {
        // 1. Basic Tokenization check
        try {
            $tokens = token_get_all($code, TOKEN_PARSE);
        } catch (ParseError $e) {
            return [
                'valid' => false,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ];
        } catch (Throwable $e) {
            return [
                'valid' => false,
                'error' => $e->getMessage(),
                'line' => null,
            ];
        }

        // 2. Perform isolated linting via temp file if CLI php is available
        $tmpFile = tempnam(sys_get_temp_dir(), 'oshim_lint_');
        if ($tmpFile !== false) {
            file_put_contents($tmpFile, $code);
            $result = self::validateFile($tmpFile);
            @unlink($tmpFile);
            return $result;
        }

        return ['valid' => true, 'error' => null, 'line' => null];
    }

    /**
     * Validate PHP file syntax using PHP linter.
     * @return array{valid: bool, error: ?string, line: ?int}
     */
    public static function validateFile(string $filePath): array
    {
        if (!is_file($filePath)) {
            return ['valid' => false, 'error' => "File not found: {$filePath}", 'line' => null];
        }

        $cmd = sprintf('%s -l %s 2>&1', escapeshellcmd(PHP_BINARY), escapeshellarg($filePath));
        $output = (string)@shell_exec($cmd);

        if (str_contains($output, 'No syntax errors detected')) {
            return ['valid' => true, 'error' => null, 'line' => null];
        }

        // Extract error details
        $line = null;
        if (preg_match('/on line (\d+)/i', $output, $matches)) {
            $line = (int)$matches[1];
        }

        return [
            'valid' => false,
            'error' => trim($output),
            'line' => $line,
        ];
    }
}
