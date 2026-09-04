<?php
declare(strict_types=1);

namespace Oshim\Virtualization;

use Oshim\Container\Container;
use Oshim\Container\ServiceProviderInterface;
use Oshim\Virtualization\Cgroup\CgroupV2Manager;
use Oshim\Virtualization\Driver\VirtualizationDriverInterface;
use Oshim\Virtualization\Network\BridgeManager;
use Oshim\Virtualization\Network\IpamService;
use Oshim\Virtualization\Network\NatManager;
use Oshim\Virtualization\Network\SimulatedNatRouter;
use Oshim\Virtualization\Network\VethManager;
use Oshim\Virtualization\Storage\OverlayFsManager;
use Oshim\Virtualization\Storage\SnapshotManager;
use Oshim\Virtualization\Storage\StorageQuotaManager;
use Oshim\Virtualization\Syscall\LinuxSyscall;
use Oshim\Virtualization\Syscall\SyscallInterface;

/**
 * Service Provider integrating the Virtualization & Node Engine with the OSHIM DI container.
 */
class VirtualizationServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        // 1. Syscall FFI binding
        $container->singleton(SyscallInterface::class, fn() => new LinuxSyscall());

        // 2. Cgroup Manager
        $container->singleton(CgroupV2Manager::class, fn() => new CgroupV2Manager());

        // 3. Storage Managers
        $container->singleton(OverlayFsManager::class, function (Container $c) {
            $storageRoot = $c->has('path.storage') ? $c->make('path.storage') . '/virtualization' : '/var/lib/oshim';
            return new OverlayFsManager($storageRoot, $c->make(SyscallInterface::class));
        });

        $container->singleton(SnapshotManager::class, function (Container $c) {
            $storageRoot = $c->has('path.storage') ? $c->make('path.storage') . '/virtualization' : '/var/lib/oshim';
            return new SnapshotManager($storageRoot, $c->make(OverlayFsManager::class));
        });

        $container->singleton(StorageQuotaManager::class, fn(Container $c) => new StorageQuotaManager($c->make(OverlayFsManager::class)));

        // 4. Network Managers
        $container->singleton(BridgeManager::class, fn() => new BridgeManager('oshim0', '10.42.0.1/24'));
        $container->singleton(VethManager::class, fn() => new VethManager());
        $container->singleton(IpamService::class, fn() => new IpamService('10.42.0.0/24', '10.42.0.1'));
        $container->singleton(NatManager::class, fn() => new NatManager());
        $container->singleton(SimulatedNatRouter::class, fn() => new SimulatedNatRouter());

        // 5. Driver Resolver & Interface binding
        $container->singleton(VirtualizationDriverInterface::class, function () {
            return VirtualizationEnvironment::resolveDriver();
        });

        // Facade aliases
        $container->bind('virtualization.driver', fn(Container $c) => $c->make(VirtualizationDriverInterface::class));
        $container->bind('virtualization.ipam', fn(Container $c) => $c->make(IpamService::class));
        $container->bind('virtualization.storage', fn(Container $c) => $c->make(OverlayFsManager::class));
    }

    public function boot(Container $container): void
    {
    }
}
