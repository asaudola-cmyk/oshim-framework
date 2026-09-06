<?php
declare(strict_types=1);

namespace Oshim\Storage\Engine;

use RuntimeException;

/**
 * 👑 Sovereign OSHIM Embedded Key-Value Storage Engine (Redis Replacement)
 * 
 * WHY: Traditional frameworks force developers to install and manage Redis or Memcached.
 * SovereignStore provides an in-memory O(1) key-value engine with Write-Ahead Logging (WAL),
 * atomic file-locking, and TTL expiration in 100% Pure PHP with zero external dependencies.
 */
class SovereignStore
{
    protected string $storagePath;
    protected string $walPath;
    protected array $memTable = [];
    protected array $ttlIndex = [];
    protected $walFileHandle = null;

    public function __construct(?string $storageDir = null)
    {
        $dir = $storageDir ?? (defined('OSHIM_BASE_PATH') ? OSHIM_BASE_PATH : dirname(__DIR__, 3)) . '/storage/data';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->storagePath = $dir . '/sovereign_store.db';
        $this->walPath = $dir . '/sovereign_store.wal';

        $this->recoverFromWal();
        $this->openWal();
    }

    /**
     * O(1) Key-Value Write with WAL durability.
     * 
     * @param string $key Identifier
     * @param mixed $value Any serializable PHP payload
     * @param int $ttlSeconds Time to live in seconds (0 = forever)
     */
    public function set(string $key, mixed $value, int $ttlSeconds = 0): bool
    {
        $expiry = $ttlSeconds > 0 ? time() + $ttlSeconds : 0;
        
        $this->memTable[$key] = $value;
        if ($expiry > 0) {
            $this->ttlIndex[$key] = $expiry;
        } else {
            unset($this->ttlIndex[$key]);
        }

        // Append to Write-Ahead Log (WAL)
        $logRecord = json_encode([
            'op' => 'SET',
            'k' => $key,
            'v' => $value,
            'exp' => $expiry,
            't' => microtime(true)
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

        if ($this->walFileHandle && is_resource($this->walFileHandle)) {
            flock($this->walFileHandle, LOCK_EX);
            fwrite($this->walFileHandle, $logRecord);
            fflush($this->walFileHandle);
            flock($this->walFileHandle, LOCK_UN);
        }

        return true;
    }

    /**
     * O(1) Key-Value Lookup with lazy TTL expiration.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // 1. Check TTL Expiration
        if (isset($this->ttlIndex[$key])) {
            if (time() >= $this->ttlIndex[$key]) {
                $this->delete($key);
                return $default;
            }
        }

        // 2. Fetch from In-Memory Table
        return $this->memTable[$key] ?? $default;
    }

    /**
     * Checks key existence and validity.
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Atomic deletion with WAL tombstone.
     */
    public function delete(string $key): bool
    {
        unset($this->memTable[$key], $this->ttlIndex[$key]);

        $logRecord = json_encode([
            'op' => 'DEL',
            'k' => $key,
            't' => microtime(true)
        ]) . "\n";

        if ($this->walFileHandle && is_resource($this->walFileHandle)) {
            flock($this->walFileHandle, LOCK_EX);
            fwrite($this->walFileHandle, $logRecord);
            fflush($this->walFileHandle);
            flock($this->walFileHandle, LOCK_UN);
        }

        return true;
    }

    /**
     * Flushes memory table into a compact snapshot and truncates the WAL.
     * WHY: Prevents WAL file from growing indefinitely.
     */
    public function compact(): void
    {
        if ($this->walFileHandle && is_resource($this->walFileHandle)) {
            fclose($this->walFileHandle);
            $this->walFileHandle = null;
        }

        // Clean expired keys before dumping snapshot
        $now = time();
        foreach ($this->ttlIndex as $k => $exp) {
            if ($now >= $exp) {
                unset($this->memTable[$k], $this->ttlIndex[$k]);
            }
        }

        // Atomic snapshot write
        $snapshot = serialize([
            'mem' => $this->memTable,
            'ttl' => $this->ttlIndex
        ]);
        file_put_contents($this->storagePath . '.tmp', $snapshot, LOCK_EX);
        rename($this->storagePath . '.tmp', $this->storagePath);

        // Truncate WAL
        file_put_contents($this->walPath, '', LOCK_EX);
        $this->openWal();
    }

    /**
     * Replays Write-Ahead Log to recover in-memory state on boot.
     */
    protected function recoverFromWal(): void
    {
        // 1. Load latest base snapshot if present
        if (file_exists($this->storagePath)) {
            $raw = file_get_contents($this->storagePath);
            if ($raw) {
                $snapshot = @unserialize($raw);
                if (is_array($snapshot)) {
                    $this->memTable = $snapshot['mem'] ?? [];
                    $this->ttlIndex = $snapshot['ttl'] ?? [];
                }
            }
        }

        // 2. Replay incremental delta operations from WAL
        if (!file_exists($this->walPath)) {
            return;
        }

        $handle = fopen($this->walPath, 'r');
        if (!$handle) {
            return;
        }

        while (($line = fgets($handle)) !== false) {
            $record = json_decode(trim($line), true);
            if (!is_array($record) || !isset($record['op'], $record['k'])) {
                continue;
            }

            if ($record['op'] === 'SET') {
                $this->memTable[$record['k']] = $record['v'] ?? null;
                $expiry = (int)($record['exp'] ?? 0);
                if ($expiry > 0) {
                    $this->ttlIndex[$record['k']] = $expiry;
                }
            } elseif ($record['op'] === 'DEL') {
                unset($this->memTable[$record['k']], $this->ttlIndex[$record['k']]);
            }
        }

        fclose($handle);
    }

    protected function openWal(): void
    {
        $this->walFileHandle = fopen($this->walPath, 'a');
    }

    public function __destruct()
    {
        if ($this->walFileHandle && is_resource($this->walFileHandle)) {
            fclose($this->walFileHandle);
        }
    }
}
