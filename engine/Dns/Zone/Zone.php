<?php
declare(strict_types=1);

namespace Oshim\Dns\Zone;

use Oshim\Dns\Records\RecordType;
use Oshim\Dns\Records\ResourceRecord;

/**
 * Authoritative DNS Zone Aggregate Entity.
 */
class Zone
{
    private string $name;
    private int $defaultTtl;
    private int $serial;
    /** @var list<ResourceRecord> */
    private array $records = [];

    public function __construct(
        string $name,
        int $defaultTtl = 3600,
        int $serial = 1,
        array $records = []
    ) {
        $this->name = strtolower(trim($name, '.'));
        $this->defaultTtl = max(0, $defaultTtl);
        $this->serial = $serial;
        $this->records = $records;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDefaultTtl(): int
    {
        return $this->defaultTtl;
    }

    public function setDefaultTtl(int $ttl): self
    {
        $this->defaultTtl = max(0, $ttl);
        return $this;
    }

    public function getSerial(): int
    {
        return $this->serial;
    }

    public function setSerial(int $serial): self
    {
        $this->serial = $serial;
        return $this;
    }

    /**
     * @return list<ResourceRecord>
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    public function addRecord(ResourceRecord $record): self
    {
        $this->records[] = $record;
        return $this;
    }

    public function removeRecord(string $id): bool
    {
        $initial = count($this->records);
        $this->records = array_values(array_filter(
            $this->records,
            fn(ResourceRecord $r) => $r->getId() !== $id
        ));
        return count($this->records) < $initial;
    }

    /**
     * Finds all records matching a domain name and optional record type.
     *
     * @return list<ResourceRecord>
     */
    public function findRecords(string $name, int|string|null $type = null): array
    {
        $name = strtolower(trim($name, '.'));
        $typeInt = $type !== null ? (is_int($type) ? $type : RecordType::nameToType($type)) : null;

        $matches = [];
        foreach ($this->records as $r) {
            $recordName = strtolower(trim($r->getName(), '.'));

            // Handle apex relative names
            if ($recordName === '@' || $recordName === '') {
                $recordName = $this->name;
            } elseif (!str_ends_with($recordName, '.' . $this->name) && $recordName !== $this->name) {
                // If it's a relative subdomain (e.g. 'www'), expand to 'www.example.com'
                $recordName = $recordName . '.' . $this->name;
            }

            if ($recordName === $name) {
                if ($typeInt === null || $typeInt === RecordType::ANY || $r->getType() === $typeInt) {
                    $matches[] = $r;
                }
            }
        }

        return $matches;
    }

    public function hasRecordsForName(string $name): bool
    {
        return !empty($this->findRecords($name));
    }

    public function getSoaRecord(): ?ResourceRecord
    {
        $matches = $this->findRecords($this->name, RecordType::SOA);
        return !empty($matches) ? $matches[0] : null;
    }

    /**
     * @return list<ResourceRecord>
     */
    public function getNsRecords(): array
    {
        return $this->findRecords($this->name, RecordType::NS);
    }
}
