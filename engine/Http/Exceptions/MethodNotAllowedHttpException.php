<?php
declare(strict_types=1);

namespace Oshim\Http\Exceptions;

use Throwable;

class MethodNotAllowedHttpException extends HttpException
{
    /** @var list<string> */
    protected array $allowedMethods;

    public function __construct(array $allowedMethods = [], string $message = 'Method Not Allowed', array $headers = [], ?Throwable $previous = null)
    {
        $this->allowedMethods = $allowedMethods;
        $headers['Allow'] = implode(', ', $allowedMethods);
        parent::__construct(405, $message, $headers, $previous);
    }

    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
