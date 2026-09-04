<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Bootstrap;
use ReflectionClass;
use ReflectionNamedType;

class OptimizeContainerCommand extends Command
{
    protected string $name = 'optimize:di';
    protected string $description = 'Compile Dependency Injection Graph to bypass Reflection in production';

    public function execute(Input $input, Output $output): int
    {
        $basePath = Bootstrap::getBasePath();
        $classmapFile = $basePath . '/storage/framework/classmap.php';
        
        if (!is_file($classmapFile)) {
            $output->writeln("<error>✖ Error: Please run `php bin/oshim optimize:autoloader` first.</error>");
            return 1;
        }

        $classMap = require $classmapFile;
        $diCache = [];

        $output->writeln("<cyan>🚀 Compiling DI Graph...</cyan>");

        foreach ($classMap as $class => $path) {
            try {
                if (!class_exists($class)) {
                    continue; // Skip interfaces/traits
                }
                
                $reflector = new ReflectionClass($class);
                if (!$reflector->isInstantiable()) {
                    continue;
                }

                $constructor = $reflector->getConstructor();
                if ($constructor === null) {
                    $diCache[$class] = [];
                    continue;
                }

                $deps = [];
                $valid = true;
                foreach ($constructor->getParameters() as $param) {
                    $type = $param->getType();
                    if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                        $deps[] = $type->getName();
                    } else {
                        // We cannot safely cache primitive injection natively without values
                        $valid = false;
                        break;
                    }
                }

                if ($valid) {
                    $diCache[$class] = $deps;
                }
            } catch (\Throwable $e) {
                // Ignore classes that fail reflection (e.g. missing dependencies in dev)
            }
        }

        $cacheDir = $basePath . '/storage/framework';
        $exported = var_export($diCache, true);
        $content = "<?php\n// ADVANCED OPTIMIZATION: Compiled DI Graph to bypass Reflection.\nreturn {$exported};\n";
        
        file_put_contents($cacheDir . '/di_cache.php', $content);

        $output->writeln("<green>✔ Successfully compiled DI graph for " . count($diCache) . " classes!</green>");
        return 0;
    }
}
