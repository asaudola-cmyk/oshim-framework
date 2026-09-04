<?php
declare(strict_types=1);

namespace Tests\Unit\Cli;

use Oshim\Testing\TestCase;
use Oshim\Cli\Commands\MakeCrudCommand;
use Oshim\Cli\Input;
use Oshim\Cli\Output;

class MakeCrudCommandTest extends TestCase
{
    public function tearDown(): void
    {
        $this->cleanupGeneratedFiles();
        parent::tearDown();
    }

    private function cleanupGeneratedFiles(): void
    {
        $root = dirname(__DIR__, 3);
        @unlink($root . '/app/Models/TestProduct.php');
        @unlink($root . '/app/Controllers/TestProductController.php');
        foreach (glob($root . '/database/migrations/*create_testproducts_table.php') ?: [] as $file) {
            @unlink($file);
        }
    }

    public function testMakeCrudGeneratesFiles(): void
    {
        $this->cleanupGeneratedFiles();

        $cmd = new MakeCrudCommand();
        $input = new Input(['bin/oshim', 'make:crud', 'TestProduct']);
        $output = new Output();

        $code = $cmd->execute($input, $output);
        $this->assertSame(0, $code);

        $root = dirname(__DIR__, 3);
        $modelFile = $root . '/app/Models/TestProduct.php';
        $controllerFile = $root . '/app/Controllers/TestProductController.php';
        $migrationFiles = glob($root . '/database/migrations/*create_testproducts_table.php') ?: [];

        $this->assertTrue(file_exists($modelFile));
        $this->assertTrue(file_exists($controllerFile));
        $this->assertNotEmpty($migrationFiles);

        // Clean up test files
        $this->cleanupGeneratedFiles();
    }
}
