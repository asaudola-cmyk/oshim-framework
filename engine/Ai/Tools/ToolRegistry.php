<?php
declare(strict_types=1);

namespace Oshim\Ai\Tools;

use Closure;
use InvalidArgumentException;

/**
 * Registry for JSON-Schema definition of AI Agentic Tools.
 */
class ToolRegistry
{
    /**
     * @var array<string, array{
     *     name: string,
     *     description: string,
     *     parameters: array<string, mixed>,
     *     handler: Closure
     * }>
     */
    private array $tools = [];

    /**
     * Register a new tool.
     *
     * @param string $name
     * @param string $description
     * @param array<string, mixed> $parameters JSON Schema
     * @param callable $handler
     */
    public function register(string $name, string $description, array $parameters, callable $handler): self
    {
        $this->tools[$name] = [
            'name' => $name,
            'description' => $description,
            'parameters' => $parameters,
            'handler' => $handler(...),
        ];
        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    public function get(string $name): ?array
    {
        return $this->tools[$name] ?? null;
    }

    public function getAll(): array
    {
        return $this->tools;
    }

    public function execute(string $name, array $arguments = []): mixed
    {
        if (!isset($this->tools[$name])) {
            throw new InvalidArgumentException("Tool '{$name}' is not registered.");
        }

        return ($this->tools[$name]['handler'])($arguments);
    }

    public function toSchemaArray(): array
    {
        $schemas = [];
        foreach ($this->tools as $name => $tool) {
            $schemas[] = [
                'type' => 'function',
                'function' => [
                    'name' => $tool['name'],
                    'description' => $tool['description'],
                    'parameters' => $tool['parameters'],
                ],
            ];
        }
        return $schemas;
    }
}
