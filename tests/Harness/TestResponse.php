<?php
declare(strict_types=1);

namespace Oshim\Tests\Harness;

/**
 * Rich HTTP TestResponse wrapper providing fluent assertions for status, headers, JSON, HTML, and cookies.
 */
class TestResponse
{
    private mixed $rawResponse;
    private int $statusCode;
    private array $headers;
    private string $content;
    private array $cookies;
    private array $session;

    public function __construct(
        mixed $rawResponse = null,
        int $statusCode = 200,
        array $headers = [],
        string $content = '',
        array $cookies = [],
        array $session = []
    ) {
        $this->rawResponse = $rawResponse;

        if (is_object($rawResponse)) {
            // Check if it's an Oshim\Http\Response object
            if (method_exists($rawResponse, 'getStatusCode')) {
                $this->statusCode = (int)$rawResponse->getStatusCode();
            } elseif (property_exists($rawResponse, 'statusCode')) {
                $this->statusCode = (int)$rawResponse->statusCode;
            } else {
                $this->statusCode = $statusCode;
            }

            if (method_exists($rawResponse, 'getHeaders')) {
                $this->headers = (array)$rawResponse->getHeaders();
            } elseif (property_exists($rawResponse, 'headers')) {
                $this->headers = (array)$rawResponse->headers;
            } else {
                $this->headers = $headers;
            }

            if (method_exists($rawResponse, 'getContent')) {
                $this->content = (string)$rawResponse->getContent();
            } elseif (method_exists($rawResponse, 'getBody')) {
                $this->content = (string)$rawResponse->getBody();
            } elseif (property_exists($rawResponse, 'content')) {
                $this->content = (string)$rawResponse->content;
            } else {
                $this->content = $content;
            }

            if (method_exists($rawResponse, 'getCookies')) {
                $this->cookies = (array)$rawResponse->getCookies();
            } else {
                $this->cookies = $cookies;
            }

            if (method_exists($rawResponse, 'getSession')) {
                $this->session = (array)$rawResponse->getSession();
            } else {
                $this->session = $session;
            }
        } else {
            $this->statusCode = $statusCode;
            $this->headers = $headers;
            $this->content = $content;
            $this->cookies = $cookies;
            $this->session = $session;
        }
    }

    public function assertStatus(int $expected): self
    {
        Assert::recordAssertion();
        if ($this->statusCode !== $expected) {
            $snippet = substr($this->content, 0, 300);
            throw new AssertionException(
                "Expected HTTP status {$expected}, but received {$this->statusCode}.\nResponse content snippet:\n{$snippet}"
            );
        }
        return $this;
    }

    public function assertOk(): self
    {
        return $this->assertStatus(200);
    }

    public function assertCreated(): self
    {
        return $this->assertStatus(201);
    }

    public function assertAccepted(): self
    {
        return $this->assertStatus(202);
    }

    public function assertNoContent(): self
    {
        return $this->assertStatus(204);
    }

    public function assertBadRequest(): self
    {
        return $this->assertStatus(400);
    }

    public function assertUnauthorized(): self
    {
        return $this->assertStatus(401);
    }

    public function assertForbidden(): self
    {
        return $this->assertStatus(403);
    }

    public function assertNotFound(): self
    {
        return $this->assertStatus(404);
    }

    public function assertMethodNotAllowed(): self
    {
        return $this->assertStatus(405);
    }

    public function assertUnprocessable(): self
    {
        return $this->assertStatus(422);
    }

    public function assertServerError(): self
    {
        return $this->assertStatus(500);
    }

    public function assertRedirect(?string $uri = null): self
    {
        Assert::recordAssertion();
        $isRedirect = in_array($this->statusCode, [301, 302, 303, 307, 308], true);
        if (!$isRedirect) {
            throw new AssertionException("Expected HTTP redirect status (301-308), but received {$this->statusCode}.");
        }

        if ($uri !== null) {
            $location = $this->getHeader('Location') ?? $this->getHeader('location');
            if ($location === null || !str_contains($location, $uri)) {
                throw new AssertionException("Expected redirect location to contain '{$uri}', but got '{$location}'.");
            }
        }
        return $this;
    }

    public function assertHeader(string $name, ?string $value = null): self
    {
        Assert::recordAssertion();
        $headerVal = $this->getHeader($name);
        if ($headerVal === null) {
            throw new AssertionException("Expected HTTP response header '{$name}' was not present.");
        }
        if ($value !== null && $headerVal !== $value) {
            throw new AssertionException("Expected header '{$name}' to be '{$value}', but got '{$headerVal}'.");
        }
        return $this;
    }

    public function assertHeaderMissing(string $name): self
    {
        Assert::recordAssertion();
        if ($this->getHeader($name) !== null) {
            throw new AssertionException("Unexpected HTTP response header '{$name}' was present.");
        }
        return $this;
    }

    public function assertContentType(string $type): self
    {
        Assert::recordAssertion();
        $ct = $this->getHeader('Content-Type') ?? $this->getHeader('content-type') ?? '';
        if (!str_contains($ct, $type)) {
            throw new AssertionException("Expected Content-Type to contain '{$type}', but got '{$ct}'.");
        }
        return $this;
    }

