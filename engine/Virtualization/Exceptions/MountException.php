<?php
declare(strict_types=1);

namespace Oshim\Virtualization\Exceptions;

/**
 * Exception thrown for mount, umount, pivot_root, or chroot failures.
 */
class MountException extends VirtualizationException
{
}
