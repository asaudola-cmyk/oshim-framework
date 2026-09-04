<?php
declare(strict_types=1);

namespace Oshim\Async;

class KqueueEngine
{
    private bool $isAvailable;
    private array $registeredEvents = [];
    private int $eventCount = 0;

    public function __construct()
    {
        $family = PHP_OS_FAMILY;
        $this->isAvailable = ($family === 'Darwin' || $family === 'BSD');
    }

    public function isSupported(): bool
    {
        return $this->isAvailable;
    }

    public function registerSocket($stream, int $filter, callable $callback): int
    {
        $id = ++$this->eventCount;
        $this->registeredEvents[$id] = [
            'stream' => $stream,
            'filter' => $filter, // EVFILT_READ / EVFILT_WRITE
            'callback' => $callback,
        ];
        return $id;
    }

    public function poll(int $timeoutMs = 10): int
    {
        $read = [];
        $write = [];
        $except = [];

        foreach ($this->registeredEvents as $id => $ev) {
            $read[] = $ev['stream'];
        }

        if (empty($read)) {
            usleep($timeoutMs * 1000);
            return 0;
        }

        $res = @stream_select($read, $write, $except, 0, $timeoutMs * 1000);
        if ($res > 0) {
            foreach ($this->registeredEvents as $id => $ev) {
                if (in_array($ev['stream'], $read, true)) {
                    ($ev['callback'])($ev['stream']);
                }
            }
        }
        return $res === false ? 0 : $res;
    }

    public function getStats(): array
    {
        return [
            'engine' => 'kqueue',
            'registered_events' => count($this->registeredEvents),
            'supported' => $this->isAvailable,
        ];
    }
}
