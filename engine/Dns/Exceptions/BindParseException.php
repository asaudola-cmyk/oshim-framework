<?php
declare(strict_types=1);

namespace Oshim\Dns\Exceptions;

/**
 * Thrown when BIND zone file syntax parsing fails.
 */
class BindParseException extends ZoneException
{
    private int $lineNumber;

    public function __construct(string $message, int $lineNumber = 0, ?\Throwable $previous = null)
    {
        $this->lineNumber = $lineNumber;
        $formatted = $lineNumber > 0 ? "Line {$lineNumber}: {$message}" : $message;
        parent::__construct($formatted, 0, $previous);
    }

    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }
}
