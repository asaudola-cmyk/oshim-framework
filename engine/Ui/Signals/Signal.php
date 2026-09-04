<?php
declare(strict_types=1);

namespace Oshim\Ui\Signals;

use Closure;
use JsonSerializable;

/**
 * Fine-grained Reactive Signal (SolidJS / Preact style in Pure PHP).
 */
class Signal implements JsonSerializable
{
    private string $id;
    private mixed $value;
    /** @var array<string, Closure> */
    private array $subscribers = [];
    private static ?Closure $currentObserver = null;

    public function __construct(mixed $initialValue, ?string $id = null)
    {
        $this->id = $id ?? ('sig-' . substr(md5(uniqid('', true)), 0, 8));
        $this->value = $initialValue;
    }

    public static function make(mixed $initialValue, ?string $id = null): self
    {
        return new self($initialValue, $id);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public static function setCurrentObserver(?Closure $observer): void
    {
        self::$currentObserver = $observer;
    }

    public static function getCurrentObserver(): ?Closure
    {
        return self::$currentObserver;
    }

    public function get(): mixed
    {
        if (self::$currentObserver !== null) {
            $key = spl_object_hash(self::$currentObserver);
            $this->subscribers[$key] = self::$currentObserver;
        }
        return $this->value;
    }

    public function peek(): mixed
    {
        return $this->value;
    }

    public function set(mixed $newValue): void
    {
        if ($this->value === $newValue) {
            return;
        }

        $oldValue = $this->value;
        $this->value = $newValue;

        foreach ($this->subscribers as $subscriber) {
            $subscriber($newValue, $oldValue);
        }
    }

    public function subscribe(callable $callback): string
    {
        $closure = $callback(...);
        $key = spl_object_hash($closure);
        $this->subscribers[$key] = $closure;
        return $key;
    }

    public function unsubscribe(string $key): void
    {
        unset($this->subscribers[$key]);
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'value' => $this->value,
        ];
    }
}
