<?php
declare(strict_types=1);

namespace Oshim\Tests\Harness;

use RuntimeException;
use Throwable;

/**
 * Thrown when a test assertion fails.
 */
class AssertionException extends RuntimeException
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
