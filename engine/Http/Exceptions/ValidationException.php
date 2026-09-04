<?php
declare(strict_types=1);

namespace Oshim\Http\Exceptions;

use Throwable;

class ValidationException extends HttpException
{
    /** @var array<string, list<string>> */
    protected array $errors;

    public function __construct(array $errors = [], string $message = 'The given data was invalid.', array $headers = [], ?Throwable $previous = null)
    {
        $this->errors = $errors;
        parent::__construct(422, $message, $headers, $previous);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
