<?php
declare(strict_types=1);

namespace Oshim\Kernel\Contracts;

interface KernelDriverInterface
{
    public function getDriverName(): string;
    public function getSupportedOs(): string;
    public function isAvailable(): bool;
    public function createMicroContainer(string $id, array $config): array;
    public function stopMicroContainer(string $id): bool;
    public function getSystemMetrics(): array;
    public function filterPacket(string $sourceIp, int $port, string $protocol): bool;
    public function multiplexSockets(array &$read, array &$write, array &$except, ?int $tvSec, ?int $tvUsec): int;
}
