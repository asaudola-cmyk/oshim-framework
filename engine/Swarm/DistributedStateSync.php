<?php
declare(strict_types=1);

namespace Oshim\Swarm;

class DistributedStateSync
{
    /** @var array<string, mixed> */
    private array $store = [];

    /** @var array<string, int> */
    private array $versions = [];

    /**
     * Set a key-value pair, monotonically incrementing version unless explicit version is provided.
     *
     * @param string $key
     * @param mixed $value
     * @param int|null $version
     * @return int The updated version
     */
    public function set(string $key, mixed $value, ?int $version = null): int
    {
        $currentVersion = $this->versions[$key] ?? 0;
        $newVersion = $version ?? ($currentVersion + 1);

        if ($newVersion >= $currentVersion) {
            $this->store[$key] = $value;
            $this->versions[$key] = $newVersion;
        }

        return $this->versions[$key];
    }

    /**
     * Get a value by key.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store[$key] ?? $default;
    }

    /**
     * Check if a key exists in state store.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->store);
    }

    /**
     * Delete a key from state store and increment version (tombstone).
     *
     * @param string $key
     * @return void
     */
    public function delete(string $key): void
    {
        unset($this->store[$key]);
        $this->versions[$key] = ($this->versions[$key] ?? 0) + 1;
    }

    /**
     * Get entire key-value store.
     *
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        return $this->store;
    }

    /**
     * Get all key versions.
     *
     * @return array<string, int>
     */
    public function getVersions(): array
    {
        return $this->versions;
    }

    /**
     * Merge incoming state delta into local store based on monotonic versioning.
     *
     * @param array<string, mixed> $incomingStore
     * @param array<string, int> $incomingVersions
     * @return list<string> List of keys that were updated
     */
    public function mergeDelta(array $incomingStore, array $incomingVersions): array
    {
        $updatedKeys = [];
        foreach ($incomingStore as $k => $v) {
            $incomingVer = $incomingVersions[$k] ?? 1;
            $localVer = $this->versions[$k] ?? 0;

            if ($incomingVer > $localVer) {
                $this->store[$k] = $v;
                $this->versions[$k] = $incomingVer;
                $updatedKeys[] = $k;
            }
        }
        return $updatedKeys;
    }

    /**
     * Get state delta since the provided version map.
     *
     * @param array<string, int> $knownVersions
     * @return array{store: array<string, mixed>, versions: array<string, int>}
     */
    public function getDeltaSince(array $knownVersions): array
    {
        $deltaStore = [];
        $deltaVersions = [];

        foreach ($this->versions as $key => $version) {
            $knownVersion = $knownVersions[$key] ?? 0;
            if ($version > $knownVersion) {
                if (array_key_exists($key, $this->store)) {
                    $deltaStore[$key] = $this->store[$key];
                }
                $deltaVersions[$key] = $version;
            }
        }

        return [
            'store' => $deltaStore,
            'versions' => $deltaVersions,
        ];
    }
}
