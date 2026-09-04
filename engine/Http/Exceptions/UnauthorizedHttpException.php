<?php
declare(strict_types=1);

namespace Oshim\Http\Exceptions;

use Throwable;

class UnauthorizedHttpException extends HttpException
{
    public function __construct(string $message = 'Unauthorized', array $headers = [], ?Throwable $previous = null)
    {
        parent::__construct(401, $message, $headers, $previous);
    }
}
