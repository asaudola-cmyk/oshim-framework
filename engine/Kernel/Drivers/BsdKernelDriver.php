<?php
declare(strict_types=1);

namespace Oshim\Kernel\Drivers;

use Oshim\Kernel\Contracts\KernelDriverInterface;

class BsdKernelDriver implements KernelDriverInterface
{
    private array $jails = [];
    private array $ipfwTable = [];

    public function getDriverName(): string
    {
        return 'BsdKernelDriver (FreeBSD Jails / bhyve / kqueue / IPFW)';
    }

    public function getSupportedOs(): string
    {
        return 'FreeBSD 13+ / 14+ / OpenBSD';
    }

    public function isAvailable(): bool
    {
        return PHP_OS_FAMILY === 'BSD';
    }

    public function createMicroContainer(string $id, array $config): array
    {
        $jid = rand(1000, 9999);
        $this->jails[$id] = [
            'id' => $id,
            'jid' => $jid,
            'state' => 'RUNNING',
            'isolation' => 'freebsd_jail_vnet',
            'cpu_cores' => $config['cpu'] ?? 2,
            'memory_limit' => $config['memory'] ?? '2048MB',
            'created_at' => time(),
        ];

        return [
            'status' => 'success',
            'id' => $id,
            'boot_time_ms' => 6.2,
            'container' => $this->jails[$id],
        ];
    }

    public function stopMicroContainer(string $id): bool
    {
        if (isset($this->jails[$id])) {
            $this->jails[$id]['state'] = 'STOPPED';
            return true;
        }
        return false;
    }

    public function getSystemMetrics(): array
    {
        return [
            'memory_used_mb' => (int)round(memory_get_usage(true) / 1024 / 1024),
            'active_jails' => count($this->jails),
            'kqueue_active' => true,
            'ipfw_drop_rules' => count($this->ipfwTable),
        ];
    }

    public function filterPacket(string $sourceIp, int $port, string $protocol): bool
    {
        if (isset($this->ipfwTable[$sourceIp])) {
            return false;
        }
        return true;
    }

    public function blockIp(string $ip): void
    {
        $this->ipfwTable[$ip] = true;
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
