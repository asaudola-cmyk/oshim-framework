<?php
declare(strict_types=1);

namespace Oshim\Ui\Signals;

use Closure;
use JsonSerializable;

/**
 * Computed Derived Reactive Value with auto-dependency tracking.
 */
class Computed implements JsonSerializable
{
    private string $id;
    private Closure $computeFn;
    private mixed $cachedValue;
    private bool $dirty = true;

    public function __construct(callable $computeFn, ?string $id = null)
    {
        $this->id = $id ?? ('comp-' . substr(md5(uniqid('', true)), 0, 8));
        $this->computeFn = $computeFn(...);
    }

    public static function make(callable $computeFn, ?string $id = null): self
    {
        return new self($computeFn, $id);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function get(): mixed
    {
        if ($this->dirty) {
            $prevObserver = Signal::getCurrentObserver();
            $onDependencyChange = function () {
                $this->dirty = true;
            };

            Signal::setCurrentObserver($onDependencyChange);
            try {
                $this->cachedValue = ($this->computeFn)();
                $this->dirty = false;
            } finally {
                Signal::setCurrentObserver($prevObserver);
            }
        }

        return $this->cachedValue;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'value' => $this->get(),
        ];
    }
}
