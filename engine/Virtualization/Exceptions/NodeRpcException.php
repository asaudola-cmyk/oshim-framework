<?php
declare(strict_types=1);

namespace Oshim\Virtualization\Exceptions;

use Throwable;

/**
 * Exception thrown for Node JSON-RPC 2.0 communication and protocol errors.
 */
class NodeRpcException extends VirtualizationException
{
    protected mixed $rpcData;

    public function __construct(string $message = '', int $code = -32603, mixed $rpcData = null, ?Throwable $previous = null)
    {
        $this->rpcData = $rpcData;
        parent::__construct($message, $code, $previous);
    }

    public function getRpcData(): mixed
    {
        return $this->rpcData;
    }
}
