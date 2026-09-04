<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Bootstrap;

class OptimizeAutoloaderCommand extends Command
{
    protected string $name = 'optimize:autoloader';
    protected string $description = 'Compile the zero-dependency ClassMap for O(1) autoloading';

    public function execute(Input $input, Output $output): int
    {
        $basePath = Bootstrap::getBasePath();
        $directories = [
            $basePath . '/engine',
            $basePath . '/app',
            $basePath . '/plugins'
        ];

        $output->writeln("<cyan>🚀 Compiling ClassMap for O(1) Autoloading...</cyan>");
        
        $classMap = [];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) continue;
            
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $class = $this->extractClassFromFile($file->getPathname());
                    if ($class) {
                        $classMap[$class] = $file->getPathname();
                    }
                }
            }
        }

        $cacheDir = $basePath . '/storage/framework';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        $exported = var_export($classMap, true);
        $content = "<?php\n// ADVANCED OPTIMIZATION: O(1) ClassMap cache generated automatically.\nreturn {$exported};\n";
        
        file_put_contents($cacheDir . '/classmap.php', $content);

        $output->writeln("<green>✔ Successfully compiled " . count($classMap) . " classes into O(1) ClassMap!</green>");
        return 0;
    }

    protected function extractClassFromFile(string $file): ?string
    {
        $contents = file_get_contents($file);
        if (!$contents) return null;

        $namespace = '';
        $class = '';
        $tokens = token_get_all($contents);
        $count = count($tokens);
        
        for ($i = 0; $i < $count; $i++) {
            if ($tokens[$i][0] === T_NAMESPACE) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($tokens[$j][0] === T_NAME_QUALIFIED || $tokens[$j][0] === T_STRING) {
                        $namespace = $tokens[$j][1];
                        break;
                    }
                }
            }
            if ($tokens[$i][0] === T_CLASS) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($tokens[$j][0] === T_STRING) {
                        $class = $tokens[$j][1];
                        break;
                    }
                }
                break;
            }
        }

        if ($class) {
            return $namespace ? $namespace . '\\' . $class : $class;
        }

        return null;
    }
}
