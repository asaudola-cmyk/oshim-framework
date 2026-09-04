<?php
declare(strict_types=1);

namespace Oshim\Plugins;

use RuntimeException;

class PluginValidator
{
    /**
     * Forbidden patterns that violate the Sovereign Zero-Dependency Standard.
     */
    private const FORBIDDEN_PATTERNS = [
        '/vendor[\/\\\\]autoload\.php/' => 'External vendor/autoload.php references are strictly forbidden',
        '/node_modules/' => 'External node_modules references are strictly forbidden',
        '/composer\.json/' => 'External Composer package dependencies are forbidden in Sovereign plugins',
        '/eval\s*\(/' => 'Dynamic eval execution is prohibited for security isolation',
        '/shell_exec|system|passthru|exec\s*\(/' => 'Direct unauthorized shell execution is prohibited in plugins',
    ];

    /**
     * Validate that a plugin file or source code is 100% sovereign and zero-dependency.
     */
    public static function validateFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("Plugin file not found: {$filePath}");
        }

        $code = file_get_contents($filePath);
        return self::validateSource($code, $filePath);
    }

    /**
     * Validate raw source code.
     */
    public static function validateSource(string $code, string $identifier = 'inline'): array
    {
        $violations = [];

        foreach (self::FORBIDDEN_PATTERNS as $pattern => $message) {
            if (preg_match($pattern, $code)) {
                $violations[] = [
                    'rule' => $pattern,
                    'message' => $message,
                    'severity' => 'FATAL',
                ];
            }
        }

        $isValid = empty($violations);

        return [
            'valid' => $isValid,
            'identifier' => $identifier,
            'violations' => $violations,
            'standard' => 'OSHIM_SOVEREIGN_V1',
            'status' => $isValid ? 'VERIFIED_SOVEREIGN' : 'REJECTED_DEPENDENCY_OR_SECURITY_VIOLATION',
        ];
    }
}
