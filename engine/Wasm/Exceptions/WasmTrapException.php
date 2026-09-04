<?php
declare(strict_types=1);

namespace Oshim\Wasm\Exceptions;

/**
 * Exception thrown when a WebAssembly execution trap occurs (e.g., unreachable, division by zero).
 */
class WasmTrapException extends WasmException
{
    private string $trapType;

    public function __construct(string $message = 'WebAssembly execution trapped', string $trapType = 'trap', int $code = 0, ?\Throwable $previous = null)
    {
        $this->trapType = $trapType;
        parent::__construct($message, $code, $previous);
    }

    public function getTrapType(): string
    {
        return $this->trapType;
    }
}
