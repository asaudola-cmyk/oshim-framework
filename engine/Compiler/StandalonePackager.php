<?php
declare(strict_types=1);

namespace Oshim\Compiler;

use RuntimeException;

/**
 * StandalonePackager: Single-File Autonomous App Compiler.
 * Tree-shakes and compiles an OSHIM application into a single, zero-dependency, self-contained executable file.
 * The generated file runs on any server with pure PHP 8.3+ WITHOUT needing the OSHIM engine directory!
 */
class StandalonePackager
{
    private string $frameworkEngineDir;

    public function __construct(?string $frameworkEngineDir = null)
    {
        $this->frameworkEngineDir = rtrim($frameworkEngineDir ?? dirname(__DIR__), '/\\');
    }

    /**
     * Package a source script into a standalone self-contained PHP file.
     * @return array{
     *     status: string,
     *     output_file: string,
     *     classes_bundled: list<string>,
     *     size_bytes: int,
     *     sha256: string
     * }
     */
    public function compile(string $sourceFile, string $outputFile): array
    {
        if (!is_file($sourceFile)) {
            throw new RuntimeException("Source file not found: {$sourceFile}");
        }

        $sourceCode = (string)file_get_contents($sourceFile);
        $classes = $this->detectReferencedClasses($sourceCode);

        // Core required micro classes
        $coreClasses = [
            'Oshim\Http\HeaderMap',
            'Oshim\Http\UploadedFile',
            'Oshim\Kernel\RouteParameterExtractor',
            'Oshim\Kernel\MicroKernel',
            'Oshim\Http\Request',
            'Oshim\Http\Response',
            'Oshim\Oshim',
        ];

        // Recursive dependency resolution
        $visited = [];
        $queue = array_unique(array_merge($coreClasses, $classes));
        $allClasses = [];

        while (!empty($queue)) {
            $current = array_shift($queue);
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;
            $allClasses[] = $current;

            $file = $this->resolveClassPath($current);
            if ($file && is_file($file)) {
                $content = (string)file_get_contents($file);
                $deps = $this->detectReferencedClasses($content);
                foreach ($deps as $dep) {
                    if (!isset($visited[$dep])) {
                        $queue[] = $dep;
                    }
                }
            }
        }

        $allClasses = $this->sortClassesByInheritance($allClasses);

        $bundledCode = "<?php\ndeclare(strict_types=1);\n\n";
        $bundledCode .= "/**\n * ⚡ OSHIM Sovereign Standalone Bundle\n * Generated: " . date('Y-m-d H:i:s') . "\n * 100% Zero-Dependency Standalone Executable\n */\n\n";

        $bundledClassNames = [];

        foreach ($allClasses as $class) {
            $classFile = $this->resolveClassPath($class);
            if ($classFile && is_file($classFile)) {
                $content = (string)file_get_contents($classFile);
                $cleaned = $this->cleanPhpFile($content);
                $bundledCode .= "// --- Class: {$class} ---\n" . $cleaned . "\n\n";
                $bundledClassNames[] = $class;
            }
        }

        // Clean user script and wrap in namespace block
        $cleanedUserScript = preg_replace('/^\s*<\?php\s*/i', '', $sourceCode);
        $cleanedUserScript = preg_replace('/declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;/i', '', $cleanedUserScript) ?? $cleanedUserScript;
        $cleanedUserScript = preg_replace('/^\s*require(?:_once)?\s+[^;]+;\s*$/mi', '', $cleanedUserScript) ?? $cleanedUserScript;

        if (preg_match('/^\s*namespace\s+([^;{]+)\s*;/m', $cleanedUserScript, $m)) {
            $userNs = trim($m[1]);
            $body = preg_replace('/^\s*namespace\s+[^;{]+\s*;/m', '', $cleanedUserScript);
            $bundledCode .= "namespace {$userNs} {\n" . trim($body) . "\n}\n";
        } else {
            $bundledCode .= "namespace {\n" . trim($cleanedUserScript) . "\n}\n";
        }

        $outDir = dirname($outputFile);
        if (!is_dir($outDir)) {
            mkdir($outDir, 0777, true);
        }

        file_put_contents($outputFile, $bundledCode, LOCK_EX);

        return [
            'status' => 'COMPILED_SUCCESS',
            'output_file' => $outputFile,
            'classes_bundled' => $bundledClassNames,
            'size_bytes' => (int)filesize($outputFile),
            'sha256' => hash_file('sha256', $outputFile) ?: '',
        ];
    }

