<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Tenant\Tenant;
use Oshim\Tenant\TenantResolver;
use Oshim\Tenant\TenantManager;
use Oshim\Http\Request;

final class MultiTenancyTest extends TestCase
{
    public function testTenantResolutionAndContextSwitch(): void
    {
        $tenant1 = new Tenant('acme', 'Acme Corp', 'acme', 'acme.com');
        $tenant2 = new Tenant('globex', 'Globex Corp', 'globex', null);

        $manager = new TenantManager();
        $manager->register($tenant1);
        $manager->register($tenant2);

        $this->assertCount(2, $manager->all());
        $this->assertSame('tenant_acme', $tenant1->getDatabaseName());

        // Test Header Resolution
        $req1 = Request::create('/', 'GET', [], [], [], [
            'HTTP_X_TENANT_ID' => 'acme',
        ]);
        $resolved1 = TenantResolver::resolveFromRequest($req1, $manager->all());
        $this->assertNotNull($resolved1);
        $this->assertSame('acme', $resolved1->id);

        // Test Scoped Context Run
        $result = TenantManager::runInTenantContext($tenant1, function ($curr) {
            return TenantManager::id();
        });

        $this->assertSame('acme', $result);
        $this->assertNull(TenantManager::current());
    }
}
