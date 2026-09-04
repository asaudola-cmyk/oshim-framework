<?php
declare(strict_types=1);

namespace Oshim\Dns\Zone;

use Oshim\Dns\Records\ResourceRecord;

/**
 * Storage contract for Authoritative DNS Zones and Resource Records.
 */
interface ZoneRepositoryInterface
{
    /**
     * Retrieves a zone by exact domain name.
     */
    public function getZone(string $domain): ?Zone;

    /**
     * Checks if a zone exists in the repository.
     */
    public function hasZone(string $domain): bool;

    /**
     * Saves or updates a zone.
     */
    public function saveZone(Zone $zone): void;

    /**
     * Deletes a zone by domain name.
     */
    public function deleteZone(string $domain): bool;

    /**
     * Lists all hosted zones.
     *
     * @return array<string, Zone>
     */
    public function listZones(): array;

    /**
     * Finds the longest matching authoritative zone for a queried domain name.
     */
    public function findBestMatchingZone(string $domain): ?Zone;

    /**
     * Adds a record to a zone.
     */
    public function addRecord(string $zoneName, ResourceRecord $record): void;

    /**
     * Removes a record from a zone by record ID.
     */
    public function removeRecord(string $zoneName, string $recordId): bool;
}
