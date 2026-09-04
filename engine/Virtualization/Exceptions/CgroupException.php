<?php
declare(strict_types=1);

namespace Oshim\Virtualization\Exceptions;

/**
 * Exception thrown for Linux Cgroups v2 management or quota enforcement failures.
 */
class CgroupException extends VirtualizationException
{
}
