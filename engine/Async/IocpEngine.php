<?php
declare(strict_types=1);

namespace Oshim\Async;

class IocpEngine
{
    private bool $isAvailable;
    private array $completionPorts = [];
    private int $portCounter = 0;

    public function __construct()
    {
        $this->isAvailable = (PHP_OS_FAMILY === 'Windows');
    }

    public function isSupported(): bool
    {
        return $this->isAvailable;
    }

    public function bindSocket($stream, callable $completionHandler): int
    {
        $portId = ++$this->portCounter;
        $this->completionPorts[$portId] = [
            'stream' => $stream,
            'handler' => $completionHandler,
        ];
        return $portId;
    }

    public function getQueuedCompletionStatus(int $timeoutMs = 10): int
    {
        $read = [];
        $write = [];
        $except = [];

        foreach ($this->completionPorts as $id => $port) {
            $read[] = $port['stream'];
        }

        if (empty($read)) {
            usleep($timeoutMs * 1000);
            return 0;
        }

        $res = @stream_select($read, $write, $except, 0, $timeoutMs * 1000);
        if ($res > 0) {
            foreach ($this->completionPorts as $id => $port) {
                if (in_array($port['stream'], $read, true)) {
                    ($port['handler'])($port['stream']);
                }
            }
        }
        return $res === false ? 0 : $res;
    }

    public function getStats(): array
    {
        return [
            'engine' => 'iocp_win32',
            'bound_ports' => count($this->completionPorts),
            'supported' => $this->isAvailable,
        ];
    }
}
