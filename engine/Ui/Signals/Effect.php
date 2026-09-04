<?php
declare(strict_types=1);

namespace Oshim\Ui\Signals;

use Closure;

/**
 * Reactive Effect Runner for Signal side-effects.
 */
class Effect
{
    private Closure $effectFn;

    public function __construct(callable $effectFn)
    {
        $this->effectFn = $effectFn(...);
        $this->run();
    }

    public static function make(callable $effectFn): self
    {
        return new self($effectFn);
    }

    public function run(): void
    {
        $prevObserver = Signal::getCurrentObserver();
        $onDependencyChange = function () {
            $this->run();
        };

        Signal::setCurrentObserver($onDependencyChange);
        try {
            ($this->effectFn)();
        } finally {
            Signal::setCurrentObserver($prevObserver);
        }
    }
}
