<?php
declare(strict_types=1);

namespace Oshim\Tests\Harness;

use ArrayAccess;
use Countable;
use PDO;
use Throwable;

/**
 * Core assertion engine providing 25+ assertions with unified diff reporting.
 */
class Assert
{
    private static int $assertionCount = 0;

    public static function resetCount(): void
    {
        self::$assertionCount = 0;
    }

    public static function getAssertionCount(): int
    {
        return self::$assertionCount;
    }

    public static function getCount(): int
    {
        return self::$assertionCount;
    }

    public static function incrementCount(int $step = 1): void
    {
        self::$assertionCount += $step;
    }

    public static function recordAssertion(): void
    {
        self::$assertionCount++;
    }

    public static function assertTrue(mixed $actual, string $message = ''): void
    {
        self::recordAssertion();
        if ($actual !== true) {
            $valStr = var_export($actual, true);
            throw new AssertionException(
                $message ?: "Failed asserting that {$valStr} is true."
            );
        }
    }

    public static function assertFalse(mixed $actual, string $message = ''): void
    {
        self::recordAssertion();
        if ($actual !== false) {
            $valStr = var_export($actual, true);
            throw new AssertionException(
                $message ?: "Failed asserting that {$valStr} is false."
            );
        }
    }

    public static function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::recordAssertion();
        if (is_array($expected) && is_array($actual)) {
            if (!self::arraysEqual($expected, $actual)) {
                $diff = self::generateDiff($expected, $actual);
                throw new AssertionException(
                    ($message ? $message . "\n" : '') . "Failed asserting that two arrays are equal.",
                    $diff
                );
            }
            return;
        }

