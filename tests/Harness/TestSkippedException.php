<?php
declare(strict_types=1);

namespace Oshim\Tests\Harness;

use RuntimeException;
use Throwable;

/**
 * Thrown when a test is skipped.
 */
class TestSkippedException extends RuntimeException
{
    public function __construct(string $message = 'Test skipped', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
