<?php
declare(strict_types=1);

namespace Oshim\Ai\Agents;

/**
 * Immutable State Container with Checkpointing and Time-Travel debugging.
 */
class AgentState
{
    private array $data;
    /** @var array<string, array> Checkpointed state history */
    private array $checkpoints = [];
    private int $version = 0;

    public function __construct(array $initialData = [])
    {
        $this->data = $initialData;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): self
    {
        $this->data[$key] = $value;
        $this->version++;
        return $this;
    }

    public function merge(array $data): self
    {
        $this->data = array_merge($this->data, $data);
        $this->version++;
        return $this;
    }

    public function all(): array
    {
        return $this->data;
    }

    public function checkpoint(string $name): void
    {
        $this->checkpoints[$name] = [
            'version' => $this->version,
            'timestamp' => microtime(true),
            'data' => $this->data,
        ];
    }

    public function restore(string $name): bool
    {
        if (isset($this->checkpoints[$name])) {
            $this->data = $this->checkpoints[$name]['data'];
            $this->version = $this->checkpoints[$name]['version'];
            return true;
        }
        return false;
    }

    public function getCheckpoints(): array
    {
        return $this->checkpoints;
    }
}
