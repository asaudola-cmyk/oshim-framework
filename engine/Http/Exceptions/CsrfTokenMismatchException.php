<?php
declare(strict_types=1);

namespace Oshim\Http\Exceptions;

use Throwable;

class CsrfTokenMismatchException extends HttpException
{
    public function __construct(string $message = 'CSRF token mismatch.', array $headers = [], ?Throwable $previous = null)
    {
        parent::__construct(419, $message, $headers, $previous);
    }
}
