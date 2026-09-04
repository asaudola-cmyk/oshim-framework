<?php
declare(strict_types=1);

namespace Oshim\Ui\Multiplayer;

/**
 * Versioned In-Memory Shared State Store with Last-Write-Wins (LWW) Conflict Resolution.
 */
class SharedStateStore
{
    /**
     * @var array<string, array{value: mixed, version: int, updatedAt: float, updatedBy: string}>
     */
    private array $data = [];

    public function __construct(array $initialState = [])
    {
        $now = microtime(true);
        foreach ($initialState as $key => $val) {
            $this->data[$key] = [
                'value' => $val,
                'version' => 1,
                'updatedAt' => $now,
                'updatedBy' => 'system',
            ];
        }
    }

    /**
     * Set a key-value pair and return the mutation record.
     *
     * @return array{key: string, value: mixed, version: int, updatedAt: float, updatedBy: string}
     */
    public function set(string $key, mixed $value, string $peerId): array
    {
        $current = $this->data[$key] ?? null;
        $version = $current !== null ? $current['version'] + 1 : 1;
        $updatedAt = microtime(true);

        $record = [
            'key' => $key,
            'value' => $value,
            'version' => $version,
            'updatedAt' => $updatedAt,
            'updatedBy' => $peerId,
        ];

        $this->data[$key] = [
            'value' => $value,
            'version' => $version,
            'updatedAt' => $updatedAt,
            'updatedBy' => $peerId,
        ];

        return $record;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return isset($this->data[$key]) ? $this->data[$key]['value'] : $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function delete(string $key, string $peerId): ?array
    {
        if (!isset($this->data[$key])) {
            return null;
        }

        $current = $this->data[$key];
        unset($this->data[$key]);

        return [
            'key' => $key,
            'value' => null,
            'version' => $current['version'] + 1,
            'updatedAt' => microtime(true),
            'updatedBy' => $peerId,
            'deleted' => true,
        ];
    }

    /**
     * Apply remote mutation using LWW (Last-Write-Wins) conflict resolution.
     *
     * @param array{key: string, value: mixed, version: int, updatedAt: float, updatedBy: string, deleted?: bool} $mutation
     * @return bool True if mutation was applied, False if rejected due to conflict
     */
    public function applyMutation(array $mutation): bool
    {
        $key = (string) ($mutation['key'] ?? '');
        if ($key === '') {
            return false;
        }

        $newVersion = (int) ($mutation['version'] ?? 1);
        $newTime = (float) ($mutation['updatedAt'] ?? 0.0);
        $isDeleted = !empty($mutation['deleted']);

        if (isset($this->data[$key])) {
            $curr = $this->data[$key];
            // If existing mutation is strictly newer, reject the older incoming mutation
            if ($curr['version'] > $newVersion) {
                return false;
            }
            if ($curr['version'] === $newVersion && $curr['updatedAt'] >= $newTime) {
                return false;
            }
        }

        if ($isDeleted) {
            unset($this->data[$key]);
            return true;
        }

        $this->data[$key] = [
            'value' => $mutation['value'] ?? null,
            'version' => $newVersion,
            'updatedAt' => $newTime,
            'updatedBy' => (string) ($mutation['updatedBy'] ?? 'unknown'),
        ];

        return true;
    }

    /**
     * Get a full snapshot of all current values.
     *
     * @return array<string, mixed>
     */
    public function getValues(): array
    {
        $out = [];
        foreach ($this->data as $key => $meta) {
            $out[$key] = $meta['value'];
        }
        return $out;
    }

    /**
     * Get full versioned state metadata.
     *
     * @return array<string, array{value: mixed, version: int, updatedAt: float, updatedBy: string}>
     */
    public function getSnapshot(): array
    {
        return $this->data;
    }

    public function count(): int
    {
        return count($this->data);
    }
}
