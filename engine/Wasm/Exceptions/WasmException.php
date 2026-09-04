<?php
declare(strict_types=1);

namespace Oshim\Wasm\Exceptions;

use RuntimeException;

/**
 * Base exception for all WebAssembly execution, parsing, and sandbox errors.
 */
class WasmException extends RuntimeException
{
}
