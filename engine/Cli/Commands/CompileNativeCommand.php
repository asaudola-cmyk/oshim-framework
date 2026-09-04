<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Compiler\Native\NativePackager;

class CompileNativeCommand extends Command
{
    protected string $name = 'compile:native';
    protected string $description = 'Compile OSHIM strict PHP directly to a standalone Machine Code Binary (No Zend Engine)';

    protected function configure(): void
    {
        $this->addArgument('source', Input::OPTIONAL, 'Path to the OSHIM PHP source file', 'app.php')
            ->addOption('output', 'o', Input::VALUE_OPTIONAL, 'Destination path for the native binary', 'dist/app');
    }

    public function execute(Input $input, Output $output): int
    {
        $output->writeln("<bold><cyan>🚀 OSHIM Native AOT Compiler (PHP -> C++ -> Machine Code)</cyan></bold>");
        $output->writeln("<dim>Bypassing the Zend Engine completely...</dim>\n");

        $source = (string)($input->getArgument('source') ?? $input->getArgument(0, 'app.php'));
        $destination = (string)$input->getOption('output', 'dist/app');

        if (!is_file($source)) {
            // Let's create a sample file for testing if it doesn't exist
            $output->writeln("<yellow>Source file not found. Creating a sample `app.php`...</yellow>");
            $this->createSampleApp($source);
        }

        $packager = new NativePackager();
        $output->writeln("Transpiling <yellow>{$source}</yellow> to C++...");

        try {
            $result = $packager->compile($source, $destination);
            
            $output->writeln("<green>✔ Native Binary Compiled Successfully!</green>");
            $output->writeln("• Compiler:      <cyan>{$result['compiler']}</cyan>");
            $output->writeln("• C++ Source:    <dim>{$result['cpp_source']}</dim>");
            $output->writeln("• Output Binary: <cyan>{$result['output_binary']}</cyan>");
            $output->writeln("• Binary Size:   <yellow>" . round($result['size_bytes'] / 1024, 2) . " KB</yellow>");
            
            $output->writeln("\n<bold><green>🎯 Done! You can now run the binary directly: ./{$destination}</green></bold>");
            $output->writeln("<dim>(Notice that it runs 100% natively without PHP or the Zend Engine!)</dim>");
            
            return 0;
        } catch (\Throwable $e) {
            $output->writeln("<red>✖ Compilation Failed:</red>");
            $output->writeln($e->getMessage());
            return 1;
        }
    }

    protected function createSampleApp(string $path): void
    {
        $code = <<<PHP
<?php
declare(strict_types=1);

function add(int \$a, int \$b): int {
    return \$a + \$b;
}

function main(): int {
    int \$result = add(500, 500);
    echo "Hello from OSHIM Native Binary! The result is: ";
    echo \$result;
    return 0;
}
PHP;
        file_put_contents($path, $code);
    }
}
