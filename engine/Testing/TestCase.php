<?php
declare(strict_types=1);

namespace Oshim\Testing;

use Oshim\Bootstrap;
use Oshim\Container\Container;
use Oshim\Http\Request;
use Oshim\Http\Router\Router;
use Oshim\Database\ConnectionManager;
use Oshim\Database\Migrations\Migrator;
use Throwable;

/**
 * Base TestCase for all OSHIM framework and application tests.
 */
abstract class TestCase
{
    protected ?Container $app = null;
    protected ?object $authenticatedUser = null;

    public function setUp(): void
    {
        $this->app = Bootstrap::boot();
    }

    public function tearDown(): void
    {
        $this->authenticatedUser = null;

        if (class_exists(\Oshim\Ai\Tokenizer\GgufTokenizer::class)) {
            \Oshim\Ai\Tokenizer\GgufTokenizer::reset();
        }

        if (class_exists(\Oshim\Async\EventLoop::class)) {
            \Oshim\Async\EventLoop::reset();
        }

        if (class_exists(\Oshim\Async\FiberScheduler::class)) {
            \Oshim\Async\FiberScheduler::setInstance(null);
        }
    }

    public function actingAs(object $user): static
    {
        $this->authenticatedUser = $user;
        return $this;
    }

    // --- HTTP Testing Helpers ---
    public function get(string $uri, array $headers = []): TestResponse
    {
        return $this->call('GET', $uri, [], [], [], $headers);
    }

    public function post(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('POST', $uri, $data, [], [], $headers);
    }

    public function put(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('PUT', $uri, $data, [], [], $headers);
    }

    public function delete(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('DELETE', $uri, $data, [], [], $headers);
    }

    public function json(string $method, string $uri, array $data = [], array $headers = []): TestResponse
    {
        $headers['Content-Type'] = 'application/json';
        $headers['Accept'] = 'application/json';
        $content = json_encode($data, JSON_UNESCAPED_SLASHES);

        return $this->call($method, $uri, [], [], [], $headers, $content ?: '');
    }

    public function call(
        string $method,
        string $uri,
        array $parameters = [],
        array $cookies = [],
        array $files = [],
        array $headers = [],
        ?string $content = null
    ): TestResponse {
        $server = [];
        foreach ($headers as $k => $v) {
            $server['HTTP_' . str_replace('-', '_', strtoupper($k))] = $v;
        }

        $request = Request::create($method, $uri, $parameters, $cookies, $files, $server, $content);

        if ($this->authenticatedUser !== null) {
            $request->setUser($this->authenticatedUser);
        }

        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $response = $router->dispatch($request);

        return new TestResponse($response);
    }

    // --- Database Testing Helpers ---
    public function useInMemoryDatabase(): void
    {
        ConnectionManager::getInstance()->addConnection([
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ], 'default');
    }

    public function migrateDatabase(): void
    {
        /** @var Migrator $migrator */
        $migrator = $this->app->make(Migrator::class);
        $migrator->run();
    }

    // --- Assertion Proxies ---
    public function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        Assert::assertEquals($expected, $actual, $message);
    }

    public function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        Assert::assertSame($expected, $actual, $message);
    }

    public function assertIsArray(mixed $actual, string $message = ''): void
    {
        Assert::assertIsArray($actual, $message);
    }

    public function assertIsString(mixed $actual, string $message = ''): void
    {
        Assert::assertIsString($actual, $message);
    }

    public function assertIsInt(mixed $actual, string $message = ''): void
    {
        Assert::assertIsInt($actual, $message);
    }

    public function assertIsBool(mixed $actual, string $message = ''): void
    {
        Assert::assertIsBool($actual, $message);
    }

    public function assertIsFloat(mixed $actual, string $message = ''): void
    {
        Assert::assertIsFloat($actual, $message);
    }

    public function assertNotEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        Assert::assertNotEquals($expected, $actual, $message);
    }

    public function assertNotSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        Assert::assertNotSame($expected, $actual, $message);
    }

    public function assertTrue(mixed $condition, string $message = ''): void
    {
        Assert::assertTrue($condition, $message);
    }

    public function assertFalse(mixed $condition, string $message = ''): void
    {
        Assert::assertFalse($condition, $message);
    }

    public function assertGreaterThan(mixed $expected, mixed $actual, string $message = ''): void
    {
        Assert::assertGreaterThan($expected, $actual, $message);
    }

    public function assertGreaterThanOrEqual(mixed $expected, mixed $actual, string $message = ''): void
    {
        Assert::assertGreaterThanOrEqual($expected, $actual, $message);
    }

    public function assertLessThan(mixed $expected, mixed $actual, string $message = ''): void
    {
        Assert::assertLessThan($expected, $actual, $message);
    }

    public function assertLessThanOrEqual(mixed $expected, mixed $actual, string $message = ''): void
    {
        Assert::assertLessThanOrEqual($expected, $actual, $message);
    }

    public function assertNull(mixed $actual, string $message = ''): void
    {
        Assert::assertNull($actual, $message);
    }

    public function assertNotNull(mixed $actual, string $message = ''): void
    {
        Assert::assertNotNull($actual, $message);
    }

    public function assertEmpty(mixed $actual, string $message = ''): void
    {
        Assert::assertEmpty($actual, $message);
    }

    public function assertNotEmpty(mixed $actual, string $message = ''): void
    {
        Assert::assertNotEmpty($actual, $message);
    }

    public function assertCount(int $expectedCount, mixed $countable, string $message = ''): void
    {
        Assert::assertCount($expectedCount, $countable, $message);
    }

    public function assertContains(mixed $needle, mixed $haystack, string $message = ''): void
    {
        Assert::assertContains($needle, $haystack, $message);
    }

    public function assertNotContains(mixed $needle, mixed $haystack, string $message = ''): void
    {
        Assert::assertNotContains($needle, $haystack, $message);
    }

    public function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
    {
        Assert::assertStringContainsString($needle, $haystack, $message);
    }

    public function assertStringNotContainsString(string $needle, string $haystack, string $message = ''): void
    {
        Assert::assertStringNotContainsString($needle, $haystack, $message);
    }

    public function assertStringStartsWith(string $prefix, string $string, string $message = ''): void
    {
        Assert::assertStringStartsWith($prefix, $string, $message);
    }

    public function assertStringEndsWith(string $suffix, string $string, string $message = ''): void
    {
        Assert::assertStringEndsWith($suffix, $string, $message);
    }

    public function assertMatchesRegularExpression(string $pattern, string $string, string $message = ''): void
    {
        Assert::assertMatchesRegularExpression($pattern, $string, $message);
    }

    public function assertThrows(callable $callback, string $expectedClass = Throwable::class, ?string $expectedMessage = null): Throwable
    {
        return Assert::assertThrows($callback, $expectedClass, $expectedMessage);
    }

    public function assertDatabaseHas(string $table, array $criteria, string $message = ''): void
    {
        Assert::assertDatabaseHas($table, $criteria, $message);
    }

    public function assertDatabaseMissing(string $table, array $criteria, string $message = ''): void
    {
        Assert::assertDatabaseMissing($table, $criteria, $message);
    }

    public function assertDatabaseCount(string $table, int $expectedCount, string $message = ''): void
    {
        Assert::assertDatabaseCount($table, $expectedCount, $message);
    }

    public function assertArrayHasKey(string|int $key, mixed $array, string $message = ''): void
    {
        Assert::assertArrayHasKey($key, $array, $message);
    }

    public function assertInstanceOf(string $expectedClass, mixed $actual, string $message = ''): void
    {
        Assert::assertInstanceOf($expectedClass, $actual, $message);
    }
}
