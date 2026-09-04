<?php
declare(strict_types=1);

namespace Oshim\Dns\Zone;

use PDO;
use Oshim\Dns\Records\RecordType;
use Oshim\Dns\Records\ResourceRecord;

/**
 * SQLite-backed Persistent Zone Repository with schema auto-initialization.
 */
class SqliteZoneRepository implements ZoneRepositoryInterface
{
    private PDO $pdo;

    public function __construct(string|PDO $database = ':memory:')
    {
        if ($database instanceof PDO) {
            $this->pdo = $database;
        } else {
            $this->pdo = new PDO("sqlite:" . $database, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }

        $this->initializeSchema();
    }

    private function initializeSchema(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS dns_zones (
                id VARCHAR(36) PRIMARY KEY,
                name VARCHAR(255) UNIQUE NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
                serial INTEGER NOT NULL DEFAULT 1,
                default_ttl INTEGER NOT NULL DEFAULT 3600,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS dns_records (
                id VARCHAR(36) PRIMARY KEY,
                zone_id VARCHAR(36) NOT NULL,
                name VARCHAR(255) NOT NULL,
                type VARCHAR(10) NOT NULL,
                ttl INTEGER NOT NULL DEFAULT 3600,
                data TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                FOREIGN KEY (zone_id) REFERENCES dns_zones(id) ON DELETE CASCADE
            );

            CREATE INDEX IF NOT EXISTS idx_dns_records_zone_lookup ON dns_records(zone_id, name, type);
        ");
    }

    public function getZone(string $domain): ?Zone
    {
        $domain = strtolower(trim($domain, '.'));
        $stmt = $this->pdo->prepare("SELECT * FROM dns_zones WHERE name = :name LIMIT 1");
        $stmt->execute(['name' => $domain]);
        $zoneRow = $stmt->fetch();

        if (!$zoneRow) {
            return null;
        }

        $recStmt = $this->pdo->prepare("SELECT * FROM dns_records WHERE zone_id = :zone_id");
        $recStmt->execute(['zone_id' => $zoneRow['id']]);
        $recordRows = $recStmt->fetchAll();

        $records = [];
        foreach ($recordRows as $row) {
            $decodedData = json_decode($row['data'], true);
            $data = json_last_error() === JSON_ERROR_NONE ? $decodedData : $row['data'];
            $records[] = new ResourceRecord(
                $row['name'],
                $row['type'],
                $data,
                (int)$row['ttl'],
                RecordType::CLASS_IN,
                $row['id']
            );
        }

        return new Zone($zoneRow['name'], (int)$zoneRow['default_ttl'], (int)$zoneRow['serial'], $records);
    }

    public function hasZone(string $domain): bool
    {
        $domain = strtolower(trim($domain, '.'));
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM dns_zones WHERE name = :name");
        $stmt->execute(['name' => $domain]);
        return ((int)$stmt->fetchColumn()) > 0;
    }

    public function saveZone(Zone $zone): void
    {
        $name = $zone->getName();
        $now = gmdate('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare("SELECT id FROM dns_zones WHERE name = :name LIMIT 1");
        $stmt->execute(['name' => $name]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            $updateStmt = $this->pdo->prepare("
                UPDATE dns_zones SET serial = :serial, default_ttl = :default_ttl, updated_at = :updated_at WHERE id = :id
            ");
            $updateStmt->execute([
                'serial' => $zone->getSerial(),
                'default_ttl' => $zone->getDefaultTtl(),
                'updated_at' => $now,
                'id' => $existingId,
            ]);
            $zoneId = (string)$existingId;

            // Delete old records and re-insert
            $delStmt = $this->pdo->prepare("DELETE FROM dns_records WHERE zone_id = :zone_id");
            $delStmt->execute(['zone_id' => $zoneId]);
        } else {
            $zoneId = bin2hex(random_bytes(16));
            $insertStmt = $this->pdo->prepare("
                INSERT INTO dns_zones (id, name, status, serial, default_ttl, created_at, updated_at)
                VALUES (:id, :name, 'ACTIVE', :serial, :default_ttl, :created_at, :updated_at)
            ");
            $insertStmt->execute([
                'id' => $zoneId,
                'name' => $name,
                'serial' => $zone->getSerial(),
                'default_ttl' => $zone->getDefaultTtl(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $insRec = $this->pdo->prepare("
            INSERT INTO dns_records (id, zone_id, name, type, ttl, data, created_at, updated_at)
            VALUES (:id, :zone_id, :name, :type, :ttl, :data, :created_at, :updated_at)
        ");

        foreach ($zone->getRecords() as $rec) {
            $recId = $rec->getId() ?: bin2hex(random_bytes(16));
            $rec->setId($recId);
            $jsonData = is_scalar($rec->getData()) ? (string)$rec->getData() : (string)json_encode($rec->getData());

            $insRec->execute([
                'id' => $recId,
                'zone_id' => $zoneId,
                'name' => $rec->getName(),
                'type' => $rec->getTypeName(),
                'ttl' => $rec->getTtl(),
                'data' => $jsonData,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function deleteZone(string $domain): bool
    {
        $domain = strtolower(trim($domain, '.'));
        $stmt = $this->pdo->prepare("DELETE FROM dns_zones WHERE name = :name");
        $stmt->execute(['name' => $domain]);
        return $stmt->rowCount() > 0;
    }

    public function listZones(): array
    {
        $stmt = $this->pdo->query("SELECT name FROM dns_zones");
        $zones = [];
        while ($name = $stmt->fetchColumn()) {
            $zone = $this->getZone((string)$name);
            if ($zone !== null) {
                $zones[(string)$name] = $zone;
            }
        }
        return $zones;
    }

    public function findBestMatchingZone(string $domain): ?Zone
    {
        $domain = strtolower(trim($domain, '.'));
        $zone = $this->getZone($domain);
        if ($zone !== null) {
            return $zone;
        }

        $parts = explode('.', $domain);
        $count = count($parts);

        for ($i = 1; $i < $count; $i++) {
            $parent = implode('.', array_slice($parts, $i));
            $zone = $this->getZone($parent);
            if ($zone !== null) {
                return $zone;
            }
        }

        return null;
    }

    public function addRecord(string $zoneName, ResourceRecord $record): void
    {
        $zone = $this->getZone($zoneName);
        if ($zone === null) {
            $zone = new Zone($zoneName);
        }
        $zone->addRecord($record);
        $this->saveZone($zone);
    }

    public function removeRecord(string $zoneName, string $recordId): bool
    {
        $zone = $this->getZone($zoneName);
        if ($zone === null) {
            return false;
        }
        $removed = $zone->removeRecord($recordId);
        if ($removed) {
            $this->saveZone($zone);
            return true;
        }
        return false;
    }
}
