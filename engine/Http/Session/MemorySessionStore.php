<?php
declare(strict_types=1);

namespace Oshim\Http\Session;

class MemorySessionStore implements SessionStoreInterface
{
    /** @var array<string, array{data: array, expires_at: int}> */
    private array $storage = [];

    public function read(string $sessionId): array
    {
        if (!isset($this->storage[$sessionId])) {
            return [];
        }
        $item = $this->storage[$sessionId];
        if (time() > $item['expires_at']) {
            unset($this->storage[$sessionId]);
            return [];
        }
        return $item['data'];
    }

    public function write(string $sessionId, array $data, int $lifetime = 7200): bool
    {
        $this->storage[$sessionId] = [
            'data' => $data,
            'expires_at' => time() + $lifetime,
        ];
        return true;
    }

    public function destroy(string $sessionId): bool
    {
        unset($this->storage[$sessionId]);
        return true;
    }

    public function gc(int $maxLifetime): int
    {
        $now = time();
        $deleted = 0;
        foreach ($this->storage as $id => $item) {
            if ($now > $item['expires_at']) {
                unset($this->storage[$id]);
                $deleted++;
            }
        }
        return $deleted;
    }
}
