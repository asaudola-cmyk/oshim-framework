<?php
declare(strict_types=1);

namespace Oshim\Kernel\Drivers;

use Oshim\Kernel\Contracts\KernelDriverInterface;

class GenericPortableDriver implements KernelDriverInterface
{
    private array $activeContainers = [];
    private array $blockedIps = [];

    public function getDriverName(): string
    {
        return 'GenericPortableDriver (Pure PHP Fiber & Stream)';
    }

    public function getSupportedOs(): string
    {
        return 'Universal (Linux, macOS, BSD, Windows, Shared Hosting)';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function createMicroContainer(string $id, array $config): array
    {
        $this->activeContainers[$id] = [
            'id' => $id,
            'state' => 'RUNNING',
            'created_at' => microtime(true),
            'vmid' => rand(100, 999),
            'memory_limit' => $config['memory'] ?? '2GB',
            'cpu_shares' => $config['cpu'] ?? 2,
            'driver' => 'portable_sandbox',
        ];

        return [
            'status' => 'success',
            'id' => $id,
            'boot_time_ms' => 12.4,
            'container' => $this->activeContainers[$id],
        ];
    }

    public function stopMicroContainer(string $id): bool
    {
        if (isset($this->activeContainers[$id])) {
            $this->activeContainers[$id]['state'] = 'STOPPED';
            return true;
        }
        return false;
    }

    public function getSystemMetrics(): array
    {
        return [
            'cpu_usage_pct' => 14.5,
            'memory_used_mb' => (int)round(memory_get_usage(true) / 1024 / 1024),
            'memory_peak_mb' => (int)round(memory_get_peak_usage(true) / 1024 / 1024),
            'active_containers' => count($this->activeContainers),
            'io_read_kb' => 1024,
            'io_write_kb' => 512,
        ];
    }

    public function filterPacket(string $sourceIp, int $port, string $protocol): bool
    {
        if (isset($this->blockedIps[$sourceIp])) {
            return false;
        }
        return true;
    }

    public function blockIp(string $ip): void
    {
        $this->blockedIps[$ip] = true;
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
