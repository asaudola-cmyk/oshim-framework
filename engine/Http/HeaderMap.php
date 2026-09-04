<?php
declare(strict_types=1);

namespace Oshim\Http;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use ArrayIterator;
use Traversable;

/**
 * Case-insensitive, multi-value HTTP header collection.
 */
class HeaderMap implements ArrayAccess, Countable, IteratorAggregate
{
    /**
     * Internal storage: lowercase_key => ['name' => 'Original-Name', 'values' => list<string>]
     * @var array<string, array{name: string, values: list<string>}>
     */
    protected array $headers = [];

    public function __construct(array $headers = [])
    {
        foreach ($headers as $key => $values) {
            $this->set((string)$key, $values);
        }
    }

    /**
     * Check if a header exists.
     */
    public function has(string $key): bool
    {
        return isset($this->headers[strtolower($key)]);
    }

    /**
     * Retrieve first header value or default.
     */
    public function get(string $key, ?string $default = null): ?string
    {
        $normalized = strtolower($key);
        if (!isset($this->headers[$normalized]) || empty($this->headers[$normalized]['values'])) {
            return $default;
        }

        return $this->headers[$normalized]['values'][0];
    }

    /**
     * Retrieve all values for a header as an array.
     * @return list<string>
     */
    public function allValues(string $key): array
    {
        $normalized = strtolower($key);
        return $this->headers[$normalized]['values'] ?? [];
    }

    /**
     * Set/overwrite a header.
     * @param string|list<string> $values
     */
    public function set(string $key, string|array $values): static
    {
        $normalized = strtolower($key);
        $valuesArray = is_array($values) ? array_map('strval', $values) : [(string)$values];

        $this->headers[$normalized] = [
            'name'   => $key,
            'values' => array_values($valuesArray),
        ];

        return $this;
    }

    /**
     * Append a value to a header.
     */
    public function add(string $key, string $value): static
    {
        $normalized = strtolower($key);
        if (!isset($this->headers[$normalized])) {
            $this->set($key, $value);
        } else {
            $this->headers[$normalized]['values'][] = (string)$value;
        }

        return $this;
    }

    /**
     * Remove a header.
     */
    public function remove(string $key): static
    {
        unset($this->headers[strtolower($key)]);
        return $this;
    }

    /**
     * Returns raw mapping of original header names to array of values.
     * @return array<string, list<string>>
     */
    public function all(): array
    {
        $result = [];
        foreach ($this->headers as $header) {
            $result[$header['name']] = $header['values'];
        }
        return $result;
    }

    /**
     * Returns flattened mapping of header names to comma-separated string values.
     * @return array<string, string>
     */
    public function allFlattened(): array
    {
        $result = [];
        foreach ($this->headers as $header) {
            $result[$header['name']] = implode(', ', $header['values']);
        }
        return $result;
    }

    public function count(): int
    {
        return count($this->headers);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->allFlattened());
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string)$offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string)$offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set((string)$offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->remove((string)$offset);
    }
}
