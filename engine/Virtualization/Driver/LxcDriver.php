<?php
declare(strict_types=1);

namespace Oshim\Virtualization\Driver;

use Oshim\Virtualization\Container;
use Oshim\Virtualization\ContainerConfig;
use Oshim\Virtualization\ContainerState;
use Oshim\Virtualization\ContainerStats;
use Oshim\Virtualization\ExecResult;

/**
 * LXC Container Virtualization Driver for Linux hosts.
 * Extends NativeLinuxDriver and implements VirtualizationDriverInterface.
 */
class LxcDriver extends NativeLinuxDriver implements VirtualizationDriverInterface
{
    /**
     * Get the driver name identifier.
     */
    public function getDriverName(): string
    {
        return 'LxcDriver';
    }
}
