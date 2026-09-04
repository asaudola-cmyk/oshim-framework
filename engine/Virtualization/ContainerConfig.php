<?php
declare(strict_types=1);

namespace Oshim\Virtualization;

/**
 * Immutable configuration DTO for container creation and resource quotas.
 */
final class ContainerConfig
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $image = 'ubuntu-24.04-base',
        public readonly float $vcpu = 1.0,
        public readonly int $cpuWeight = 100,
        public readonly int $memoryLimitBytes = 1073741824, // 1 GB
        public readonly ?int $memoryHighBytes = null,
        public readonly ?int $memoryLowBytes = null,
        public readonly int $swapLimitBytes = 0,
        public readonly int $diskLimitBytes = 21474836480, // 20 GB
        public readonly int $pidsLimit = 512,
        /** @var array<string, array<string, int>> */
        public readonly array $ioLimits = [],
        public readonly ?string $ipAddress = null,
        public readonly string $netmask = '255.255.255.0',
        public readonly string $gateway = '10.42.0.1',
        /** @var list<string> */
        public readonly array $dnsServers = ['10.42.0.1', '1.1.1.1', '8.8.8.8'],
        public readonly ?string $macAddress = null,
        public readonly ?string $tapDevice = null,
        /** @var list<array{public_port: int, guest_port: int, proto?: string}> */
        public readonly array $portForwards = [],
        /** @var list<string> */
        public readonly array $sshAuthorizedKeys = [],
        /** @var list<string> */
        public readonly array $entrypoint = ['/bin/sh'],
        /** @var array<string, string> */
        public readonly array $env = [],
        public readonly string $workingDir = '/'
    ) {}

    /**
     * Create a ContainerConfig instance from an associative specification array.
     * Supports both modern and legacy parameter keys.
     *
     * @param array<string, mixed> $spec
     */
    public static function fromArray(array $spec): self
    {
        $id = (string)($spec['id'] ?? $spec['instance_id'] ?? ('inst_' . bin2hex(random_bytes(6))));
        $name = (string)($spec['name'] ?? $spec['hostname'] ?? ('container-' . substr($id, 0, 8)));
        $image = (string)($spec['image'] ?? $spec['os'] ?? 'ubuntu-24.04-base');

        // CPU resolution
        $vcpu = 1.0;
        if (isset($spec['vcpu'])) {
            $vcpu = (float)$spec['vcpu'];
        } elseif (isset($spec['cpu_limit'])) {
            $vcpu = (float)$spec['cpu_limit'];
        } elseif (isset($spec['cpu_cores'])) {
            $vcpu = (float)$spec['cpu_cores'];
        }

        $cpuWeight = (int)($spec['cpu_weight'] ?? 100);

        // Memory resolution
        $memoryLimitBytes = 1073741824;
        if (isset($spec['memory_limit_bytes'])) {
            $memoryLimitBytes = (int)$spec['memory_limit_bytes'];
        } elseif (isset($spec['memory_max_bytes'])) {
            $memoryLimitBytes = (int)$spec['memory_max_bytes'];
        } elseif (isset($spec['ram_mb'])) {
            $memoryLimitBytes = (int)$spec['ram_mb'] * 1024 * 1024;
        } elseif (isset($spec['ram_gb'])) {
            $memoryLimitBytes = (int)$spec['ram_gb'] * 1024 * 1024 * 1024;
        }

        $memoryHighBytes = isset($spec['memory_high_bytes'])
            ? (int)$spec['memory_high_bytes']
            : (int)($memoryLimitBytes * 0.875);

        $memoryLowBytes = isset($spec['memory_low_bytes']) ? (int)$spec['memory_low_bytes'] : null;
        $swapLimitBytes = isset($spec['memory_swap_max_bytes'])
            ? (int)$spec['memory_swap_max_bytes']
            : (int)($spec['swap_limit_bytes'] ?? 0);

        // Disk resolution
        $diskLimitBytes = 21474836480;
        if (isset($spec['disk_limit_bytes'])) {
            $diskLimitBytes = (int)$spec['disk_limit_bytes'];
        } elseif (isset($spec['disk_gb'])) {
            $diskLimitBytes = (int)$spec['disk_gb'] * 1024 * 1024 * 1024;
        } elseif (isset($spec['disk_mb'])) {
            $diskLimitBytes = (int)$spec['disk_mb'] * 1024 * 1024;
        }

        // PIDs resolution
        $pidsLimit = (int)($spec['pids_limit'] ?? $spec['pids_max'] ?? 512);

        // IO limits
        $ioLimits = (array)($spec['io_limits'] ?? []);

        // Network resolution
        $net = (array)($spec['network'] ?? []);
        $ipAddress = $net['ip_address'] ?? $spec['ip_address'] ?? $spec['ipv4'] ?? null;
        $netmask = (string)($net['netmask'] ?? $spec['netmask'] ?? '255.255.255.0');
        $gateway = (string)($net['gateway'] ?? $spec['gateway'] ?? '10.42.0.1');
        $dnsServers = (array)($net['dns'] ?? $net['dns_servers'] ?? $spec['dns_servers'] ?? ['10.42.0.1', '1.1.1.1', '8.8.8.8']);
        $macAddress = $net['mac_address'] ?? $spec['mac_address'] ?? null;
        $tapDevice = $net['tap_device'] ?? $spec['tap_dev'] ?? $spec['tap_device'] ?? null;

        // Port forwards
        $portForwards = (array)($spec['port_forwards'] ?? []);

        // SSH keys
        $sshAuthorizedKeys = (array)($spec['ssh_authorized_keys'] ?? $spec['ssh_keys'] ?? []);

        // Entrypoint & Env
        $entrypoint = (array)($spec['entrypoint'] ?? ['/bin/sh']);
        $env = (array)($spec['env'] ?? []);
        $workingDir = (string)($spec['working_dir'] ?? '/');

        return new self(
            id: $id,
            name: $name,
            image: $image,
            vcpu: $vcpu,
            cpuWeight: $cpuWeight,
            memoryLimitBytes: $memoryLimitBytes,
            memoryHighBytes: $memoryHighBytes,
            memoryLowBytes: $memoryLowBytes,
            swapLimitBytes: $swapLimitBytes,
            diskLimitBytes: $diskLimitBytes,
            pidsLimit: $pidsLimit,
            ioLimits: $ioLimits,
            ipAddress: $ipAddress !== null ? (string)$ipAddress : null,
            netmask: $netmask,
            gateway: $gateway,
            dnsServers: array_values($dnsServers),
            macAddress: $macAddress !== null ? (string)$macAddress : null,
            tapDevice: $tapDevice !== null ? (string)$tapDevice : null,
            portForwards: $portForwards,
            sshAuthorizedKeys: array_values($sshAuthorizedKeys),
            entrypoint: array_values($entrypoint),
            env: $env,
            workingDir: $workingDir
        );
    }

    public function getId(): string { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getImage(): string { return $this->image; }
    public function getVcpu(): float { return $this->vcpu; }
    public function getCpuWeight(): int { return $this->cpuWeight; }
    public function getMemoryLimitBytes(): int { return $this->memoryLimitBytes; }
    public function getMemoryHighBytes(): ?int { return $this->memoryHighBytes; }
    public function getMemoryLowBytes(): ?int { return $this->memoryLowBytes; }
    public function getSwapLimitBytes(): int { return $this->swapLimitBytes; }
    public function getDiskLimitBytes(): int { return $this->diskLimitBytes; }
    public function getPidsLimit(): int { return $this->pidsLimit; }
    public function getIoLimits(): array { return $this->ioLimits; }
    public function getIpAddress(): ?string { return $this->ipAddress; }
    public function getNetmask(): string { return $this->netmask; }
    public function getGateway(): string { return $this->gateway; }
    public function getDnsServers(): array { return $this->dnsServers; }
    public function getMacAddress(): ?string { return $this->macAddress; }
    public function getTapDevice(): ?string { return $this->tapDevice; }
    public function getPortForwards(): array { return $this->portForwards; }
    public function getSshAuthorizedKeys(): array { return $this->sshAuthorizedKeys; }
    public function getEntrypoint(): array { return $this->entrypoint; }
    public function getEnv(): array { return $this->env; }
    public function getWorkingDir(): string { return $this->workingDir; }

    /**
     * Convert configuration to associative array format.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'                  => $this->id,
            'instance_id'         => $this->id,
            'name'                => $this->name,
            'hostname'            => $this->name,
            'image'               => $this->image,
            'os'                  => $this->image,
            'vcpu'                => $this->vcpu,
            'cpu_limit'           => $this->vcpu,
            'cpu_weight'          => $this->cpuWeight,
            'memory_limit_bytes'  => $this->memoryLimitBytes,
            'ram_mb'              => (int)($this->memoryLimitBytes / 1024 / 1024),
            'memory_high_bytes'   => $this->memoryHighBytes,
            'memory_low_bytes'    => $this->memoryLowBytes,
            'memory_swap_max'     => $this->swapLimitBytes,
            'disk_limit_bytes'    => $this->diskLimitBytes,
            'disk_gb'             => (int)($this->diskLimitBytes / 1024 / 1024 / 1024),
            'pids_limit'          => $this->pidsLimit,
            'io_limits'           => $this->ioLimits,
            'network'             => [
                'ip_address'  => $this->ipAddress,
                'netmask'     => $this->netmask,
                'gateway'     => $this->gateway,
                'dns'         => $this->dnsServers,
                'mac_address' => $this->macAddress,
                'tap_device'  => $this->tapDevice,
            ],
            'ip_address'          => $this->ipAddress,
            'ipv4'                => $this->ipAddress,
            'tap_dev'             => $this->tapDevice,
            'port_forwards'       => $this->portForwards,
            'ssh_authorized_keys' => $this->sshAuthorizedKeys,
            'entrypoint'          => $this->entrypoint,
            'env'                 => $this->env,
            'working_dir'         => $this->workingDir,
        ];
    }
}
