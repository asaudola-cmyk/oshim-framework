<?php
declare(strict_types=1);

namespace Oshim\Testing;

use Oshim\Testing\Exceptions\AssertionFailedException;
use Oshim\Database\DB;
use Countable;
use Traversable;
use ArrayAccess;
use Throwable;

/**
 * Complete zero-dependency Test Assertion Library.
 */
class Assert
{
    private static int $assertionCount = 0;

    public static function resetCount(): void
    {
        self::$assertionCount = 0;
    }

    public static function getCount(): int
    {
        return self::$assertionCount;
    }

    public static function incrementCount(): void
    {
        self::$assertionCount++;
    }

    public static function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::incrementCount();
        if ($expected != $actual) {
            $diff = sprintf("Expected: %s\nActual:   %s", self::export($expected), self::export($actual));
            $msg = $message !== '' ? $message : "Failed asserting that two values are equal.";
            throw new AssertionFailedException($msg, $diff);
        }
    }

    public static function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::incrementCount();
        if ($expected !== $actual) {
            $diff = sprintf("Expected (identical): %s\nActual:               %s", self::export($expected), self::export($actual));
            $msg = $message !== '' ? $message : "Failed asserting that two values are identical.";
            throw new AssertionFailedException($msg, $diff);
        }
    }

    public static function assertIsArray(mixed $actual, string $message = ''): void
    {
        self::incrementCount();
        if (!is_array($actual)) {
            $msg = $message !== '' ? $message : "Failed asserting that " . gettype($actual) . " is an array.";
            throw new AssertionFailedException($msg);
        }
    }

    public static function assertIsString(mixed $actual, string $message = ''): void
    {
        self::incrementCount();
        if (!is_string($actual)) {
            $msg = $message !== '' ? $message : "Failed asserting that " . gettype($actual) . " is a string.";
            throw new AssertionFailedException($msg);
        }
    }

    public static function assertIsInt(mixed $actual, string $message = ''): void
    {
        self::incrementCount();
        if (!is_int($actual)) {
            $msg = $message !== '' ? $message : "Failed asserting that " . gettype($actual) . " is an integer.";
            throw new AssertionFailedException($msg);
        }
    }

    public static function assertIsBool(mixed $actual, string $message = ''): void
    {
        self::incrementCount();
        if (!is_bool($actual)) {
            $msg = $message !== '' ? $message : "Failed asserting that " . gettype($actual) . " is a boolean.";
            throw new AssertionFailedException($msg);
        }
    }

    public static function assertIsFloat(mixed $actual, string $message = ''): void
    {
        self::incrementCount();
        if (!is_float($actual) && !is_numeric($actual)) {
            $msg = $message !== '' ? $message : "Failed asserting that " . gettype($actual) . " is a float.";
            throw new AssertionFailedException($msg);
        }
    }

    public static function assertNotEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::incrementCount();
        if ($expected == $actual) {
            $diff = sprintf("Value: %s", self::export($actual));
            $msg = $message !== '' ? $message : "Failed asserting that two values are not equal.";
            throw new AssertionFailedException($msg, $diff);
        }
    }

    public static function assertNotSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::incrementCount();
        if ($expected === $actual) {
            $diff = sprintf("Value: %s", self::export($actual));
            $msg = $message !== '' ? $message : "Failed asserting that two values are not identical.";
            throw new AssertionFailedException($msg, $diff);
        }
    }

    public static function assertTrue(mixed $condition, string $message = ''): void
    {
        self::incrementCount();
        if ($condition !== true) {
            $diff = sprintf("Actual value: %s", self::export($condition));
            $msg = $message !== '' ? $message : "Failed asserting that value is true.";
            throw new AssertionFailedException($msg, $diff);
        }
    }

    public static function assertFalse(mixed $condition, string $message = ''): void
    {
        self::incrementCount();
        if ($condition !== false) {
            $diff = sprintf("Actual value: %s", self::export($condition));
            $msg = $message !== '' ? $message : "Failed asserting that value is false.";
            throw new AssertionFailedException($msg, $diff);
        }
    }

    public static function assertGreaterThan(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::incrementCount();
        if (!($actual > $expected)) {
            $diff = sprintf("Expected > %s, but actual value was %s", self::export($expected), self::export($actual));
            $msg = $message !== '' ? $message : "Failed asserting that value is greater than expected.";
            throw new AssertionFailedException($msg, $diff);
        }
    }

    public static function assertGreaterThanOrEqual(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::incrementCount();
        if (!($actual >= $expected)) {
            $diff = sprintf("Expected >= %s, but actual value was %s", self::export($expected), self::export($actual));
            $msg = $message !== '' ? $message : "Failed asserting that value is greater than or equal to expected.";
            throw new AssertionFailedException($msg, $diff);
        }
    }

    public static function assertLessThan(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::incrementCount();
        if (!($actual < $expected)) {
            $diff = sprintf("Expected < %s, but actual value was %s", self::export($expected), self::export($actual));
            $msg = $message !== '' ? $message : "Failed asserting that value is less than expected.";
            throw new AssertionFailedException($msg, $diff);
        }
    }

    public static function assertLessThanOrEqual(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::incrementCount();
        if (!($actual <= $expected)) {
            $diff = sprintf("Expected <= %s, but actual value was %s", self::export($expected), self::export($actual));
            $msg = $message !== '' ? $message : "Failed asserting that value is less than or equal to expected.";
            throw new AssertionFailedException($msg, $diff);
        }
    }

    public static function assertNull(mixed $actual, string $message = ''): void
    {
        self::incrementCount();
        if ($actual !== null) {
            $diff = sprintf("Actual value: %s", self::export($actual));
            $msg = $message !== '' ? $message : "Failed asserting that value is null.";
            throw new AssertionFailedException($msg, $diff);
        }
    }

    public static function assertNotNull(mixed $actual, string $message = ''): void
    {
        self::incrementCount();
        if ($actual === null) {
            $msg = $message !== '' ? $message : "Failed asserting that value is not null.";
            throw new AssertionFailedException($msg);
        }
    }

    public static function assertEmpty(mixed $actual, string $message = ''): void
    {
        self::incrementCount();
        if (!empty($actual)) {
            $diff = sprintf("Actual value: %s", self::export($actual));
            $msg = $message !== '' ? $message : "Failed asserting that value is empty.";
            throw new AssertionFailedException($msg, $diff);
        }
    }

    public static function assertNotEmpty(mixed $actual, string $message = ''): void
    {
        self::incrementCount();
        if (empty($actual)) {
            $msg = $message !== '' ? $message : "Failed asserting that value is not empty.";
            throw new AssertionFailedException($msg);
        }
    }

    public static function assertCount(int $expectedCount, mixed $countable, string $message = ''): void
    {
        self::incrementCount();
        $actualCount = is_countable($countable) ? count($countable) : (is_array($countable) ? count($countable) : 0);

        if ($actualCount !== $expectedCount) {
            $diff = sprintf("Expected count: %d\nActual count:   %d", $expectedCount, $actualCount);
            $msg = $message !== '' ? $message : "Failed asserting that count matches expected.";
            throw new AssertionFailedException($msg, $diff);
        }
    }

    public static function assertContains(mixed $needle, mixed $haystack, string $message = ''): void
    {
        self::incrementCount();
        $found = false;

        if (is_array($haystack) || $haystack instanceof Traversable) {
            foreach ($haystack as $item) {
                if ($item === $needle) {
                    $found = true;
                    break;
                }
            }
        }

        if (!$found) {
            $diff = sprintf("Needle:   %s\nHaystack: %s", self::export($needle), self::export($haystack));
            $msg = $message !== '' ? $message : "Failed asserting that haystack contains needle.";
            throw new AssertionFailedException($msg, $diff);
        }
    }

    public static function assertNotContains(mixed $needle, mixed $haystack, string $message = ''): void
    {
        self::incrementCount();
        $found = false;

        if (is_array($haystack) || $haystack instanceof Traversable) {
            foreach ($haystack as $item) {
                if ($item === $needle) {
                    $found = true;
                    break;
                }
            }
        }

        if ($found) {
            $diff = sprintf("Found needle: %s", self::export($needle));
            $msg = $message !== '' ? $message : "Failed asserting that haystack does not contain needle.";
            throw new AssertionFailedException($msg, $diff);
        }
    }

    public static function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
    {
        self::incrementCount();
        if (!str_contains($haystack, $needle)) {
            $diff = sprintf("Needle:   %s\nHaystack: %s", self::export($needle), self::export($haystack));
            $msg = $message !== '' ? $message : "Failed asserting that string contains substring.";
            throw new AssertionFailedException($msg, $diff);
        }
    }

    public static function assertStringNotContainsString(string $needle, string $haystack, string $message = ''): void
    {
        self::incrementCount();
        if (str_contains($haystack, $needle)) {
            $diff = sprintf("Found needle: %s in %s", self::export($needle), self::export($haystack));
            $msg = $message !== '' ? $message : "Failed asserting that string does not contain substring.";
            throw new AssertionFailedException($msg, $diff);
        }
    }

    public static function assertStringStartsWith(string $prefix, string $string, string $message = ''): void
    {
        self::incrementCount();
        if (!str_starts_with($string, $prefix)) {
            $diff = sprintf("Prefix: %s\nString: %s", self::export($prefix), self::export($string));
            $msg = $message !== '' ? $message : "Failed asserting that string starts with prefix.";
            throw new AssertionFailedException($msg, $diff);
        }
    }

    public static function assertStringEndsWith(string $suffix, string $string, string $message = ''): void
    {
        self::incrementCount();
        if (!str_ends_with($string, $suffix)) {
            $diff = sprintf("Suffix: %s\nString: %s", self::export($suffix), self::export($string));
            $msg = $message !== '' ? $message : "Failed asserting that string ends with suffix.";
            throw new AssertionFailedException($msg, $diff);
        }
    }

    public static function assertMatchesRegularExpression(string $pattern, string $string, string $message = ''): void
    {
        self::incrementCount();
        if (!preg_match($pattern, $string)) {
            $diff = sprintf("Pattern: %s\nString:  %s", $pattern, self::export($string));
            $msg = $message !== '' ? $message : "Failed asserting that string matches regular expression.";
            throw new AssertionFailedException($msg, $diff);
        }
    }

    public static function assertThrows(callable $callback, string $expectedClass = Throwable::class, ?string $expectedMessage = null): Throwable
    {
        self::incrementCount();
        try {
            $callback();
        } catch (Throwable $e) {
            if (!$e instanceof $expectedClass) {
                $diff = sprintf("Expected exception: %s\nActual exception:   %s", $expectedClass, get_class($e));
                throw new AssertionFailedException("Failed asserting that expected exception was thrown.", $diff, 0, $e);
            }

            if ($expectedMessage !== null && !str_contains($e->getMessage(), $expectedMessage)) {
                $diff = sprintf("Expected message: %s\nActual message:   %s", $expectedMessage, $e->getMessage());
                throw new AssertionFailedException("Failed asserting that exception message matches expected substring.", $diff, 0, $e);
            }

            return $e;
        }

        throw new AssertionFailedException("Failed asserting that exception [{$expectedClass}] was thrown.");
    }

    public static function assertDatabaseHas(string $table, array $criteria, string $message = ''): void
    {
        self::incrementCount();
        $query = DB::table($table);
        foreach ($criteria as $k => $v) {
            $query->where($k, '=', $v);
        }

        if (!$query->exists()) {
            $diff = sprintf("Table: %s\nCriteria: %s", $table, self::export($criteria));
            $msg = $message !== '' ? $message : "Failed asserting that database table [{$table}] contains matching record.";
            throw new AssertionFailedException($msg, $diff);
        }
    }

    public static function assertDatabaseMissing(string $table, array $criteria, string $message = ''): void
    {
        self::incrementCount();
        $query = DB::table($table);
        foreach ($criteria as $k => $v) {
            $query->where($k, '=', $v);
        }

        if ($query->exists()) {
            $diff = sprintf("Table: %s\nCriteria: %s", $table, self::export($criteria));
            $msg = $message !== '' ? $message : "Failed asserting that database table [{$table}] does not contain matching record.";
            throw new AssertionFailedException($msg, $diff);
        }
    }

    public static function assertDatabaseCount(string $table, int $expectedCount, string $message = ''): void
    {
        self::incrementCount();
        $actualCount = DB::table($table)->count();

        if ($actualCount !== $expectedCount) {
            $diff = sprintf("Table: %s\nExpected count: %d\nActual count:   %d", $table, $expectedCount, $actualCount);
            $msg = $message !== '' ? $message : "Failed asserting that table [{$table}] has {$expectedCount} records.";
            throw new AssertionFailedException($msg, $diff);
        }
    }

    public static function assertArrayHasKey(string|int $key, mixed $array, string $message = ''): void
    {
        self::incrementCount();
        $exists = is_array($array) ? array_key_exists($key, $array) : ($array instanceof ArrayAccess ? isset($array[$key]) : false);

        if (!$exists) {
            $diff = sprintf("Key [%s] not found in array: %s", (string)$key, self::export($array));
            $msg = $message !== '' ? $message : "Failed asserting that array has key [{$key}].";
            throw new AssertionFailedException($msg, $diff);
        }
    }

    public static function assertInstanceOf(string $expectedClass, mixed $actual, string $message = ''): void
    {
        self::incrementCount();
        if (!$actual instanceof $expectedClass) {
            $actualType = is_object($actual) ? get_class($actual) : gettype($actual);
            $diff = sprintf("Expected class: %s\nActual type:    %s", $expectedClass, $actualType);
            $msg = $message !== '' ? $message : "Failed asserting that object is an instance of [{$expectedClass}].";
            throw new AssertionFailedException($msg, $diff);
        }
    }

    private static function export(mixed $value): string
    {
        if (is_string($value)) {
            return '"' . $value . '"';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_null($value)) {
            return 'null';
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: 'array';
        }
        if (is_object($value)) {
            return get_class($value) . ' ' . (json_encode($value) ?: '');
        }
        return (string)$value;
    }
}
