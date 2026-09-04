<?php
declare(strict_types=1);

namespace Oshim\Ai\Healing;

use RuntimeException;

/**
 * CodePatcher: Atomic and safe source code mutator with rollback guarantees.
 */
class CodePatcher
{
    /**
     * Atomically apply a code replacement to a file with automatic backup and rollback.
     * @return array{success: bool, backup_path: ?string, error: ?string}
     */
    public static function patchFile(string $filePath, string $targetContent, string $replacementContent): array
    {
        if (!is_file($filePath) || !is_writable($filePath)) {
            return ['success' => false, 'backup_path' => null, 'error' => "File not writable: {$filePath}"];
        }

        $original = (string)file_get_contents($filePath);
        if (!str_contains($original, $targetContent)) {
            return ['success' => false, 'backup_path' => null, 'error' => "Target content not found in file"];
        }

        // Create atomic backup
        $backupPath = $filePath . '.oshim_bak_' . time();
        if (file_put_contents($backupPath, $original) === false) {
            return ['success' => false, 'backup_path' => null, 'error' => "Failed to create backup"];
        }

        // Apply replacement
        $modified = str_replace($targetContent, $replacementContent, $original);

        // Pre-validate syntax before committing
        $validation = SyntaxValidator::validateString($modified);
        if (!$validation['valid']) {
            @unlink($backupPath);
            return [
                'success' => false,
                'backup_path' => null,
                'error' => "Syntax validation failed: " . $validation['error'],
            ];
        }

        // Commit file
        if (file_put_contents($filePath, $modified, LOCK_EX) === false) {
            // Restore immediately
            self::rollback($filePath, $backupPath);
            return ['success' => false, 'backup_path' => null, 'error' => "Failed to write modified file"];
        }

        return [
            'success' => true,
            'backup_path' => $backupPath,
            'error' => null,
        ];
    }

    /**
     * Rollback a file from its backup.
     */
    public static function rollback(string $filePath, string $backupPath): bool
    {
        if (!is_file($backupPath)) {
            return false;
        }

        $restored = copy($backupPath, $filePath);
        if ($restored) {
            @unlink($backupPath);
        }
        return $restored;
    }
}