        if ($expected != $actual) {
            $diff = self::generateDiff($expected, $actual);
            throw new AssertionException(
                ($message ? $message . "\n" : '') . "Failed asserting that two values are equal.",
                $diff
            );
        }
    }

    public static function assertNotEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::recordAssertion();
        if (is_array($expected) && is_array($actual) && self::arraysEqual($expected, $actual)) {
            $valStr = var_export($actual, true);
            throw new AssertionException(
                $message ?: "Failed asserting that array {$valStr} is not equal to expected."
            );
        }

        if ($expected == $actual && !(is_array($expected) && is_array($actual))) {
            $valStr = var_export($actual, true);
            throw new AssertionException(
                $message ?: "Failed asserting that {$valStr} is not equal to expected."
            );
        }
    }

    public static function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::recordAssertion();
        if ($expected !== $actual) {
            $diff = self::generateDiff($expected, $actual);
            throw new AssertionException(
                ($message ? $message . "\n" : '') . "Failed asserting that two values are identical (===).",
                $diff
            );
        }
    }

    public static function assertNotSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::recordAssertion();
        if ($expected === $actual) {
            $valStr = var_export($actual, true);
            throw new AssertionException(
                $message ?: "Failed asserting that {$valStr} is not identical (!==) to expected."
            );
        }
    }

    public static function assertNull(mixed $actual, string $message = ''): void
    {
        self::recordAssertion();
        if ($actual !== null) {
            $valStr = var_export($actual, true);
            throw new AssertionException(
                $message ?: "Failed asserting that {$valStr} is null."
            );
        }
    }

    public static function assertNotNull(mixed $actual, string $message = ''): void
    {
        self::recordAssertion();
        if ($actual === null) {
            throw new AssertionException(
                $message ?: "Failed asserting that value is not null."
            );
        }
    }

    public static function assertEmpty(mixed $actual, string $message = ''): void
    {
        self::recordAssertion();
        if (!empty($actual)) {
            $valStr = var_export($actual, true);
            throw new AssertionException(
                $message ?: "Failed asserting that {$valStr} is empty."
            );
        }
    }

    public static function assertNotEmpty(mixed $actual, string $message = ''): void
    {
        self::recordAssertion();
        if (empty($actual)) {
            $valStr = var_export($actual, true);
            throw new AssertionException(
                $message ?: "Failed asserting that {$valStr} is not empty."
            );
        }
    }

    public static function assertStringContains(string $needle, string $haystack, string $message = ''): void
    {
        self::recordAssertion();
        if (!str_contains($haystack, $needle)) {
            throw new AssertionException(
                $message ?: "Failed asserting that string '{$haystack}' contains '{$needle}'."
            );
        }
    }

    public static function assertStringNotContains(string $needle, string $haystack, string $message = ''): void
    {
        self::recordAssertion();
        if (str_contains($haystack, $needle)) {
            throw new AssertionException(
                $message ?: "Failed asserting that string '{$haystack}' does not contain '{$needle}'."
            );
        }
    }

    public static function assertStringStartsWith(string $prefix, string $haystack, string $message = ''): void
    {
        self::recordAssertion();
        if (!str_starts_with($haystack, $prefix)) {
            throw new AssertionException(
                $message ?: "Failed asserting that string '{$haystack}' starts with '{$prefix}'."
            );
        }
    }

    public static function assertStringEndsWith(string $suffix, string $haystack, string $message = ''): void
    {
        self::recordAssertion();
        if (!str_ends_with($haystack, $suffix)) {
            throw new AssertionException(
                $message ?: "Failed asserting that string '{$haystack}' ends with '{$suffix}'."
            );
        }
    }

    public static function assertMatchesRegex(string $pattern, string $string, string $message = ''): void
    {
        self::recordAssertion();
        if (@preg_match($pattern, $string) !== 1) {
            throw new AssertionException(
                $message ?: "Failed asserting that '{$string}' matches pattern '{$pattern}'."
            );
        }
    }

    public static function assertDoesNotMatchRegex(string $pattern, string $string, string $message = ''): void
    {
        self::recordAssertion();
        if (@preg_match($pattern, $string) === 1) {
            throw new AssertionException(
                $message ?: "Failed asserting that '{$string}' does not match pattern '{$pattern}'."
            );
        }
    }

    public static function assertThrows(string $expectedClass, callable $callback, ?string $msgContains = null, string $message = ''): Throwable
    {
        self::recordAssertion();
        try {
            $callback();
        } catch (Throwable $e) {
            if ($e instanceof $expectedClass || is_a($e, $expectedClass)) {
                if ($msgContains !== null && !str_contains($e->getMessage(), $msgContains)) {
                    throw new AssertionException(
                        $message ?: "Caught expected exception '{$expectedClass}', but message '{$e->getMessage()}' does not contain '{$msgContains}'."
                    );
                }
                return $e;
            }
            $caughtClass = get_class($e);
            throw new AssertionException(
                $message ?: "Expected exception of type '{$expectedClass}', but caught '{$caughtClass}' with message: {$e->getMessage()}."
            );
        }
        throw new AssertionException(
            $message ?: "Expected exception of type '{$expectedClass}' was not thrown."
        );
    }

    public static function assertDoesNotThrow(callable $callback, string $message = ''): mixed
    {
        self::recordAssertion();
        try {
            return $callback();
        } catch (Throwable $e) {
            $caughtClass = get_class($e);
            throw new AssertionException(
                $message ?: "Failed asserting that callable does not throw an exception. Caught '{$caughtClass}': {$e->getMessage()}."
            );
        }
    }

    public static function assertCount(int $expectedCount, Countable|array $countable, string $message = ''): void
    {
        self::recordAssertion();
        $actualCount = count($countable);
        if ($actualCount !== $expectedCount) {
            throw new AssertionException(
                $message ?: "Failed asserting that actual count {$actualCount} matches expected {$expectedCount}."
            );
        }
    }

    public static function assertContains(mixed $needle, iterable $haystack, string $message = ''): void
    {
        self::recordAssertion();
        $found = false;
        foreach ($haystack as $item) {
            if ($item === $needle || $item == $needle) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $needleStr = var_export($needle, true);
            throw new AssertionException(
                $message ?: "Failed asserting that iterable contains {$needleStr}."
            );
        }
    }

    public static function assertNotContains(mixed $needle, iterable $haystack, string $message = ''): void
    {
        self::recordAssertion();
        $found = false;
        foreach ($haystack as $item) {
            if ($item === $needle || $item == $needle) {
                $found = true;
                break;
            }
        }
        if ($found) {
            $needleStr = var_export($needle, true);
            throw new AssertionException(
                $message ?: "Failed asserting that iterable does not contain {$needleStr}."
            );
        }
    }

    public static function assertArrayHasKey(string|int $key, array|ArrayAccess $array, string $message = ''): void
    {
        self::recordAssertion();
        $hasKey = is_array($array) ? array_key_exists($key, $array) : isset($array[$key]);
        if (!$hasKey) {
            throw new AssertionException(
                $message ?: "Failed asserting that array contains key '{$key}'."
            );
        }
    }

    public static function assertArrayNotHasKey(string|int $key, array|ArrayAccess $array, string $message = ''): void
    {
        self::recordAssertion();
        $hasKey = is_array($array) ? array_key_exists($key, $array) : isset($array[$key]);
        if ($hasKey) {
            throw new AssertionException(
                $message ?: "Failed asserting that array does not contain key '{$key}'."
            );
        }
    }

    public static function assertArraySubset(array $subset, array $array, bool $strict = false, string $message = ''): void
    {
        self::recordAssertion();
        if (!self::isSubset($subset, $array, $strict)) {
            $diff = self::generateDiff($subset, $array);
            throw new AssertionException(
                ($message ? $message . "\n" : '') . "Failed asserting that array is a subset of the target array.",
                $diff
            );
        }
    }

    public static function assertGreaterThan(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::recordAssertion();
        if (!($actual > $expected)) {
            $actStr = var_export($actual, true);
            $expStr = var_export($expected, true);
            throw new AssertionException(
                $message ?: "Failed asserting that {$actStr} is greater than {$expStr}."
            );
        }
    }

    public static function assertGreaterThanOrEqual(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::recordAssertion();
        if (!($actual >= $expected)) {
            $actStr = var_export($actual, true);
            $expStr = var_export($expected, true);
            throw new AssertionException(
                $message ?: "Failed asserting that {$actStr} is greater than or equal to {$expStr}."
            );
        }
    }

    public static function assertLessThan(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::recordAssertion();
        if (!($actual < $expected)) {
            $actStr = var_export($actual, true);
            $expStr = var_export($expected, true);
            throw new AssertionException(
                $message ?: "Failed asserting that {$actStr} is less than {$expStr}."
            );
        }
    }

    public static function assertLessThanOrEqual(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::recordAssertion();
        if (!($actual <= $expected)) {
            $actStr = var_export($actual, true);
            $expStr = var_export($expected, true);
            throw new AssertionException(
                $message ?: "Failed asserting that {$actStr} is less than or equal to {$expStr}."
            );
        }
    }

    public static function assertJson(string $jsonString, string $message = ''): void
    {
        self::recordAssertion();
        json_decode($jsonString);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new AssertionException(
                $message ?: "Failed asserting that string is valid JSON: " . json_last_error_msg()
            );
        }
    }

    public static function assertJsonEquals(array|string $expected, string $actualJson, string $message = ''): void
    {
        self::recordAssertion();
        $decodedActual = json_decode($actualJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new AssertionException("Actual string is not valid JSON: " . json_last_error_msg());
        }
        $decodedExpected = is_string($expected) ? json_decode($expected, true) : $expected;
        if (json_last_error() !== JSON_ERROR_NONE && is_string($expected)) {
            throw new AssertionException("Expected string is not valid JSON: " . json_last_error_msg());
        }

        if (!self::arraysEqual($decodedExpected, $decodedActual)) {
            $diff = self::generateDiff($decodedExpected, $decodedActual);
            throw new AssertionException(
                ($message ? $message . "\n" : '') . "Failed asserting that JSON payloads match.",
                $diff
            );
        }
    }

    public static function assertJsonContains(array $expectedSubset, string $actualJson, string $message = ''): void
    {
        self::recordAssertion();
        $decodedActual = json_decode($actualJson, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decodedActual)) {
            throw new AssertionException("Actual string is not valid JSON object: " . json_last_error_msg());
        }

        if (!self::isSubset($expectedSubset, $decodedActual, false)) {
            $diff = self::generateDiff($expectedSubset, $decodedActual);
            throw new AssertionException(
                ($message ? $message . "\n" : '') . "Failed asserting that JSON contains expected subset.",
                $diff
            );
        }
    }

    public static function assertStatusCode(int $expectedStatus, int $actualStatus, string $message = ''): void
    {
        self::recordAssertion();
        if ($expectedStatus !== $actualStatus) {
            throw new AssertionException(
                $message ?: "Failed asserting that HTTP status code {$actualStatus} matches expected {$expectedStatus}."
            );
        }
    }

    public static function assertDatabaseHas(string $table, array $criteria, ?PDO $pdo = null, string $message = ''): void
    {
        self::recordAssertion();
        $pdo = $pdo ?? DatabaseSandbox::getActivePdo();
        if ($pdo === null) {
            throw new AssertionException("Cannot verify database: No active PDO instance found.");
        }

        $conditions = [];
        $values = [];
        foreach ($criteria as $col => $val) {
            $conditions[] = "{$col} = ?";
            $values[] = $val;
        }
        $whereSql = count($conditions) > 0 ? " WHERE " . implode(' AND ', $conditions) : '';
        $sql = "SELECT COUNT(*) AS total FROM {$table}{$whereSql}";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        $count = (int) $stmt->fetchColumn();

        if ($count === 0) {
            $criteriaStr = json_encode($criteria);
            throw new AssertionException(
                $message ?: "Failed asserting that table '{$table}' has a record matching criteria: {$criteriaStr}."
            );
        }
    }

    public static function assertDatabaseMissing(string $table, array $criteria, ?PDO $pdo = null, string $message = ''): void
    {
        self::recordAssertion();
        $pdo = $pdo ?? DatabaseSandbox::getActivePdo();
        if ($pdo === null) {
            throw new AssertionException("Cannot verify database: No active PDO instance found.");
        }

        $conditions = [];
        $values = [];
        foreach ($criteria as $col => $val) {
            $conditions[] = "{$col} = ?";
            $values[] = $val;
        }
        $whereSql = count($conditions) > 0 ? " WHERE " . implode(' AND ', $conditions) : '';
        $sql = "SELECT COUNT(*) AS total FROM {$table}{$whereSql}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        $count = (int) $stmt->fetchColumn();

        if ($count > 0) {
            $criteriaStr = json_encode($criteria);
            throw new AssertionException(
                $message ?: "Failed asserting that table '{$table}' is missing a record matching criteria: {$criteriaStr}. Found {$count} matching rows."
            );
        }
    }

    public static function fail(string $message = 'Test assertion failed'): never
    {
        self::recordAssertion();
        throw new AssertionException($message);
    }

    public static function markTestSkipped(string $message = 'Test skipped'): never
    {
        throw new TestSkippedException($message);
    }

    public static function generateDiff(mixed $expected, mixed $actual): string
    {
        $expStr = self::formatForDiff($expected);
        $actStr = self::formatForDiff($actual);

        $expLines = explode("\n", $expStr);
        $actLines = explode("\n", $actStr);

        $out = "--- Expected\n+++ Actual\n@@ @@\n";
        foreach ($expLines as $line) {
            $out .= "- {$line}\n";
        }
        foreach ($actLines as $line) {
            $out .= "+ {$line}\n";
        }

        return rtrim($out);
    }

    private static function formatForDiff(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return var_export($value, true);
        }
        $encoded = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $encoded !== false ? $encoded : var_export($value, true);
    }

    private static function arraysEqual(array $a, array $b): bool
    {
        if (count($a) !== count($b)) {
            return false;
        }
        foreach ($a as $key => $val) {
            if (!array_key_exists($key, $b)) {
                return false;
            }
            if (is_array($val) && is_array($b[$key])) {
                if (!self::arraysEqual($val, $b[$key])) {
                    return false;
                }
            } elseif ($val != $b[$key]) {
                return false;
            }
        }
        return true;
    }

    private static function isSubset(array $subset, array $array, bool $strict = false): bool
    {
        foreach ($subset as $key => $val) {
            if (!array_key_exists($key, $array)) {
                return false;
            }
            if (is_array($val) && is_array($array[$key])) {
                if (!self::isSubset($val, $array[$key], $strict)) {
                    return false;
                }
            } else {
                if ($strict ? ($val !== $array[$key]) : ($val != $array[$key])) {
                    return false;
                }
            }
        }
        return true;
    }
}
