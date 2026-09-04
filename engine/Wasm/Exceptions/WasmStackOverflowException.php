<?php
declare(strict_types=1);

namespace Oshim\Wasm\Exceptions;

/**
 * Exception thrown when maximum call stack depth or operand stack size is exceeded.
 */
class WasmStackOverflowException extends WasmTrapException
{
    private int $currentDepth;
    private int $maxDepth;

    public function __construct(int $currentDepth = 0, int $maxDepth = 0, ?\Throwable $previous = null)
    {
        $this->currentDepth = $currentDepth;
        $this->maxDepth = $maxDepth;

        $msg = sprintf(
            'Call stack overflow: recursion depth %d exceeded maximum permitted depth of %d frames',
            $currentDepth,
            $maxDepth
        );
        parent::__construct($msg, 'stack_overflow', 0, $previous);
    }

    public function getCurrentDepth(): int
    {
        return $this->currentDepth;
    }

    public function getMaxDepth(): int
    {
        return $this->maxDepth;
    }
}
