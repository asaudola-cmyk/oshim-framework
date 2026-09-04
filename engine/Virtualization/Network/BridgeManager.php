<?php
declare(strict_types=1);

namespace Oshim\Virtualization\Network;

use Oshim\Virtualization\Exceptions\NetworkException;
use Oshim\Virtualization\Syscall\LinuxConstants;
use Oshim\Virtualization\Syscall\LinuxSyscall;
use Throwable;

/**
 * Linux bridge interface manager for master network routing (oshim0).
 */
class BridgeManager
{
    private string $defaultBridge;
    private string $defaultGatewayCidr;

    public function __construct(string $defaultBridge = 'oshim0', string $defaultGatewayCidr = '10.42.0.1/24')
    {
        $this->defaultBridge = $defaultBridge;
        $this->defaultGatewayCidr = $defaultGatewayCidr;
    }

    public function isBridgeActive(string $bridgeName = 'oshim0'): bool
    {
        return is_dir("/sys/class/net/{$bridgeName}/bridge");
    }

    /**
     * Ensure the bridge device exists, has the gateway IP assigned, and is brought UP.
     */
    public function ensureBridgeExists(string $bridgeName = 'oshim0', ?string $gatewayCidr = null): bool
    {
        $cidr = $gatewayCidr ?? $this->defaultGatewayCidr;

        if ($this->isBridgeActive($bridgeName)) {
            return true;
        }

        // Try creating bridge via ioctl or iproute2
        $created = false;
        if (class_exists('FFI') && PHP_OS_FAMILY === 'Linux') {
            try {
                $ffi = LinuxSyscall::getFFI();
                $sock = $ffi->open('/dev/null', 0); // placeholder or socket descriptor
                // Attempt SIOCBRADDBR if socket available
            } catch (Throwable) {
                // Fallback to shell ip link command
            }
        }

        // Use ip link command
        $cmdCreate = "ip link add name {$bridgeName} type bridge 2>&1";
        @exec($cmdCreate, $out, $ret);

        if ($ret !== 0 && !$this->isBridgeActive($bridgeName)) {
            // Check if mock or rootless
            return false;
        }

        // Assign IP and bring up
        @exec("ip addr add {$cidr} dev {$bridgeName} 2>&1");
        @exec("ip link set {$bridgeName} up 2>&1");

        return true;
    }

    /**
     * Attach a network interface to the bridge.
     */
    public function attachInterface(string $bridgeName, string $interfaceName): bool
    {
        if (!file_exists("/sys/class/net/{$interfaceName}")) {
            return false;
        }

        @exec("ip link set {$interfaceName} master {$bridgeName} 2>&1", $out, $ret);
        @exec("ip link set {$interfaceName} up 2>&1");

        return $ret === 0;
    }

    /**
     * Detach a network interface from the bridge.
     */
    public function detachInterface(string $bridgeName, string $interfaceName): bool
    {
        @exec("ip link set {$interfaceName} nomaster 2>&1", $out, $ret);
        return $ret === 0;
    }

    /**
     * Delete the bridge interface.
     */
    public function deleteBridge(string $bridgeName = 'oshim0'): bool
    {
        @exec("ip link set {$bridgeName} down 2>&1");
        @exec("ip link delete {$bridgeName} type bridge 2>&1", $out, $ret);
        return $ret === 0;
    }

    /**
     * Retrieve status and attached interfaces for a bridge.
     *
     * @return array<string, mixed>
     */
    public function getBridgeInfo(string $bridgeName = 'oshim0'): array
    {
        $active = $this->isBridgeActive($bridgeName);
        $interfaces = [];

        $brIfDir = "/sys/class/net/{$bridgeName}/brif";
        if (is_dir($brIfDir)) {
            $entries = @scandir($brIfDir);
            if ($entries) {
                foreach ($entries as $e) {
                    if ($e !== '.' && $e !== '..') {
                        $interfaces[] = $e;
                    }
                }
            }
        }

        return [
            'bridge_name' => $bridgeName,
            'is_active'   => $active,
            'interfaces'  => $interfaces,
        ];
    }
}
