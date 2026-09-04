<?php
declare(strict_types=1);

namespace Oshim\Ui\Rsc;

use JsonSerializable;

/**
 * Base React Server Component (RSC) in Pure PHP.
 * Executes strictly on the server with 0 bytes of JavaScript shipped to the client.
 */
abstract class ServerComponent implements JsonSerializable
{
    protected array $props;

    public function __construct(array $props = [])
    {
        $this->props = $props;
    }

    public static function make(array $props = []): static
    {
        return new static($props);
    }

    public function getProps(): array
    {
        return $this->props;
    }

    /**
     * Synchronous or Async render on server.
     */
    abstract public function render(): string;

    public function jsonSerialize(): array
    {
        return [
            'type' => 'SERVER_COMPONENT',
            'class' => static::class,
            'html' => $this->render(),
            'zero_js' => true,
        ];
    }

    public function __toString(): string
    {
        return $this->render();
    }
}
