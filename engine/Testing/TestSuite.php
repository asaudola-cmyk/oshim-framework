<?php
declare(strict_types=1);

namespace Oshim\Testing;

use ReflectionClass;
use ReflectionMethod;

class TestSuite
{
    /**
     * Discover all test classes and their test methods within given paths.
     *
     * @param list<string> $paths
     * @param string|null $filter
     * @return array<class-string<TestCase>, list<string>> Map of TestCase class name => list of test method names
     */
    public static function discover(array $paths, ?string $filter = null): array
    {
        $testClasses = [];

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                if (is_file($path) && str_ends_with($path, 'Test.php')) {
                    self::loadFileAndCollect($path, $testClasses);
                }
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
            foreach ($iterator as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), 'Test.php')) {
                    self::loadFileAndCollect($file->getPathname(), $testClasses);
                }
            }
        }

        $discovered = [];

        foreach ($testClasses as $className) {
            if (!class_exists($className)) {
                continue;
            }

            $reflector = new ReflectionClass($className);
            if ($reflector->isAbstract() || !$reflector->isSubclassOf(TestCase::class)) {
                continue;
            }

            $methods = [];
            foreach ($reflector->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $name = $method->getName();
                if (str_starts_with($name, 'test')) {
                    if ($filter !== null && !str_contains($name, $filter) && !str_contains($className, $filter)) {
                        continue;
                    }
                    $methods[] = $name;
                }
            }

            if (!empty($methods)) {
                $discovered[$className] = $methods;
            }
        }

        return $discovered;
    }

    private static function loadFileAndCollect(string $filePath, array &$testClasses): void
    {
        $declaredBefore = get_declared_classes();
        require_once $filePath;
        $declaredAfter = get_declared_classes();

        $newClasses = array_diff($declaredAfter, $declaredBefore);
        foreach ($newClasses as $cls) {
            $testClasses[] = $cls;
        }
    }
}
