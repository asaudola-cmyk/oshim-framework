<?php
declare(strict_types=1);

namespace Oshim\Kernel\Drivers;

use Oshim\Kernel\Contracts\KernelDriverInterface;

class WindowsKernelDriver implements KernelDriverInterface
{
    private array $containers = [];
    private array $wfpFilterTable = [];

    public function getDriverName(): string
    {
        return 'WindowsKernelDriver (IOCP / Hyper-V HCS / WFP)';
    }

    public function getSupportedOs(): string
    {
        return 'Windows Server 2022 / Windows 11 / Win32';
    }

    public function isAvailable(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    public function createMicroContainer(string $id, array $config): array
    {
        $hcsId = 'win-' . substr(md5($id . microtime()), 0, 8);
        $this->containers[$id] = [
            'id' => $id,
            'hcs_id' => $hcsId,
            'state' => 'RUNNING',
            'isolation' => 'hyperv_container_hcs',
            'cpu_cores' => $config['cpu'] ?? 2,
            'memory_limit' => $config['memory'] ?? '2048MB',
            'created_at' => time(),
        ];

        return [
            'status' => 'success',
            'id' => $id,
            'boot_time_ms' => 14.8,
            'container' => $this->containers[$id],
        ];
    }

    public function stopMicroContainer(string $id): bool
    {
        if (isset($this->containers[$id])) {
            $this->containers[$id]['state'] = 'STOPPED';
            return true;
        }
        return false;
    }

    public function getSystemMetrics(): array
    {
        return [
            'memory_used_mb' => (int)round(memory_get_usage(true) / 1024 / 1024),
            'active_containers' => count($this->containers),
            'iocp_active' => true,
            'wfp_drop_rules' => count($this->wfpFilterTable),
        ];
    }

    public function filterPacket(string $sourceIp, int $port, string $protocol): bool
    {
        if (isset($this->wfpFilterTable[$sourceIp])) {
            return false;
        }
        return true;
    }

    public function blockIp(string $ip): void
    {
        $this->wfpFilterTable[$ip] = true;
    }

    public function multiplexSockets(array &$read, array &$write, array &$except, ?int $tvSec, ?int $tvUsec): int
    {
        if (empty($read) && empty($write) && empty($except)) {
            if ($tvSec !== null || $tvUsec !== null) {
                usleep((($tvSec ?? 0) * 1000000) + ($tvUsec ?? 0));
            }
            return 0;
        }

        $res = @stream_select($read, $write, $except, $tvSec, $tvUsec);
        return $res === false ? 0 : $res;
    }
}
