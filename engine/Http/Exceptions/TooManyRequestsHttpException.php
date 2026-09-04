<?php
declare(strict_types=1);

namespace Oshim\Http\Exceptions;

use Throwable;

class TooManyRequestsHttpException extends HttpException
{
    protected int $retryAfter;

    public function __construct(int $retryAfter = 60, string $message = 'Too Many Requests', array $headers = [], ?Throwable $previous = null)
    {
        $this->retryAfter = $retryAfter;
        $headers['Retry-After'] = (string)$retryAfter;
        parent::__construct(429, $message, $headers, $previous);
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