    public function assertSee(string $needle, bool $escape = true): self
    {
        Assert::recordAssertion();
        $target = $escape ? htmlspecialchars($needle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $needle;
        if (!str_contains($this->content, $needle) && !str_contains($this->content, $target)) {
            throw new AssertionException("Failed asserting that response content contains string '{$needle}'.");
        }
        return $this;
    }

    public function assertDontSee(string $needle, bool $escape = true): self
    {
        Assert::recordAssertion();
        $target = $escape ? htmlspecialchars($needle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $needle;
        if (str_contains($this->content, $needle) || str_contains($this->content, $target)) {
            throw new AssertionException("Failed asserting that response content does not contain string '{$needle}'.");
        }
        return $this;
    }

    public function assertSeeHtml(string $html): self
    {
        return $this->assertSee($html, false);
    }

    public function assertJson(?array $expectedSubset = null): self
    {
        Assert::recordAssertion();
        $decoded = json_decode($this->content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw new AssertionException("Failed asserting that response content is valid JSON: " . json_last_error_msg() . "\nContent: {$this->content}");
        }

        if ($expectedSubset !== null) {
            Assert::assertArraySubset($expectedSubset, $decoded, false);
        }
        return $this;
    }

    public function assertExactJson(array $data): self
    {
        Assert::recordAssertion();
        $decoded = json_decode($this->content, true);
        if ($decoded !== $data) {
            $diff = Assert::generateDiff($data, $decoded);
            throw new AssertionException("Failed asserting that response JSON matches exact structure.", $diff);
        }
        return $this;
    }

    public function assertJsonPath(string $path, mixed $expectedValue): self
    {
        Assert::recordAssertion();
        $decoded = json_decode($this->content, true);
        if (!is_array($decoded)) {
            throw new AssertionException("Response content is not a valid JSON array or object.");
        }

        $segments = explode('.', $path);
        $current = $decoded;
        foreach ($segments as $segment) {
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
            } else {
                throw new AssertionException("JSON path '{$path}' could not be resolved at segment '{$segment}'.");
            }
        }

        if ($current !== $expectedValue && $current != $expectedValue) {
            $diff = Assert::generateDiff($expectedValue, $current);
            throw new AssertionException("JSON path '{$path}' does not match expected value.", $diff);
        }
        return $this;
    }

    public function assertJsonCount(int $count, ?string $path = null): self
    {
        Assert::recordAssertion();
        $decoded = json_decode($this->content, true);
        if (!is_array($decoded)) {
            throw new AssertionException("Response content is not a valid JSON array.");
        }

        $target = $decoded;
        if ($path !== null) {
            $segments = explode('.', $path);
            foreach ($segments as $segment) {
                if (is_array($target) && array_key_exists($segment, $target)) {
                    $target = $target[$segment];
                } else {
                    throw new AssertionException("JSON path '{$path}' could not be resolved at segment '{$segment}'.");
                }
            }
        }

        if (!is_array($target)) {
            throw new AssertionException("Value at JSON path '{$path}' is not countable.");
        }

        $actualCount = count($target);
        if ($actualCount !== $count) {
            throw new AssertionException("Expected JSON array count at '{$path}' to be {$count}, but got {$actualCount}.");
        }
        return $this;
    }

    public function json(?string $key = null): mixed
    {
        $decoded = json_decode($this->content, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        if ($key === null) {
            return $decoded;
        }

        $segments = explode('.', $key);
        $current = $decoded;
        foreach ($segments as $segment) {
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
            } else {
                return null;
            }
        }

        return $current;
    }

    public function assertCookie(string $name, ?string $value = null): self
    {
        Assert::recordAssertion();
        $cookieVal = $this->getCookie($name);
        if ($cookieVal === null) {
            throw new AssertionException("Expected cookie '{$name}' was not set in response.");
        }
        if ($value !== null && $cookieVal !== $value) {
            throw new AssertionException("Expected cookie '{$name}' value to be '{$value}', but got '{$cookieVal}'.");
        }
        return $this;
    }

    public function assertCookieMissing(string $name): self
    {
        Assert::recordAssertion();
        if ($this->getCookie($name) !== null) {
            throw new AssertionException("Unexpected cookie '{$name}' was set in response.");
        }
        return $this;
    }

    public function assertSessionHas(string $key, mixed $value = null): self
    {
        Assert::recordAssertion();
        if (!array_key_exists($key, $this->session)) {
            throw new AssertionException("Expected session key '{$key}' was not found.");
        }
        if ($value !== null && $this->session[$key] !== $value) {
            throw new AssertionException("Expected session key '{$key}' to be " . var_export($value, true) . ", got " . var_export($this->session[$key], true));
        }
        return $this;
    }

    public function assertSessionMissing(string $key): self
    {
        Assert::recordAssertion();
        if (array_key_exists($key, $this->session)) {
            throw new AssertionException("Unexpected session key '{$key}' was found in session.");
        }
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        $target = strtolower($name);
        foreach ($this->headers as $key => $val) {
            if (strtolower((string)$key) === $target) {
                return is_array($val) ? implode(', ', $val) : (string)$val;
            }
        }
        return null;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getBody(): string
    {
        return $this->content;
    }

    public function getCookies(): array
    {
        return $this->cookies;
    }

    public function getCookie(string $name): ?string
    {
        foreach ($this->cookies as $key => $cookie) {
            if ($key === $name) {
                return is_array($cookie) ? (string)($cookie['value'] ?? '') : (string)$cookie;
            }
        }
        return null;
    }

    public function getSession(): array
    {
        return $this->session;
    }

    public function getRawResponse(): mixed
    {
        return $this->rawResponse;
    }
}
