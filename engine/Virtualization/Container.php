<?php
declare(strict_types=1);

namespace Oshim\Virtualization;

/**
 * Container runtime instance model.
 */
class Container
{
    private string $id;
    private string $name;
    private ContainerConfig $config;
    private string $state;
    private ?int $pid = null;
    private ?string $rootfsPath = null;
    private ?string $diffPath = null;
    private ?string $workPath = null;
    private ?string $cgroupPath = null;
    private ?string $tapDevice = null;
    private ?string $ipAddress = null;
    private ?string $macAddress = null;
    private string $createdAt;
    private string $updatedAt;
    private ?string $startedAt = null;
    private ?string $stoppedAt = null;

    public function __construct(
        string $id,
        string $name,
        ContainerConfig $config,
        string $state = ContainerState::CREATED,
        ?int $pid = null,
        ?string $rootfsPath = null,
        ?string $diffPath = null,
        ?string $workPath = null,
        ?string $cgroupPath = null,
        ?string $tapDevice = null,
        ?string $ipAddress = null,
        ?string $macAddress = null,
        ?string $createdAt = null,
        ?string $updatedAt = null,
        ?string $startedAt = null,
        ?string $stoppedAt = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->config = $config;
        $this->state = strtoupper($state);
        $this->pid = $pid;
        $this->rootfsPath = $rootfsPath;
        $this->diffPath = $diffPath;
        $this->workPath = $workPath;
        $this->cgroupPath = $cgroupPath;
        $this->tapDevice = $tapDevice ?? $config->getTapDevice();
        $this->ipAddress = $ipAddress ?? $config->getIpAddress();
        $this->macAddress = $macAddress ?? $config->getMacAddress();
        $now = date('Y-m-d H:i:s');
        $this->createdAt = $createdAt ?? $now;
        $this->updatedAt = $updatedAt ?? $now;
        $this->startedAt = $startedAt;
        $this->stoppedAt = $stoppedAt;
    }

    public static function create(ContainerConfig $config): self
    {
        return new self(
            id: $config->getId(),
            name: $config->getName(),
            config: $config,
            state: ContainerState::CREATED,
            ipAddress: $config->getIpAddress(),
            macAddress: $config->getMacAddress(),
            tapDevice: $config->getTapDevice()
        );
    }

    public function getId(): string { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getConfig(): ContainerConfig { return $this->config; }
    public function getState(): string { return $this->state; }
    public function getPid(): ?int { return $this->pid; }
    public function getRootfsPath(): ?string { return $this->rootfsPath; }
    public function getDiffPath(): ?string { return $this->diffPath; }
    public function getWorkPath(): ?string { return $this->workPath; }
    public function getCgroupPath(): ?string { return $this->cgroupPath; }
    public function getTapDevice(): ?string { return $this->tapDevice; }
    public function getIpAddress(): ?string { return $this->ipAddress; }
    public function getMacAddress(): ?string { return $this->macAddress; }
    public function getCreatedAt(): string { return $this->createdAt; }
    public function getUpdatedAt(): string { return $this->updatedAt; }
    public function getStartedAt(): ?string { return $this->startedAt; }
    public function getStoppedAt(): ?string { return $this->stoppedAt; }

    public function setState(string $state): self
    {
        $this->state = strtoupper($state);
        $this->updatedAt = date('Y-m-d H:i:s');
        if ($this->state === ContainerState::RUNNING) {
            $this->startedAt = date('Y-m-d H:i:s');
        } elseif ($this->state === ContainerState::STOPPED) {
            $this->stoppedAt = date('Y-m-d H:i:s');
        }
        return $this;
    }

    public function setPid(?int $pid): self
    {
        $this->pid = $pid;
        $this->updatedAt = date('Y-m-d H:i:s');
        return $this;
    }

    public function setPaths(?string $rootfs, ?string $diff = null, ?string $work = null, ?string $cgroup = null): self
    {
        $this->rootfsPath = $rootfs;
        $this->diffPath = $diff;
        $this->workPath = $work;
        $this->cgroupPath = $cgroup;
        $this->updatedAt = date('Y-m-d H:i:s');
        return $this;
    }

    public function setNetwork(?string $ip, ?string $mac = null, ?string $tap = null): self
    {
        $this->ipAddress = $ip;
        $this->macAddress = $mac;
        $this->tapDevice = $tap;
        $this->updatedAt = date('Y-m-d H:i:s');
        return $this;
    }

    public function isRunning(): bool
    {
        return $this->state === ContainerState::RUNNING;
    }

    public function isPaused(): bool
    {
        return $this->state === ContainerState::PAUSED;
    }

    public function isStopped(): bool
    {
        return $this->state === ContainerState::STOPPED || $this->state === ContainerState::CREATED;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'instance_id' => $this->id,
            'name'        => $this->name,
            'hostname'    => $this->name,
            'state'       => $this->state,
            'status'      => $this->state,
            'pid'         => $this->pid,
            'rootfs_path' => $this->rootfsPath,
            'diff_path'   => $this->diffPath,
            'work_path'   => $this->workPath,
            'cgroup_path' => $this->cgroupPath,
            'tap_device'  => $this->tapDevice,
            'tap_dev'     => $this->tapDevice,
            'ip_address'  => $this->ipAddress,
            'ipv4'        => $this->ipAddress,
            'mac_address' => $this->macAddress,
            'vcpu'        => $this->config->getVcpu(),
            'ram_mb'      => (int)($this->config->getMemoryLimitBytes() / 1024 / 1024),
            'disk_gb'     => (int)($this->config->getDiskLimitBytes() / 1024 / 1024 / 1024),
            'os'          => $this->config->getImage(),
            'config'      => $this->config->toArray(),
            'created_at'  => $this->createdAt,
            'updated_at'  => $this->updatedAt,
            'started_at'  => $this->startedAt,
            'stopped_at'  => $this->stoppedAt,
        ];
    }
}