    /**
     * @return list<string>
     */
    private function detectReferencedClasses(string $code): array
    {
        $classes = [];

        // 1. Explicit Oshim\ references
        if (preg_match_all('/(Oshim\\\\[a-zA-Z0-9_\\\\]+)/', $code, $matches)) {
            foreach ($matches[1] as $match) {
                $ref = trim($match, " \\'\"");
                if ($ref === 'Oshim\Autoloader') {
                    continue;
                }
                $classes[] = $ref;
            }
        }

        // 2. Same-namespace class references (extends, implements, new, instanceof, StaticCall::)
        if (preg_match('/namespace\s+([^;{]+)\s*;/m', $code, $nsMatch)) {
            $ns = trim($nsMatch[1]);
            if (preg_match_all('/\b(?:extends|implements|new|instanceof)\s+([A-Za-z0-9_]+)\b/', $code, $typeMatches)) {
                foreach ($typeMatches[1] as $type) {
                    $candidate = $ns . '\\' . $type;
                    if ($candidate === 'Oshim\Autoloader') {
                        continue;
                    }
                    if ($this->resolveClassPath($candidate) !== null) {
                        $classes[] = $candidate;
                    }
                }
            }
            if (preg_match_all('/\b([A-Za-z0-9_]+)::[a-zA-Z0-9_]+\b/', $code, $staticMatches)) {
                foreach ($staticMatches[1] as $type) {
                    if (in_array($type, ['self', 'static', 'parent'], true)) {
                        continue;
                    }
                    $candidate = $ns . '\\' . $type;
                    if ($candidate === 'Oshim\Autoloader') {
                        continue;
                    }
                    if ($this->resolveClassPath($candidate) !== null) {
                        $classes[] = $candidate;
                    }
                }
            }
        }

        return array_values(array_unique($classes));
    }

    private function resolveClassPath(string $class): ?string
    {
        if (!str_starts_with($class, 'Oshim\\')) {
            return null;
        }

        $relative = substr($class, strlen('Oshim\\'));
        $filePath = $this->frameworkEngineDir . '/' . str_replace('\\', '/', $relative) . '.php';

        return is_file($filePath) ? $filePath : null;
    }

    private function cleanPhpFile(string $content): string
    {
        // Remove open tag and strict types
        $content = preg_replace('/^\s*<\?php\s*/i', '', $content) ?? $content;
        $content = preg_replace('/declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;/i', '', $content) ?? $content;
        $content = preg_replace('/^\s*require(?:_once)?\s+[^;]+;\s*$/mi', '', $content) ?? $content;
        $content = preg_replace('/^\s*Autoloader::register\([^;]*\);\s*$/mi', '', $content) ?? $content;

        // Extract namespace
        if (preg_match('/^\s*namespace\s+([^;{]+)\s*;/m', $content, $m)) {
            $ns = trim($m[1]);
            $body = preg_replace('/^\s*namespace\s+[^;{]+\s*;/m', '', $content);
            return "namespace {$ns} {\n" . trim((string)$body) . "\n}";
        }

        return "namespace {\n" . trim($content) . "\n}";
    }

    /**
     * Topologically sort classes so parent classes and interfaces precede child classes.
     *
     * @param list<string> $classes
     * @return list<string>
     */
    private function sortClassesByInheritance(array $classes): array
    {
        $classSet = array_flip($classes);
        $parents = [];

        foreach ($classes as $class) {
            $parents[$class] = [];
            $classFile = $this->resolveClassPath($class);
            if (!$classFile || !is_file($classFile)) {
                continue;
            }
            $code = (string)file_get_contents($classFile);
            $ns = '';
            if (preg_match('/namespace\s+([^;{]+)\s*;/m', $code, $nsMatch)) {
                $ns = trim($nsMatch[1]);
            }

            // Extract extends
            if (preg_match('/\bclass\s+[A-Za-z0-9_]+\s+extends\s+([A-Za-z0-9_\\\\]+)/', $code, $extMatch)) {
                $parent = trim($extMatch[1]);
                $fqParent = str_starts_with($parent, '\\') ? ltrim($parent, '\\') : ($ns ? $ns . '\\' . $parent : $parent);
                if (isset($classSet[$fqParent])) {
                    $parents[$class][] = $fqParent;
                }
            }

            // Extract implements
            if (preg_match('/\bclass\s+[A-Za-z0-9_]+(?:\s+extends\s+[A-Za-z0-9_\\\\]+)?\s+implements\s+([^;{]+)/', $code, $implMatch)) {
                $rawInterfaces = explode(',', $implMatch[1]);
                foreach ($rawInterfaces as $rawInterface) {
                    $iface = trim($rawInterface);
                    $fqIface = str_starts_with($iface, '\\') ? ltrim($iface, '\\') : ($ns ? $ns . '\\' . $iface : $iface);
                    if (isset($classSet[$fqIface])) {
                        $parents[$class][] = $fqIface;
                    }
                }
            }
        }

        $sorted = [];
        $visited = [];
        $visiting = [];

        $visit = function (string $node) use (&$visit, &$sorted, &$visited, &$visiting, $parents): void {
            if (isset($visited[$node]) || isset($visiting[$node])) {
                return;
            }
            $visiting[$node] = true;

            foreach ($parents[$node] ?? [] as $dep) {
                $visit($dep);
            }

            unset($visiting[$node]);
            $visited[$node] = true;
            $sorted[] = $node;
        };

        foreach ($classes as $class) {
            $visit($class);
        }

        return $sorted;
    }
}
