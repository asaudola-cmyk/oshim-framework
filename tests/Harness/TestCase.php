<?php
declare(strict_types=1);

namespace Oshim\Tests\Harness;

use ArrayAccess;
use Countable;
use PDO;
use Throwable;

/**
 * Base TestCase class providing lifecycle hooks, proxy assertions, and harness factories.
 */
abstract class TestCase
{
    protected string $name = '';
    protected ?DatabaseSandbox $sandboxDb = null;
    protected ?HttpTestClient $httpClient = null;

    public function __construct(string $name = '')
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public static function setUpBeforeClass(): void
    {
    }

    public static function tearDownAfterClass(): void
    {
    }

    protected function setUp(): void
    {
    }

    protected function tearDown(): void
    {
        if ($this->sandboxDb !== null) {
            $this->sandboxDb->rollBack();
        }

        if (class_exists(\Oshim\Ai\Tokenizer\GgufTokenizer::class)) {
            \Oshim\Ai\Tokenizer\GgufTokenizer::reset();
        }

        if (class_exists(\Oshim\Async\EventLoop::class)) {
            \Oshim\Async\EventLoop::reset();
        }
    }

    // --- Proxy Assertions ---

    protected function assert(bool $condition, string $message = ''): void
    {
        Assert::assertTrue($condition, $message ?: "Failed asserting that condition is true.");
    }

    protected function assertTrue(mixed $actual, string $message = ''): void
    {
        Assert::assertTrue($actual, $message);
    }

    protected function assertFalse(mixed $actual, string $message = ''): void
    {
        Assert::assertFalse($actual, $message);
    }

