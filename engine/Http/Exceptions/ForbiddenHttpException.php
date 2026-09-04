<?php
declare(strict_types=1);

namespace Oshim\Http\Exceptions;

use Throwable;

class ForbiddenHttpException extends HttpException
{
    public function __construct(string $message = 'Forbidden', array $headers = [], ?Throwable $previous = null)
    {
        parent::__construct(403, $message, $headers, $previous);
    }
}
