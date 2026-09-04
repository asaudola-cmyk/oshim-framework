<?php
declare(strict_types=1);

namespace Oshim\Http\Exceptions;

use RuntimeException;
use Throwable;

class HttpException extends RuntimeException
{
    protected int $statusCode;
    protected array $headers;

    public function __construct(int $statusCode = 500, string $message = '', array $headers = [], ?Throwable $previous = null)
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}
