<?php
declare(strict_types=1);

namespace Oshim\Http\Session;

use Oshim\Security\Cipher;
use PDO;

class SqliteSessionStore implements SessionStoreInterface
{
    public function __construct(
        protected PDO $pdo,
        protected string $appKey,
        protected string $table = 'sessions'
    ) {
        $this->ensureTable();
    }

    protected function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            id VARCHAR(64) PRIMARY KEY,
            payload TEXT NOT NULL,
            last_activity INTEGER NOT NULL,
            user_id INTEGER NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL
        );
        CREATE INDEX IF NOT EXISTS idx_sessions_activity ON {$this->table}(last_activity);";

        $this->pdo->exec($sql);
    }

    public function read(string $sessionId): array
    {
        $stmt = $this->pdo->prepare("SELECT payload, last_activity FROM {$this->table} WHERE id = :id");
        $stmt->execute(['id' => $sessionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || empty($row['payload'])) {
            return [];
        }

        try {
            $decrypted = Cipher::decrypt($row['payload'], $this->appKey, $sessionId);
            $data = unserialize($decrypted, ['allowed_classes' => true]);
            return is_array($data) ? $data : [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function write(string $sessionId, array $data, int $lifetimeSeconds): bool
    {
        $serialized = serialize($data);
        $encrypted = Cipher::encrypt($serialized, $this->appKey, $sessionId);
        $now = time();
        $userId = $data['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $stmt = $this->pdo->prepare("INSERT OR REPLACE INTO {$this->table} (id, payload, last_activity, user_id, ip_address, user_agent)
            VALUES (:id, :payload, :last_activity, :user_id, :ip_address, :user_agent)");

        return $stmt->execute([
            'id'            => $sessionId,
            'payload'       => $encrypted,
            'last_activity' => $now,
            'user_id'       => $userId,
            'ip_address'    => $ip,
            'user_agent'    => $userAgent,
        ]);
    }

    public function destroy(string $sessionId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        return $stmt->execute(['id' => $sessionId]);
    }

    public function gc(int $maxLifetimeSeconds): int
    {
        $threshold = time() - $maxLifetimeSeconds;
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE last_activity < :threshold");
        $stmt->execute(['threshold' => $threshold]);
        return $stmt->rowCount();
    }
}
