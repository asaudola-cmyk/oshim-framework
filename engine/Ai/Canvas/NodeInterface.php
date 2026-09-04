<?php
declare(strict_types=1);

namespace Oshim\Ai\Canvas;

use JsonSerializable;

/**
 * Contract for executable AI Canvas nodes in the sovereign visual node graph.
 */
interface NodeInterface extends JsonSerializable
{
    /**
     * Get the unique identifier of the node.
     */
    public function getId(): string;

    /**
     * Get the node type taxonomy identifier (e.g. 'prompt', 'llm_inference', etc.).
     */
    public function getType(): string;

    /**
     * Get human-readable title of the node.
     */
    public function getTitle(): string;

    /**
     * Set human-readable title of the node.
     */
    public function setTitle(string $title): self;

    /**
     * Get 2D canvas coordinates ['x' => float, 'y' => float].
     *
     * @return array{x: float|int, y: float|int}
     */
    public function getPosition(): array;

    /**
     * Set 2D canvas coordinates.
     */
    public function setPosition(int|float $x, int|float $y): self;

    /**
     * Get the node configuration parameter dictionary.
     */
    public function getConfig(): array;

    /**
     * Set/update configuration parameters.
     */
    public function setConfig(array $config): self;

    /**
     * Get registered input port definitions.
     *
     * @return array<string, array{name: string, type: string, description: string, required: bool, default: mixed}>
     */
    public function getInputs(): array;

    /**
     * Get registered output port definitions.
     *
     * @return array<string, array{name: string, type: string, description: string}>
     */
    public function getOutputs(): array;

    /**
     * Provide input values for the node's input ports.
     */
    public function setInputs(array $inputs): self;

    /**
     * Execute the node's processing logic against the provided execution context.
     *
     * @param array<string, mixed> $context Execution context data / upstream inputs
     * @return array<string, mixed> Output port values and execution results
     */
    public function execute(array $context = []): array;

    /**
     * Validate node configuration and connectivity.
     */
    public function validate(): bool;

    /**
     * Get list of validation error messages.
     *
     * @return list<string>
     */
    public function getErrors(): array;

    /**
     * Export node specification to serializable array format.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * Reconstitute node from serialized array.
     */
    public static function fromArray(array $data): static;
}
