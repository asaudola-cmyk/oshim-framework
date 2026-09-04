<?php
declare(strict_types=1);

namespace Tests\Unit\Plugins;

use Oshim\Testing\TestCase;
use Oshim\Plugins\PluginValidator;
use Oshim\Plugins\PluginSandbox;
use Oshim\Plugins\PluginInterface;

class SovereignPluginTest extends TestCase
{
    public function testPluginValidatorApprovesSovereignCode(): void
    {
        $cleanPluginCode = <<<'PHP'
<?php
namespace Community\Nagad;

use Oshim\Plugins\PluginInterface;
use Oshim\Database\DB;

class NagadGatewayPlugin implements PluginInterface
{
    public function getName(): string { return 'community/nagad'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getPermissions(): array { return ['database']; }
    public function boot(): void {
        // Pure PHP Logic
    }
}
PHP;
        $res = PluginValidator::validateSource($cleanPluginCode, 'clean_plugin');
        $this->assertTrue($res['valid']);
        $this->assertSame('VERIFIED_SOVEREIGN', $res['status']);
    }

    public function testPluginValidatorRejectsExternalVendorBloat(): void
    {
        $bloatedPluginCode = <<<'PHP'
<?php
require_once __DIR__ . '/vendor/autoload.php';
eval("$evil = 1;");
PHP;
        $res = PluginValidator::validateSource($bloatedPluginCode, 'bloated_plugin');
        $this->assertFalse($res['valid']);
        $this->assertSame('REJECTED_DEPENDENCY_OR_SECURITY_VIOLATION', $res['status']);
        $this->assertCount(2, $res['violations']);
    }

    public function testPluginSandboxExecution(): void
    {
        $sandbox = new PluginSandbox();

        $plugin = new class implements PluginInterface {
            public bool $booted = false;
            public function getName(): string { return 'charts/apex-lite'; }
            public function getVersion(): string { return '1.2.0'; }
            public function getPermissions(): array { return ['storage']; }
            public function boot(): void { $this->booted = true; }
        };

        $reg = $sandbox->registerPlugin($plugin);
        $this->assertSame('charts/apex-lite', $reg['name']);
        $this->assertSame('ACTIVE', $reg['status']);
        $this->assertTrue($plugin->booted);
    }
}
