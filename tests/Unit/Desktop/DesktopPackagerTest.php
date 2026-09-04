<?php
declare(strict_types=1);

namespace Tests\Unit\Desktop;

use Oshim\Cli\Commands\DesktopBuildCommand;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Desktop\DesktopPackager;
use Oshim\Testing\TestCase;

class DesktopPackagerTest extends TestCase
{
    public function testDesktopPackagerGeneratesAllPlatformBundles(): void
    {
        $tmpDist = sys_get_temp_dir() . '/oshim_desktop_test_' . uniqid();
        $packager = new DesktopPackager(__DIR__, $tmpDist, [
            'app_name' => 'TestSovereignApp',
            'version' => '2.0.0',
            'window' => ['width' => 1024, 'height' => 768],
        ]);

        $result = $packager->package();

        $this->assertSame('PACKAGED_SUCCESS', $result['status']);
        $this->assertSame($tmpDist, $result['dist_dir']);

        // Check Linux artifacts
        $this->assertTrue(is_file($tmpDist . '/oshim-desktop'));
        $this->assertTrue(is_file($tmpDist . '/oshim.desktop'));
        $linuxSh = (string)file_get_contents($tmpDist . '/oshim-desktop');
        $this->assertStringContainsString('OSHIM_PORT', $linuxSh);
        $this->assertStringContainsString('1024,768', $linuxSh);

        // Check Windows artifacts
        $this->assertTrue(is_file($tmpDist . '/oshim-desktop.bat'));
        $this->assertTrue(is_file($tmpDist . '/run-webview2.ps1'));
        $ps1 = (string)file_get_contents($tmpDist . '/run-webview2.ps1');
        $this->assertStringContainsString('msedge.exe', $ps1);

        // Check macOS artifacts
        $this->assertTrue(is_file($tmpDist . '/OSHIM.app/Contents/MacOS/OSHIM'));
        $this->assertTrue(is_file($tmpDist . '/OSHIM.app/Contents/Info.plist'));
        $plist = (string)file_get_contents($tmpDist . '/OSHIM.app/Contents/Info.plist');
        $this->assertStringContainsString('TestSovereignApp', $plist);

        // Check Manifest
        $this->assertTrue(is_file($tmpDist . '/app-manifest.json'));
        $manifest = json_decode((string)file_get_contents($tmpDist . '/app-manifest.json'), true);
        $this->assertSame('TestSovereignApp', $manifest['name']);
        $this->assertSame('2.0.0', $manifest['version']);
        $this->assertNotEmpty($result['checksums']);

        // Cleanup
        $this->deleteDirectory($tmpDist);
    }

    public function testDesktopBuildCommandExecution(): void
    {
        $tmpDist = sys_get_temp_dir() . '/oshim_build_cli_' . uniqid();
        $cmd = new DesktopBuildCommand();
        $input = new Input(['oshim', '--dist=' . $tmpDist, '--name=MyAwesomeApp', '--width=1400', '--height=900']);
        $output = new Output();

        ob_start();
        $code = $cmd->execute($input, $output);
        $text = ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Desktop Bundle Generated Successfully', $text);
        $this->assertTrue(is_file($tmpDist . '/oshim-desktop'));
        $this->assertTrue(is_file($tmpDist . '/app-manifest.json'));

        $this->deleteDirectory($tmpDist);
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
