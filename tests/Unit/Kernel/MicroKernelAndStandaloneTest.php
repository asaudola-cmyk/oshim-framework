<?php
declare(strict_types=1);

namespace Tests\Unit\Kernel;

use Oshim\Cli\Commands\PackStandaloneCommand;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Compiler\StandalonePackager;
use Oshim\Http\Request;
use Oshim\Kernel\MicroKernel;
use Oshim\Oshim;
use Oshim\Testing\TestCase;

class MicroKernelAndStandaloneTest extends TestCase
{
    public function testMicroKernelRoutingWithoutBootingProviders(): void
    {
        $kernel = new MicroKernel();

        $kernel->get('/api/ping', fn() => ['status' => 'pong', 'time' => 123456]);
        $kernel->post('/api/users/{id}', fn(Request $req, string $id) => [
            'id' => $id,
            'name' => 'Alice',
        ]);

        // 1. Test GET /api/ping
        $req1 = new Request('GET', '/api/ping');
        $res1 = $kernel->handle($req1);
        $this->assertSame(200, $res1->statusCode());
        $this->assertStringContainsString('pong', (string)$res1->body());

        // 2. Test POST /api/users/99
        $req2 = new Request('POST', '/api/users/99');
        $res2 = $kernel->handle($req2);
        $this->assertSame(200, $res2->statusCode());
        $data = json_decode((string)$res2->body(), true);
        $this->assertSame('99', $data['id']);
        $this->assertSame('Alice', $data['name']);

        // 3. Test 404 Route Not Found
        $req3 = new Request('GET', '/non/existent');
        $res3 = $kernel->handle($req3);
        $this->assertSame(404, $res3->statusCode());
    }

    public function testOshimGatewayAutonomousAccess(): void
    {
        Oshim::reset();

        // 1. Test Micro Routing via Oshim Gateway
        Oshim::get('/sovereign/hello', fn() => 'Autonomous OSHIM');
        $req = new Request('GET', '/sovereign/hello');
        $res = Oshim::run($req);
        $this->assertSame(200, $res->statusCode());
        $this->assertSame('Autonomous OSHIM', (string)$res->body());

        // 2. Test Standalone Ledger (Without booting full framework)
        $ledger = Oshim::ledger(1);
        $this->assertSame(1, $ledger->getBlockCount());
        $this->assertTrue($ledger->isValid());

        // 3. Test Standalone Tailwind JIT
        $css = Oshim::tailwind('<div class="bg-cyan-500 p-4 text-slate-100">Test</div>');
        $this->assertStringContainsString('#06b6d4', $css);
        $this->assertStringContainsString('padding', $css);

        Oshim::reset();
    }

    public function testStandalonePackagerCompilesSingleSelfContainedFile(): void
    {
        $tmpDir = sys_get_temp_dir() . '/oshim_pack_test_' . uniqid();
        mkdir($tmpDir, 0777, true);

        $sourceFile = $tmpDir . '/my_micro_app.php';
        $bundleFile = $tmpDir . '/bundle.php';

        $sourceCode = <<<'PHP'
<?php
require_once __DIR__ . '/../../engine/Oshim.php';
use Oshim\Oshim;
use Oshim\Http\Request;

Oshim::get('/standalone', fn() => ['standalone' => true, 'framework' => 'OSHIM']);
$req = new Request('GET', '/standalone');
$res = Oshim::run($req);
echo $res->body();
PHP;

        file_put_contents($sourceFile, $sourceCode);

        $packager = new StandalonePackager();
        $result = $packager->compile($sourceFile, $bundleFile);

        $this->assertSame('COMPILED_SUCCESS', $result['status']);
        $this->assertTrue(is_file($bundleFile));
        $this->assertTrue($result['size_bytes'] > 500);
        $this->assertNotEmpty($result['classes_bundled']);

        // Execute the single standalone bundle in a separate isolated PHP process
        $output = shell_exec(escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($bundleFile));
        $this->assertNotNull($output);
        $data = json_decode((string)$output, true);
        $this->assertTrue($data['standalone'] ?? false);
        $this->assertSame('OSHIM', $data['framework'] ?? '');

        // Cleanup
        @unlink($sourceFile);
        @unlink($bundleFile);
        @rmdir($tmpDir);
    }

    public function testPackStandaloneCommand(): void
    {
        $tmpDir = sys_get_temp_dir() . '/oshim_cmd_pack_' . uniqid();
        mkdir($tmpDir, 0777, true);

        $sourceFile = $tmpDir . '/app.php';
        $bundleFile = $tmpDir . '/dist_app.php';

        file_put_contents($sourceFile, "<?php echo 'Test Standalone Script';");

        $cmd = new PackStandaloneCommand();
        $input = new Input(['oshim', 'pack:standalone', $sourceFile, '--output=' . $bundleFile]);
        $output = new Output();

        ob_start();
        $code = $cmd->execute($input, $output);
        $text = ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Standalone Bundle Compiled Successfully', $text);
        $this->assertTrue(is_file($bundleFile));

        @unlink($sourceFile);
        @unlink($bundleFile);
        @rmdir($tmpDir);
    }
}
