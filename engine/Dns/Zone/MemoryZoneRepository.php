<?php
declare(strict_types=1);

namespace Oshim\Dns\Zone;

use Oshim\Dns\Records\ResourceRecord;

/**
 * In-Memory Zone Repository for high-speed lookups, testing, and caching.
 */
class MemoryZoneRepository implements ZoneRepositoryInterface
{
    /** @var array<string, Zone> */
    private array $zones = [];

    /**
     * @param array<string, Zone> $initialZones
     */
    public function __construct(array $initialZones = [])
    {
        foreach ($initialZones as $zone) {
            $this->saveZone($zone);
        }
    }

    public function getZone(string $domain): ?Zone
    {
        $domain = strtolower(trim($domain, '.'));
        return $this->zones[$domain] ?? null;
    }

    public function hasZone(string $domain): bool
    {
        $domain = strtolower(trim($domain, '.'));
        return isset($this->zones[$domain]);
    }

    public function saveZone(Zone $zone): void
    {
        $this->zones[$zone->getName()] = $zone;
    }

    public function deleteZone(string $domain): bool
    {
        $domain = strtolower(trim($domain, '.'));
        if (isset($this->zones[$domain])) {
            unset($this->zones[$domain]);
            return true;
        }
        return false;
    }

    public function listZones(): array
    {
        return $this->zones;
    }

    public function findBestMatchingZone(string $domain): ?Zone
    {
        $domain = strtolower(trim($domain, '.'));
        if (isset($this->zones[$domain])) {
            return $this->zones[$domain];
        }

        $parts = explode('.', $domain);
        $count = count($parts);

        for ($i = 1; $i < $count; $i++) {
            $parent = implode('.', array_slice($parts, $i));
            if (isset($this->zones[$parent])) {
                return $this->zones[$parent];
            }
        }

        return null;
    }

    public function addRecord(string $zoneName, ResourceRecord $record): void
    {
        $zoneName = strtolower(trim($zoneName, '.'));
        if (!isset($this->zones[$zoneName])) {
            $this->zones[$zoneName] = new Zone($zoneName);
        }
        $this->zones[$zoneName]->addRecord($record);
    }

    public function removeRecord(string $zoneName, string $recordId): bool
    {
        $zoneName = strtolower(trim($zoneName, '.'));
        if (isset($this->zones[$zoneName])) {
            return $this->zones[$zoneName]->removeRecord($recordId);
        }
        return false;
    }
}
