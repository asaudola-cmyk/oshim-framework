<?php
declare(strict_types=1);

namespace Oshim\Virtualization\Exceptions;

use Throwable;

/**
 * Exception thrown when a direct Linux syscall or libc call fails.
 */
class SyscallException extends VirtualizationException
{
    protected string $syscall;
    protected int $errno;
    /** @var array<string, mixed> */
    protected array $context;

    public function __construct(
        string $message,
        string $syscall = '',
        int $errno = 0,
        array $context = [],
        ?Throwable $previous = null
    ) {
        $this->syscall = $syscall;
        $this->errno = $errno;
        $this->context = $context;

        parent::__construct($message, $errno, $previous);
    }

    public function getSyscall(): string
    {
        return $this->syscall;
    }

    public function getErrno(): int
    {
        return $this->errno;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
