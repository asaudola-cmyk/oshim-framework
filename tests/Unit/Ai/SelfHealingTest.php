<?php
declare(strict_types=1);

namespace Tests\Unit\Ai;

use Error;
use ErrorException;
use Oshim\Ai\Healing\CodePatcher;
use Oshim\Ai\Healing\SelfHealingEngine;
use Oshim\Ai\Healing\SyntaxValidator;
use Oshim\Cli\Commands\SelfHealCommand;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Testing\TestCase;

class SelfHealingTest extends TestCase
{
    public function testSyntaxValidatorDetectsValidAndInvalidCode(): void
    {
        $validCode = '<?php echo "Hello OSHIM";';
        $validResult = SyntaxValidator::validateString($validCode);
        $this->assertTrue($validResult['valid']);

        $invalidCode = '<?php function broken( { echo 123; }';
        $invalidResult = SyntaxValidator::validateString($invalidCode);
        $this->assertFalse($invalidResult['valid']);
        $this->assertNotEmpty($invalidResult['error']);
    }

    public function testCodePatcherSafeAtomicMutationAndRollback(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'patcher_test_');
        $initialCode = "<?php\n\$status = 'draft';\n";
        file_put_contents($tmpFile, $initialCode);

        // Valid patch
        $result = CodePatcher::patchFile($tmpFile, "'draft'", "'published'");
        $this->assertTrue($result['success']);
        $this->assertStringContainsString("'published'", (string)file_get_contents($tmpFile));

        // Invalid syntax patch must fail and not corrupt file
        $invalidResult = CodePatcher::patchFile($tmpFile, "'published'", "syntax error ((");
        $this->assertFalse($invalidResult['success']);
        $this->assertStringContainsString("'published'", (string)file_get_contents($tmpFile));

        @unlink($tmpFile);
        if ($result['backup_path'] && is_file($result['backup_path'])) {
            @unlink($result['backup_path']);
        }
    }

    public function testSelfHealingEngineUndefinedKeyDiagnosisAndPatch(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'heal_key_');
        $code = "<?php\n\$data = [];\n\$val = \$data['missing_key'];\n";
        file_put_contents($tmpFile, $code);

        $engine = new SelfHealingEngine(autoApply: true);

        // Simulate runtime error exception
        $error = new ErrorException('Undefined array key "missing_key"', 0, E_WARNING, $tmpFile, 3);
        $report = $engine->diagnoseAndHeal($error);

        $this->assertTrue($report['diagnosed']);
        $this->assertTrue($report['applied']);
        $this->assertSame('HEALED_HOTPATCH_APPLIED', $report['status']);

        // Check patched file content
        $patchedCode = (string)file_get_contents($tmpFile);
        $this->assertStringContainsString("(\$data['missing_key'] ?? null)", $patchedCode);

        @unlink($tmpFile);
    }

    public function testSelfHealingEngineDivisionByZeroProtection(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'heal_div_');
        $code = "<?php\n\$divisor = 0;\n\$calc = 100 / \$divisor;\n";
        file_put_contents($tmpFile, $code);

        $engine = new SelfHealingEngine(autoApply: true);
        $error = new Error('Division by zero', 0);
        
        // Use reflection to set file and line on Error
        $ref = new \ReflectionClass($error);
        $fileProp = $ref->getProperty('file');
        $fileProp->setAccessible(true);
        $fileProp->setValue($error, $tmpFile);
        $lineProp = $ref->getProperty('line');
        $lineProp->setAccessible(true);
        $lineProp->setValue($error, 3);

        $report = $engine->diagnoseAndHeal($error);

        $this->assertTrue($report['applied']);
        $patched = (string)file_get_contents($tmpFile);
        $this->assertStringContainsString('($divisor ?: 1)', $patched);

        @unlink($tmpFile);
    }

    public function testSelfHealCommand(): void
    {
        $cmd = new SelfHealCommand();
        $input = new Input(['oshim', 'heal:scan', 'tests/Unit/Ai/SelfHealingTest.php']);
        $output = new Output();

        ob_start();
        $code = $cmd->execute($input, $output);
        $text = ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('OSHIM Autonomous Self-Healing & Mutating AI', $text);
        $this->assertStringContainsString('100% Code Integrity Verified', $text);
    }
}
