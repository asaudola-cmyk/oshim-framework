<?php
declare(strict_types=1);

namespace Oshim\Ui\Reactive;

use ReflectionClass;
use ReflectionProperty;
use JsonSerializable;
use RuntimeException;

/**
 * Base Stateful Reactive UI Component (LiveWire / Hotwire style in pure PHP).
 */
abstract class ReactiveComponent implements JsonSerializable
{
    private string $id;
    private static string $signingSecret = 'oshim_reactive_secret_key_99482';

    public function __construct(?string $id = null)
    {
        $this->id = $id ?? ('oshim-comp-' . substr(md5(static::class . microtime()), 0, 8));
    }

    public function getId(): string
    {
        return $this->id;
    }

    public static function setSigningSecret(string $secret): void
    {
        self::$signingSecret = $secret;
    }

    public static function getSigningSecret(): string
    {
        return $_ENV['APP_KEY'] ?? getenv('APP_KEY') ?: self::$signingSecret;
    }

    /**
     * Component Lifecycle: Hook executed on initial mounting.
     */
    public function mount(...$args): void
    {
    }

    /**
     * Render the component HTML output.
     */
    abstract public function render(): string;

    /**
     * Extract public properties as state.
     */
    public function getState(): array
    {
        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        $state = [];

        foreach ($properties as $property) {
            if (!$property->isStatic()) {
                $state[$property->getName()] = $property->getValue($this);
            }
        }

        return $state;
    }

    /**
     * Hydrate public state from array.
     */
    public function hydrate(array $state): void
    {
        $reflection = new ReflectionClass($this);
        foreach ($state as $key => $value) {
            if ($reflection->hasProperty($key)) {
                $prop = $reflection->getProperty($key);
                if ($prop->isPublic() && !$prop->isStatic()) {
                    $prop->setValue($this, $value);
                }
            }
        }
    }

    /**
     * Call an action method on the component with security verification.
     */
    public function callAction(string $method, array $params = []): mixed
    {
        ActionRegistry::assertActionAllowed(static::class, $method);

        if (!method_exists($this, $method)) {
            throw new RuntimeException("Method '{$method}' does not exist on component " . static::class);
        }

        return $this->$method(...$params);
    }

    /**
     * Create a cryptographically signed payload for client state transport.
     */
    public function createSignedPayload(): array
    {
        $state = $this->getState();
        $serialized = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $checksum = hash_hmac('sha256', (string)$serialized, self::getSigningSecret());

        return [
            'id' => $this->id,
            'class' => static::class,
            'state' => $state,
            'checksum' => $checksum,
        ];
    }

    /**
     * Verify payload checksum and hydrate component.
     */
    public static function restoreFromSignedPayload(array $payload): static
    {
        $state = $payload['state'] ?? [];
        $checksum = $payload['checksum'] ?? '';
        $serialized = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $expectedChecksum = hash_hmac('sha256', (string)$serialized, self::getSigningSecret());

        if (!hash_equals($expectedChecksum, $checksum)) {
            throw new RuntimeException("Reactive component state checksum mismatch. Possible tampering detected.");
        }

        $instance = new static($payload['id'] ?? null);
        $instance->hydrate($state);
        return $instance;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'html' => $this->render(),
            'payload' => $this->createSignedPayload(),
        ];
    }
}
