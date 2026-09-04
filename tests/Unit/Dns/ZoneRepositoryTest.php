<?php
declare(strict_types=1);

namespace Oshim\Tests\Unit\Dns;

use Oshim\Dns\Records\RecordType;
use Oshim\Dns\Records\ResourceRecord;
use Oshim\Dns\Zone\MemoryZoneRepository;
use Oshim\Dns\Zone\SqliteZoneRepository;
use Oshim\Dns\Zone\Zone;
use Oshim\Tests\Harness\TestCase;

class ZoneRepositoryTest extends TestCase
{
    public function testMemoryZoneRepositoryOperations(): void
    {
        $repo = new MemoryZoneRepository();
        $this->assertFalse($repo->hasZone('repo-test.com'));

        $zone = new Zone('repo-test.com', 3600, 1, [
            ResourceRecord::a('repo-test.com', '192.0.2.1'),
        ]);

        $repo->saveZone($zone);
        $this->assertTrue($repo->hasZone('repo-test.com'));
        $this->assertNotNull($repo->getZone('repo-test.com'));
        $this->assertCount(1, $repo->listZones());

        // Subdomain resolution
        $matched = $repo->findBestMatchingZone('deep.sub.api.repo-test.com');
        $this->assertNotNull($matched);
        $this->assertSame('repo-test.com', $matched->getName());

        // Add record
        $repo->addRecord('repo-test.com', ResourceRecord::aaaa('repo-test.com', '2001:db8::1'));
        $this->assertCount(2, $repo->getZone('repo-test.com')->getRecords());

        // Delete zone
        $this->assertTrue($repo->deleteZone('repo-test.com'));
        $this->assertFalse($repo->hasZone('repo-test.com'));
    }

    public function testSqliteZoneRepositoryPersistence(): void
    {
        $repo = new SqliteZoneRepository(':memory:');
        $this->assertFalse($repo->hasZone('sqlite-test.com'));

        $zone = new Zone('sqlite-test.com', 7200, 2026082901, [
            ResourceRecord::soa('sqlite-test.com', 'ns1.sqlite-test.com', 'admin.sqlite-test.com', 2026082901),
            ResourceRecord::a('sqlite-test.com', '192.0.2.55'),
            ResourceRecord::mx('sqlite-test.com', 10, 'mail.sqlite-test.com'),
        ]);

        $repo->saveZone($zone);
        $this->assertTrue($repo->hasZone('sqlite-test.com'));

        $fetchedZone = $repo->getZone('sqlite-test.com');
        $this->assertNotNull($fetchedZone);
        $this->assertSame(7200, $fetchedZone->getDefaultTtl());
        $this->assertSame(2026082901, $fetchedZone->getSerial());

        $aRecords = $fetchedZone->findRecords('sqlite-test.com', RecordType::A);
        $this->assertCount(1, $aRecords);
        $this->assertSame('192.0.2.55', $aRecords[0]->getData());

        $mxRecords = $fetchedZone->findRecords('sqlite-test.com', RecordType::MX);
        $this->assertCount(1, $mxRecords);
        $this->assertSame(10, $mxRecords[0]->getData()['preference']);

        // Best matching zone
        $bestMatch = $repo->findBestMatchingZone('app.staging.sqlite-test.com');
        $this->assertNotNull($bestMatch);
        $this->assertSame('sqlite-test.com', $bestMatch->getName());

        // Remove record
        $recId = $aRecords[0]->getId();
        $this->assertNotNull($recId);
        $this->assertTrue($repo->removeRecord('sqlite-test.com', (string)$recId));

        $updatedZone = $repo->getZone('sqlite-test.com');
        $this->assertCount(0, $updatedZone->findRecords('sqlite-test.com', RecordType::A));

        // Delete zone
        $this->assertTrue($repo->deleteZone('sqlite-test.com'));
        $this->assertNull($repo->getZone('sqlite-test.com'));
    }
}
