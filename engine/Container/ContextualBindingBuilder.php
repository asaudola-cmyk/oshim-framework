<?php
declare(strict_types=1);

namespace Oshim\Container;

final class ContextualBindingBuilder
{
    private string $abstract;

    public function __construct(
        private Container $container,
        private string $concrete
    ) {}

    /**
     * Define the abstract interface or parameter name needed.
     */
    public function needs(string $abstract): self
    {
        $this->abstract = $abstract;
        return $this;
    }

    /**
     * Specify the concrete implementation or closure to provide.
     */
    public function give(mixed $implementation): void
    {
        $this->container->addContextualBinding($this->concrete, $this->abstract, $implementation);
    }
}
