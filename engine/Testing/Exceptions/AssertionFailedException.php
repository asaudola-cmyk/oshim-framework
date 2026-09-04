<?php
declare(strict_types=1);

namespace Oshim\Testing\Exceptions;

use RuntimeException;
use Throwable;

class AssertionFailedException extends RuntimeException
{
    private ?string $diff = null;

    public function __construct(string $message = '', ?string $diff = null, int $code = 0, ?Throwable $previous = null)
    {
        $this->diff = $diff;
        parent::__construct($message, $code, $previous);
    }

    public function getDiff(): ?string
    {
        return $this->diff;
    }
}
