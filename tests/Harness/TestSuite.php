<?php
declare(strict_types=1);

namespace Oshim\Tests\Harness;

use ReflectionClass;
use ReflectionMethod;

/**
 * Test discovery, suite collection, and reflection analyzer.
 */
class TestSuite
{
    /**
     * @var array<string, array<string>> Map of class name to list of test method names.
     */
    private array $testClasses = [];
    private int $totalTests = 0;

    public function discover(array $options = []): void
    {
        $baseDir = dirname(__DIR__);
        $searchDirs = [];

        // 1. Determine directories and files to scan
        $testFiles = [];
        if (!empty($options['path'])) {
            $customPath = $options['path'];
            if (!str_starts_with($customPath, '/')) {
                $customPath = dirname(__DIR__, 2) . '/' . ltrim($customPath, '/');
            }
            if (is_file($customPath)) {
                $testFiles[] = $customPath;
            } elseif (is_dir($customPath)) {
                $searchDirs[] = $customPath;
            }
        } elseif (isset($options['tier']) && $options['tier'] !== null) {
            $tier = (int)$options['tier'];
            $tierDirMap = [
                1 => $baseDir . '/E2E/Tier1_FeatureCoverage',
                2 => $baseDir . '/E2E/Tier2_BoundaryCornerCases',
                3 => $baseDir . '/E2E/Tier3_CrossFeatureCombinations',
                4 => $baseDir . '/E2E/Tier4_RealWorldScenarios',
            ];
            if (isset($tierDirMap[$tier]) && is_dir($tierDirMap[$tier])) {
                $searchDirs[] = $tierDirMap[$tier];
            }
        } else {
            $candidates = [
                $baseDir . '/Unit',
                $baseDir . '/Functional',
                $baseDir . '/E2E/Tier1_FeatureCoverage',
                $baseDir . '/E2E/Tier2_BoundaryCornerCases',
                $baseDir . '/E2E/Tier3_CrossFeatureCombinations',
                $baseDir . '/E2E/Tier4_RealWorldScenarios',
                $baseDir . '/E2E',
            ];
            foreach ($candidates as $cand) {
                if (is_dir($cand) && !in_array($cand, $searchDirs, true)) {
                    $searchDirs[] = $cand;
                }
            }
        }

        // 2. Discover test files
        foreach ($searchDirs as $dir) {
            $testFiles = array_merge($testFiles, $this->findTestFiles($dir));
        }
        $testFiles = array_unique($testFiles);
        sort($testFiles);

        foreach ($testFiles as $file) {
            $this->discoverFile($file, $options);
        }

        // Sort classes and methods alphabetically for determinism
        ksort($this->testClasses);
        foreach ($this->testClasses as $cls => &$methods) {
            sort($methods);
        }
        unset($methods);

        $this->calculateTotal();
    }

    private function discoverFile(string $file, array $options): void
    {
        $classesBefore = get_declared_classes();
        require_once $file;
        $classesAfter = get_declared_classes();
        $newClasses = array_diff($classesAfter, $classesBefore);

        // Also check if any existing declared class matches this file
        $candidateClasses = array_unique(array_merge($newClasses, $classesAfter));

        foreach ($candidateClasses as $className) {
            if (!class_exists($className, false)) {
                continue;
            }

            $ref = new ReflectionClass($className);
            if ($ref->isAbstract() || !$ref->isSubclassOf(TestCase::class)) {
                continue;
            }

            // Verify file source if possible
            if ($ref->getFileName() !== realpath($file) && $ref->getFileName() !== $file) {
                continue;
            }

            // Check feature filter
            if (!empty($options['feature'])) {
                $feature = strtoupper($options['feature']);
                $baseFileName = basename($file);
                if (!str_contains(strtoupper($className), $feature) && !str_contains(strtoupper($baseFileName), $feature)) {
                    continue;
                }
            }

            $methods = [];
            foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->isStatic()) {
                    continue;
                }

                $methodName = $method->getName();
                $isTestMethod = str_starts_with($methodName, 'test')
                    || str_contains($method->getDocComment() ?: '', '@test')
                    || count($method->getAttributes('Test')) > 0
                    || count($method->getAttributes('Oshim\Tests\Harness\Test')) > 0;

                if (!$isTestMethod) {
                    continue;
                }

                // Check filter option
                if (!empty($options['filter'])) {
                    $pattern = $options['filter'];
                    $fqn = $className . '::' . $methodName;
                    $matches = @preg_match('/' . str_replace('/', '\/', $pattern) . '/i', $fqn) === 1
                        || str_contains(strtolower($fqn), strtolower($pattern));
                    if (!$matches) {
                        continue;
                    }
                }

                $methods[] = $methodName;
            }

            if (!empty($methods)) {
                $this->testClasses[$className] = array_unique(array_merge($this->testClasses[$className] ?? [], $methods));
            }
        }
    }

    private function findTestFiles(string $dir): array
    {
        $files = [];
        if (!is_dir($dir)) {
            return $files;
        }

        $items = scandir($dir);
        if ($items === false) {
            return $files;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $files = array_merge($files, $this->findTestFiles($path));
            } elseif (is_file($path) && str_ends_with($item, 'Test.php')) {
                $files[] = $path;
            }
        }

        return $files;
    }

    public function addTest(string $className, string $method): void
    {
        $this->testClasses[$className][] = $method;
        $this->calculateTotal();
    }

    /**
     * @return array<string, array<string>>
     */
    public function getTestClasses(): array
    {
        return $this->testClasses;
    }

    public function count(): int
    {
        return $this->totalTests;
    }

    public function isEmpty(): bool
    {
        return $this->totalTests === 0;
    }

    private function calculateTotal(): void
    {
        $count = 0;
        foreach ($this->testClasses as $methods) {
            $count += count($methods);
        }
        $this->totalTests = $count;
    }
}