    protected function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        Assert::assertEquals($expected, $actual, $message);
    }

    protected function assertNotEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        Assert::assertNotEquals($expected, $actual, $message);
    }

    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        Assert::assertSame($expected, $actual, $message);
    }

    protected function assertNotSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        Assert::assertNotSame($expected, $actual, $message);
    }

    protected function assertNull(mixed $actual, string $message = ''): void
    {
        Assert::assertNull($actual, $message);
    }

    protected function assertNotNull(mixed $actual, string $message = ''): void
    {
        Assert::assertNotNull($actual, $message);
    }

    protected function assertEmpty(mixed $actual, string $message = ''): void
    {
        Assert::assertEmpty($actual, $message);
    }

    protected function assertNotEmpty(mixed $actual, string $message = ''): void
    {
        Assert::assertNotEmpty($actual, $message);
    }

    protected function assertStringContains(string $needle, string $haystack, string $message = ''): void
    {
        Assert::assertStringContains($needle, $haystack, $message);
    }

    protected function assertStringNotContains(string $needle, string $haystack, string $message = ''): void
    {
        Assert::assertStringNotContains($needle, $haystack, $message);
    }

    protected function assertStringStartsWith(string $prefix, string $haystack, string $message = ''): void
    {
        Assert::assertStringStartsWith($prefix, $haystack, $message);
    }

    protected function assertStringEndsWith(string $suffix, string $haystack, string $message = ''): void
    {
        Assert::assertStringEndsWith($suffix, $haystack, $message);
    }

    protected function assertMatchesRegex(string $pattern, string $string, string $message = ''): void
    {
        Assert::assertMatchesRegex($pattern, $string, $message);
    }

    protected function assertDoesNotMatchRegex(string $pattern, string $string, string $message = ''): void
    {
        Assert::assertDoesNotMatchRegex($pattern, $string, $message);
    }

    protected function assertThrows(string $expectedClass, callable $callback, ?string $msgContains = null, string $message = ''): Throwable
    {
        return Assert::assertThrows($expectedClass, $callback, $msgContains, $message);
    }

    protected function assertDoesNotThrow(callable $callback, string $message = ''): mixed
    {
        return Assert::assertDoesNotThrow($callback, $message);
    }

    protected function assertCount(int $expectedCount, Countable|array $countable, string $message = ''): void
    {
        Assert::assertCount($expectedCount, $countable, $message);
    }

    protected function assertContains(mixed $needle, iterable $haystack, string $message = ''): void
    {
        Assert::assertContains($needle, $haystack, $message);
    }

    protected function assertNotContains(mixed $needle, iterable $haystack, string $message = ''): void
    {
        Assert::assertNotContains($needle, $haystack, $message);
    }

    protected function assertArrayHasKey(string|int $key, array|ArrayAccess $array, string $message = ''): void
    {
        Assert::assertArrayHasKey($key, $array, $message);
    }

    protected function assertArrayNotHasKey(string|int $key, array|ArrayAccess $array, string $message = ''): void
    {
        Assert::assertArrayNotHasKey($key, $array, $message);
    }

    protected function assertArraySubset(array $subset, array $array, bool $strict = false, string $message = ''): void
    {
        Assert::assertArraySubset($subset, $array, $strict, $message);
    }

    protected function assertGreaterThan(mixed $expected, mixed $actual, string $message = ''): void
    {
        Assert::assertGreaterThan($expected, $actual, $message);
    }

    protected function assertGreaterThanOrEqual(mixed $expected, mixed $actual, string $message = ''): void
    {
        Assert::assertGreaterThanOrEqual($expected, $actual, $message);
    }

    protected function assertLessThan(mixed $expected, mixed $actual, string $message = ''): void
    {
        Assert::assertLessThan($expected, $actual, $message);
    }

    protected function assertLessThanOrEqual(mixed $expected, mixed $actual, string $message = ''): void
    {
        Assert::assertLessThanOrEqual($expected, $actual, $message);
    }

    protected function assertJson(string $jsonString, string $message = ''): void
    {
        Assert::assertJson($jsonString, $message);
    }

    protected function assertJsonEquals(array|string $expected, string $actualJson, string $message = ''): void
    {
        Assert::assertJsonEquals($expected, $actualJson, $message);
    }

    protected function assertJsonContains(array $expectedSubset, string $actualJson, string $message = ''): void
    {
        Assert::assertJsonContains($expectedSubset, $actualJson, $message);
    }

    protected function assertStatusCode(int $expectedStatus, int $actualStatus, string $message = ''): void
    {
        Assert::assertStatusCode($expectedStatus, $actualStatus, $message);
    }

    protected function assertDatabaseHas(string $table, array $criteria, ?PDO $pdo = null, string $message = ''): void
    {
        Assert::assertDatabaseHas($table, $criteria, $pdo, $message);
    }

    protected function assertDatabaseMissing(string $table, array $criteria, ?PDO $pdo = null, string $message = ''): void
    {
        Assert::assertDatabaseMissing($table, $criteria, $pdo, $message);
    }

    protected function fail(string $message = 'Test assertion failed'): never
    {
        Assert::fail($message);
    }

    protected function markTestSkipped(string $message = 'Test skipped'): never
    {
        Assert::markTestSkipped($message);
    }

    // --- Harness Factory Helpers ---

    protected function http(?string $baseUrl = null): HttpTestClient
    {
        if ($this->httpClient === null || $baseUrl !== null) {
            $this->httpClient = new HttpTestClient($baseUrl);
        }
        return $this->httpClient;
    }

    protected function db(?string $path = ':memory:'): DatabaseSandbox
    {
        if ($this->sandboxDb === null) {
            $this->sandboxDb = (new DatabaseSandbox($path ?? ':memory:'))->initialize();
        }
        return $this->sandboxDb;
    }

    protected function createMockEppRegistry(?int $port = null): MockEppRegistry
    {
        return new MockEppRegistry($port ?? 0);
    }

    protected function createMockDnsClient(): MockDnsClient
    {
        return new MockDnsClient();
    }

    protected function createVirtualizationDriver(): VirtualizationMockDriver
    {
        return new VirtualizationMockDriver();
    }
}
