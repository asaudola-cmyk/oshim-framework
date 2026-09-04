<?php
declare(strict_types=1);

namespace Oshim\Http\Session;

interface SessionStoreInterface
{
    /**
     * Read session data for the given session ID.
     *
     * @param string $sessionId
     * @return array<string, mixed>
     */
    public function read(string $sessionId): array;

    /**
     * Write session data for the given session ID.
     *
     * @param string $sessionId
     * @param array<string, mixed> $data
     * @param int $lifetimeSeconds
     * @return bool
     */
    public function write(string $sessionId, array $data, int $lifetimeSeconds): bool;

    /**
     * Destroy session data for the given session ID.
     */
    public function destroy(string $sessionId): bool;

    /**
     * Garbage collect expired sessions.
     *
     * @param int $maxLifetimeSeconds
     * @return int Number of purged sessions
     */
    public function gc(int $maxLifetimeSeconds): int;
}
