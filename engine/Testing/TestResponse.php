<?php
declare(strict_types=1);

namespace Oshim\Testing;

use Oshim\Http\Response;

/**
 * Fluent assertion wrapper around HTTP Response.
 */
class TestResponse
{
    protected Response $response;
    protected mixed $decodedJson = null;

    public function __construct(Response $response)
    {
        $this->response = $response;
    }

    public function getResponse(): Response
    {
        return $this->response;
    }

    public function getStatusCode(): int
    {
        return $this->response->getStatusCode();
    }

    public function getContent(): string
    {
        return $this->response->getContent();
    }

    public function assertStatus(int $status): static
    {
        Assert::assertEquals(
            $status,
            $this->response->getStatusCode(),
            "Expected HTTP status {$status}, received {$this->response->getStatusCode()}."
        );
        return $this;
    }

    public function assertOk(): static
    {
        return $this->assertStatus(200);
    }

    public function assertCreated(): static
    {
        return $this->assertStatus(201);
    }

    public function assertNoContent(): static
    {
        return $this->assertStatus(204);
    }

    public function assertNotFound(): static
    {
        return $this->assertStatus(404);
    }

    public function assertForbidden(): static
    {
        return $this->assertStatus(403);
    }

    public function assertUnauthorized(): static
    {
        return $this->assertStatus(401);
    }

    public function assertUnprocessable(): static
    {
        return $this->assertStatus(422);
    }

    public function assertRedirect(?string $uri = null): static
    {
        Assert::assertTrue(
            $this->response->isRedirect(),
            "Expected redirect status code, received {$this->response->getStatusCode()}."
        );

        if ($uri !== null) {
            $location = $this->response->getHeaders()->get('location');
            Assert::assertEquals($uri, $location, "Expected redirect to [{$uri}], but redirected to [{$location}].");
        }

        return $this;
    }

    public function assertSee(string $value): static
    {
        Assert::assertStringContainsString($value, $this->response->getContent());
        return $this;
    }

    public function assertDontSee(string $value): static
    {
        Assert::assertStringNotContainsString($value, $this->response->getContent());
        return $this;
    }

    public function assertHeader(string $headerName, ?string $value = null): static
    {
        Assert::assertTrue(
            $this->response->getHeaders()->has($headerName),
            "Header [{$headerName}] is missing from response."
        );

        if ($value !== null) {
            Assert::assertEquals($value, $this->response->getHeaders()->get($headerName));
        }

        return $this;
    }

    public function json(?string $key = null): mixed
    {
        if ($this->decodedJson === null) {
            $this->decodedJson = json_decode($this->response->getContent(), true);
        }

        if ($key === null) {
            return $this->decodedJson;
        }

        $array = $this->decodedJson;
        if (!is_array($array)) {
            return null;
        }

        foreach (explode('.', $key) as $segment) {
            if (is_array($array) && array_key_exists($segment, $array)) {
                $array = $array[$segment];
            } else {
                return null;
            }
        }

        return $array;
    }

    public function assertJson(?array $expected = null): static
    {
        $decoded = $this->json();
        Assert::assertTrue(is_array($decoded), "Response is not valid JSON.");

        if ($expected !== null) {
            foreach ($expected as $k => $v) {
                Assert::assertArrayHasKey($k, $decoded);
                Assert::assertEquals($v, $decoded[$k]);
            }
        }

        return $this;
    }

    public function assertJsonPath(string $path, mixed $expected): static
    {
        $actual = $this->json($path);
        Assert::assertEquals($expected, $actual, "JSON path [{$path}] did not match expected value.");
        return $this;
    }
}
