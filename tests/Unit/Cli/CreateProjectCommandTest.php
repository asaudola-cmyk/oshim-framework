<?php
declare(strict_types=1);

namespace Tests\Unit\Cli;

use Oshim\Cli\Commands\CreateProjectCommand;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Testing\TestCase;

class CreateProjectCommandTest extends TestCase
{
    public function testCreateProjectCommandScaffoldsMicroApp(): void
    {
        $tmpDir = sys_get_temp_dir() . '/test_micro_' . uniqid();
        $appName = basename($tmpDir);

        $cwd = sys_get_temp_dir();
        $cmd = new CreateProjectCommand();
        $input = new Input(['oshim', 'create', $appName, '--template=micro']);
        $output = new Output();

        $oldCwd = getcwd();
        chdir($cwd);

        ob_start();
        $code = $cmd->execute($input, $output);
        $text = ob_get_clean();

        chdir($oldCwd);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Successfully scaffolded', $text);
        $this->assertTrue(is_file($tmpDir . '/index.php'));

        $content = (string)file_get_contents($tmpDir . '/index.php');
        $this->assertStringContainsString('OSHIM MicroKernel', $content);

        // Verify syntax
        token_get_all($content, TOKEN_PARSE);

        // Cleanup
        @unlink($tmpDir . '/index.php');
        @rmdir($tmpDir);
    }

    public function testCreateProjectCommandScaffoldsSaasApp(): void
    {
        $tmpDir = sys_get_temp_dir() . '/test_saas_' . uniqid();
        $appName = basename($tmpDir);

        $cwd = sys_get_temp_dir();
        $cmd = new CreateProjectCommand();
        $input = new Input(['oshim', 'create', $appName, '--template=saas']);
        $output = new Output();

        $oldCwd = getcwd();
        chdir($cwd);

        ob_start();
        $code = $cmd->execute($input, $output);
        $text = ob_get_clean();

        chdir($oldCwd);

        $this->assertSame(0, $code);
        $this->assertTrue(is_file($tmpDir . '/routes/web.php'));
        $this->assertTrue(is_file($tmpDir . '/oshim.json'));

        $routesContent = (string)file_get_contents($tmpDir . '/routes/web.php');
        $this->assertStringContainsString('LandingPageLayout', $routesContent);

        // Cleanup
        @unlink($tmpDir . '/routes/web.php');
        @unlink($tmpDir . '/oshim.json');
        @rmdir($tmpDir . '/routes');
        @rmdir($tmpDir . '/app/Controllers');
        @rmdir($tmpDir . '/app/Models');
        @rmdir($tmpDir . '/app');
        @rmdir($tmpDir);
    }
}
