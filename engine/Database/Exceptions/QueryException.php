<?php
declare(strict_types=1);

namespace Oshim\Database\Exceptions;

use Throwable;

class QueryException extends DatabaseException
{
    protected string $sql;
    protected array $bindings;

    public function __construct(string $sql, array $bindings, Throwable $previous)
    {
        $this->sql = $sql;
        $this->bindings = $bindings;

        $message = sprintf(
            "%s (SQL: %s) [Bindings: %s]",
            $previous->getMessage(),
            $sql,
            json_encode($bindings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        parent::__construct($message, (int)$previous->getCode(), $previous);
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }
}
