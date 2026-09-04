<?php
declare(strict_types=1);

namespace Oshim\Ai\Healing;

use Throwable;
use ErrorException;

/**
 * SelfHealingEngine: Autonomous Self-Healing & Mutating AI Runtime.
 * Intercepts runtime exceptions and fatal errors, diagnoses root causes,
 * synthesizes safe hotfixes, verifies syntax, and hot-patches code atomically.
 */
class SelfHealingEngine
{
    /** @var list<array<string, mixed>> */
    private array $mutationLog = [];
    private bool $autoApply = false;

    public function __construct(bool $autoApply = false)
    {
        $this->autoApply = $autoApply;
    }

    /**
     * Diagnose an exception and generate an intelligent remediation patch.
     * @return array{
     *     diagnosed: bool,
     *     file: string,
     *     line: int,
     *     error_message: string,
     *     error_type: string,
     *     suggested_patch: ?array{target: string, replacement: string, description: string},
     *     applied: bool,
     *     status: string
     * }
     */
    public function diagnoseAndHeal(Throwable $e): array
    {
        $file = $e->getFile();
        $line = $e->getLine();
        $msg = $e->getMessage();

        $patch = $this->synthesizePatch($file, $line, $msg);

        $applied = false;
        $status = 'DIAGNOSED_NO_PATCH';

        if ($patch !== null) {
            $status = 'PATCH_SYNTHESIZED';
            if ($this->autoApply && is_file($file) && is_writable($file)) {
                $result = CodePatcher::patchFile($file, $patch['target'], $patch['replacement']);
                if ($result['success']) {
                    $applied = true;
                    $status = 'HEALED_HOTPATCH_APPLIED';
                } else {
                    $status = 'PATCH_FAILED: ' . ($result['error'] ?? 'unknown');
                }
            }
        }

        $report = [
            'diagnosed' => true,
            'file' => $file,
            'line' => $line,
            'error_message' => $msg,
            'error_type' => get_class($e),
            'suggested_patch' => $patch,
            'applied' => $applied,
            'status' => $status,
            'timestamp' => time(),
        ];

        $this->mutationLog[] = $report;
        return $report;
    }

    /**
     * Synthesize a smart patch based on file line content and error pattern.
     * @return ?array{target: string, replacement: string, description: string}
     */
    public function synthesizePatch(string $file, int $line, string $msg): ?array
    {
        if (!is_file($file)) {
            return null;
        }

        $lines = file($file);
        if ($lines === false || !isset($lines[$line - 1])) {
            return null;
        }

        $targetLine = $lines[$line - 1];

        // 1. Undefined array key "xyz"
        if (preg_match('/Undefined array key ["\']?([^"\'\s]+)["\']?/i', $msg, $m)) {
            $key = $m[1];
            $pattern = sprintf("/(\\$[a-zA-Z0-9_]+)\\[['\"]%s['\"]\\]/", preg_quote($key, '/'));
            if (preg_match($pattern, $targetLine, $targetMatch)) {
                $replacement = sprintf("(%s['%s'] ?? null)", $targetMatch[1], $key);
                $modifiedLine = str_replace($targetMatch[0], $replacement, $targetLine);
                return [
                    'target' => $targetLine,
                    'replacement' => $modifiedLine,
                    'description' => "Safely coalesced undefined array key '{$key}' with null",
                ];
            }
        }

        // 2. Undefined method assertGreaterThanOrEqual in custom test runners
        if (preg_match('/Call to undefined method .*::assertGreaterThanOrEqual\(\)/i', $msg)) {
            if (preg_match('/\$this->assertGreaterThanOrEqual\(([^,]+),\s*([^)]+)\);/', $targetLine, $m)) {
                $valA = trim($m[1]);
                $valB = trim($m[2]);
                $replacementLine = preg_replace(
                    '/\$this->assertGreaterThanOrEqual\([^)]+\);/',
                    sprintf('$this->assertTrue(%s >= %s);', $valB, $valA),
                    $targetLine
                );
                if ($replacementLine !== null && $replacementLine !== $targetLine) {
                    return [
                        'target' => $targetLine,
                        'replacement' => $replacementLine,
                        'description' => "Replaced unsupported assertGreaterThanOrEqual with assertTrue comparison",
                    ];
                }
            }
        }

        // 3. Division by zero
        if (preg_match('/Division by zero/i', $msg)) {
            if (preg_match('/(\/\s*)(\$[a-zA-Z0-9_]+)/', $targetLine, $m)) {
                $var = $m[2];
                $modifiedLine = str_replace($m[0], sprintf('/ (%s ?: 1)', $var), $targetLine);
                return [
                    'target' => $targetLine,
                    'replacement' => $modifiedLine,
                    'description' => "Protected division against zero with fallback divisor",
                ];
            }
        }

        // 4. Call to a member function on null
        if (preg_match('/Call to a member function ([a-zA-Z0-9_]+)\(\) on null/i', $msg, $m)) {
            $method = $m[1];
            if (preg_match('/(\$[a-zA-Z0-9_]+)->' . preg_quote($method, '/') . '\(/', $targetLine, $varMatch)) {
                $targetCall = $varMatch[0];
                $safeCall = $varMatch[1] . '?->' . $method . '(';
                $modifiedLine = str_replace($targetCall, $safeCall, $targetLine);
                return [
                    'target' => $targetLine,
                    'replacement' => $modifiedLine,
                    'description' => "Converted direct method call '{$method}()' to null-safe operator '?->'",
                ];
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getMutationLog(): array
    {
        return $this->mutationLog;
    }

    /**
     * Register global unhandled exception and error self-healing reactor.
     */
    public function registerGlobalHandler(): void
    {
        set_exception_handler(function (Throwable $e) {
            $report = $this->diagnoseAndHeal($e);
            if ($report['applied']) {
                error_log("[OSHIM AI HEALER] Automatically healed runtime exception in {$report['file']}:{$report['line']}");
            }
        });

        set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) {
            if (!(error_reporting() & $errno)) {
                return false;
            }
            $e = new ErrorException($errstr, 0, $errno, $errfile, $errline);
            $this->diagnoseAndHeal($e);
            return false;
        });
    }
}
