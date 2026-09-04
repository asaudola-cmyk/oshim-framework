<?php
declare(strict_types=1);

namespace Oshim\Wasm\Exceptions;

/**
 * Exception thrown when a WebAssembly binary or module structure is malformed.
 */
class WasmParserException extends WasmException
{
    private int $byteOffset;

    public function __construct(string $message = '', int $byteOffset = 0, int $code = 0, ?\Throwable $previous = null)
    {
        $this->byteOffset = $byteOffset;
        $offsetMsg = $byteOffset > 0 ? sprintf(' at byte offset 0x%X (%d)', $byteOffset, $byteOffset) : '';
        parent::__construct($message . $offsetMsg, $code, $previous);
    }

    public function getByteOffset(): int
    {
        return $this->byteOffset;
    }
}
