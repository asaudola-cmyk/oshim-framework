<?php
declare(strict_types=1);

namespace Oshim\Database\Query;

use Stringable;

/**
 * Raw SQL Expression wrapper.
 */
class Expression implements Stringable
{
    public function __construct(protected string|int|float $value)
    {
    }

    public function getValue(): string|int|float
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return (string)$this->value;
    }
}
