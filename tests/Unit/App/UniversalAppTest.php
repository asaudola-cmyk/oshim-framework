<?php
declare(strict_types=1);

namespace Tests\Unit\App;

use Oshim\Testing\TestCase;
use Oshim\App\AppManifest;
use Oshim\App\AppGenerator;
use Oshim\App\UniversalAppEngine;
use Oshim\Compiler\UniversalPackager;

class UniversalAppTest extends TestCase
{
    public function testAppManifestCreationAndSerialization(): void
    {
        $manifest = AppManifest::make('Enterprise ERP', 'fullstack');
        $this->assertSame('Enterprise ERP', $manifest->getName());
        $this->assertSame('enterprise.erp', $manifest->getId());
        $this->assertSame('fullstack', $manifest->getType());
        $this->assertSame('1.0.0', $manifest->getVersion());

        $data = $manifest->toArray();
        $this->assertIsArray($data);
        $this->assertSame('enterprise.erp', $data['id']);
        $this->assertContains('android', $data['targets']);
        $this->assertContains('windows', $data['targets']);
    }

    public function testAppGeneratorScaffolding(): void
    {
        $project = AppGenerator::createProject('FintechApp', 'mobile');
        $this->assertSame('CREATED', $project['status']);
        $this->assertSame('FintechApp', $project['app_name']);
        $this->assertSame('mobile', $project['type']);
        $this->assertStringContainsString('FintechApp', $project['entrypoint_code']);
        $this->assertStringContainsString('OSHIM Universal Application Entrypoint', $project['entrypoint_code']);
        $this->assertStringContainsString('OSHIM Sovereign Master Framework', $project['readme']);
    }

    public function testUniversalAppEnginePlatformCapabilities(): void
    {
        $platform = UniversalAppEngine::detectCurrentPlatform();
        $this->assertNotEmpty($platform);

        $caps = UniversalAppEngine::getPlatformCapabilities();
        $this->assertIsArray($caps);
        $this->assertArrayHasKey('supported_targets', $caps);
        $this->assertArrayHasKey('android', $caps['supported_targets']);
        $this->assertArrayHasKey('windows', $caps['supported_targets']);
        $this->assertArrayHasKey('mac', $caps['supported_targets']);
        $this->assertArrayHasKey('linux', $caps['supported_targets']);
    }

    public function testUniversalPackagerAllPlatforms(): void
    {
        $manifest = AppManifest::make('SuperCloud', 'fullstack');
        $bundle = UniversalPackager::bundlePlatform('all', $manifest);

        $this->assertSame('SUCCESS', $bundle['status']);
        $this->assertSame('all', $bundle['requested_platform']);
        $this->assertArrayHasKey('android', $bundle['bundles']);
        $this->assertArrayHasKey('ios', $bundle['bundles']);
        $this->assertArrayHasKey('windows', $bundle['bundles']);
        $this->assertArrayHasKey('mac', $bundle['bundles']);
        $this->assertArrayHasKey('linux', $bundle['bundles']);
        $this->assertArrayHasKey('web', $bundle['bundles']);

        $this->assertSame('COMPILED_READY', $bundle['bundles']['android']['status']);
        $this->assertSame('COMPILED_READY', $bundle['bundles']['windows']['status']);
        $this->assertSame('COMPILED_READY', $bundle['bundles']['mac']['status']);
        $this->assertSame('COMPILED_READY', $bundle['bundles']['linux']['status']);
    }
}
