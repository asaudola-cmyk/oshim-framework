<?php
declare(strict_types=1);

namespace Oshim\Ai\Canvas;

use InvalidArgumentException;
use Throwable;

/**
 * Base implementation of NodeInterface providing state management,
 * configuration parsing, port registration, execution profiling, and validation.
 */
abstract class AbstractNode implements NodeInterface
{
    protected string $id;
    protected string $type = 'generic';
    protected string $title = 'Generic Node';
    protected float $x = 0.0;
    protected float $y = 0.0;

    /** @var array<string, mixed> */
    protected array $config = [];

    /** @var array<string, array{name: string, type: string, description: string, required: bool, default: mixed}> */
    protected array $inputPorts = [];

    /** @var array<string, array{name: string, type: string, description: string}> */
    protected array $outputPorts = [];

    /** @var array<string, mixed> */
    protected array $inputValues = [];

    /** @var list<string> */
    protected array $errors = [];

    /** @var array<string, mixed>|null */
    protected ?array $lastExecution = null;

    /**
     * @param string $id Unique node ID
     * @param string $title Human-readable title
     * @param array<string, mixed> $config Configuration parameters
     * @param array{x?: int|float, y?: int|float} $position 2D canvas coordinates
     */
    public function __construct(
        string $id,
        string $title = '',
        array $config = [],
        array $position = ['x' => 0, 'y' => 0]
    ) {
        $this->id = $id;
        if (!empty($title)) {
            $this->title = $title;
        }
        $this->config = $config;
        $this->x = (float)($position['x'] ?? 0);
        $this->y = (float)($position['y'] ?? 0);

        $this->definePorts();
    }

    /**
     * Hook to register input and output ports during construction.
     */
    abstract protected function definePorts(): void;

    /**
     * Register an input port definition.
     */
    public function registerInputPort(
        string $name,
        string $type = 'any',
        string $description = '',
        bool $required = false,
        mixed $default = null
    ): static {
        $this->inputPorts[$name] = [
            'name' => $name,
            'type' => $type,
            'description' => $description,
            'required' => $required,
            'default' => $default,
        ];
        return $this;
    }

    /**
     * Register an output port definition.
     */
    public function registerOutputPort(
        string $name,
        string $type = 'any',
        string $description = ''
    ): static {
        $this->outputPorts[$name] = [
            'name' => $name,
            'type' => $type,
            'description' => $description,
        ];
        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getPosition(): array
    {
        return ['x' => $this->x, 'y' => $this->y];
    }

    public function setPosition(int|float $x, int|float $y): static
    {
        $this->x = (float)$x;
        $this->y = (float)$y;
        return $this;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function setConfig(array $config): static
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }

    public function setConfigValue(string $key, mixed $value): static
    {
        $this->config[$key] = $value;
        return $this;
    }

    public function getInputs(): array
    {
        return $this->inputPorts;
    }

    public function getOutputs(): array
    {
        return $this->outputPorts;
    }

    public function setInputs(array $inputs): static
    {
        $this->inputValues = array_merge($this->inputValues, $inputs);
        return $this;
    }

    public function getInputValues(): array
    {
        return $this->inputValues;
    }

    public function getInputValue(string $port, mixed $default = null): mixed
    {
        if (array_key_exists($port, $this->inputValues)) {
            return $this->inputValues[$port];
        }
        return $this->inputPorts[$port]['default'] ?? $default;
    }

    public function addError(string $error): static
    {
        $this->errors[] = $error;
        return $this;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function clearErrors(): void
    {
        $this->errors = [];
    }

    /**
     * Validate node integrity, port definitions, and custom rules.
     */
    public function validate(): bool
    {
        $this->clearErrors();

        if (trim($this->id) === '') {
            $this->addError("Node ID cannot be empty.");
        }

        if (trim($this->type) === '') {
            $this->addError("Node type cannot be empty.");
        }

        $this->validateCustom();

        return empty($this->errors);
    }

    /**
     * Custom validation hook for subclasses.
     */
    protected function validateCustom(): void
    {
        // Override in subclasses if needed
    }

    /**
     * Execution wrapper providing timing, metrics, and error handling.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function execute(array $context = []): array
    {
        $startTime = microtime(true);
        $startMem = memory_get_usage();

        // Merge incoming context into input values
        $mergedInputs = array_merge($this->inputValues, $context);
        $this->inputValues = $mergedInputs;

        $outputs = [];
        $status = 'COMPLETED';
        $errorMessage = null;

        try {
            if (!$this->validate()) {
                throw new InvalidArgumentException(
                    "Node '{$this->id}' validation failed: " . implode('; ', $this->errors)
                );
            }

            $outputs = $this->process($mergedInputs);
        } catch (Throwable $e) {
            $status = 'FAILED';
            $errorMessage = $e->getMessage();
            $this->addError($errorMessage);
            $outputs['error'] = $errorMessage;
        }

        $elapsed = max(0.00001, microtime(true) - $startTime);
        $memoryUsed = max(0, memory_get_usage() - $startMem);

        $this->lastExecution = [
            'node_id' => $this->id,
            'node_type' => $this->type,
            'status' => $status,
            'duration_ms' => round($elapsed * 1000, 3),
            'memory_bytes' => $memoryUsed,
            'timestamp' => microtime(true),
            'inputs' => $mergedInputs,
            'outputs' => $outputs,
            'error' => $errorMessage,
        ];

        return $outputs;
    }

    /**
     * Subclass core execution logic.
     *
     * @param array<string, mixed> $inputs Resolved inputs
     * @return array<string, mixed> Output dictionary
     */
    abstract protected function process(array $inputs): array;

    /**
     * Retrieve telemetry of the last execution.
     *
     * @return array<string, mixed>|null
     */
    public function getLastExecution(): ?array
    {
        return $this->lastExecution;
    }

    /**
     * Export node to standard array structure.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'position' => $this->getPosition(),
            'config' => $this->config,
            'inputs' => $this->inputPorts,
            'outputs' => $this->outputPorts,
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * Reconstitute node instance from array.
     */
    public static function fromArray(array $data): static
    {
        $id = (string)($data['id'] ?? uniqid('node_'));
        $title = (string)($data['title'] ?? '');
        $config = (array)($data['config'] ?? []);
        $position = (array)($data['position'] ?? ['x' => 0, 'y' => 0]);

        $instance = new static($id, $title, $config, $position);

        if (isset($data['inputs']) && is_array($data['inputs'])) {
            foreach ($data['inputs'] as $name => $port) {
                if (is_array($port)) {
                    $instance->registerInputPort(
                        $port['name'] ?? $name,
                        $port['type'] ?? 'any',
                        $port['description'] ?? '',
                        (bool)($port['required'] ?? false),
                        $port['default'] ?? null
                    );
                }
            }
        }

        if (isset($data['outputs']) && is_array($data['outputs'])) {
            foreach ($data['outputs'] as $name => $port) {
                if (is_array($port)) {
                    $instance->registerOutputPort(
                        $port['name'] ?? $name,
                        $port['type'] ?? 'any',
                        $port['description'] ?? ''
                    );
                }
            }
        }

        return $instance;
    }
}
