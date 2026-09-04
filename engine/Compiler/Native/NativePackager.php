<?php
declare(strict_types=1);

namespace Oshim\Compiler\Native;

use RuntimeException;

/**
 * 👑 Sovereign Native AOT Compiler Builder
 * 
 * WHY: This executes the system C++ compiler (clang++ or g++) to turn 
 * our transpiled C++ code into a true standalone OS binary. No Zend Engine.
 */
class NativePackager
{
    public function compile(string $sourceFile, string $outputBinary): array
    {
        if (!is_file($sourceFile)) {
            throw new RuntimeException("Source file not found: {$sourceFile}");
        }

        $phpCode = file_get_contents($sourceFile);
        
        $transpiler = new Transpiler();
        $cppCode = $transpiler->transpile($phpCode);
        
        $buildDir = dirname($outputBinary) . '/.oshim_build';
        if (!is_dir($buildDir)) {
            mkdir($buildDir, 0777, true);
        }
        
        $cppFile = $buildDir . '/' . basename($sourceFile, '.php') . '.cpp';
        file_put_contents($cppFile, $cppCode);
        
        // Edge Case: Detect compiler. Prefer clang++ for speed, fallback to g++
        $compiler = $this->detectCompiler();
        if (!$compiler) {
            throw new RuntimeException("No native compiler found. Please install clang++ or g++.");
        }
        
        // Compile Command: O3 optimization, strip debug symbols for smallest binary
        $command = escapeshellcmd($compiler) . " -O3 -std=c++17 " . escapeshellarg($cppFile) . " -o " . escapeshellarg($outputBinary);
        
        exec($command . ' 2>&1', $output, $exitCode);
        
        if ($exitCode !== 0) {
            throw new RuntimeException("Native Compilation Failed:\n" . implode("\n", $output));
        }
        
        return [
            'status' => 'SUCCESS',
            'output_binary' => $outputBinary,
            'cpp_source' => $cppFile,
            'compiler' => $compiler,
            'size_bytes' => filesize($outputBinary),
        ];
    }
    
    protected function detectCompiler(): ?string
    {
        exec('which clang++ 2>/dev/null', $outClang, $retClang);
        if ($retClang === 0) return trim($outClang[0]);
        
        exec('which g++ 2>/dev/null', $outGcc, $retGcc);
        if ($retGcc === 0) return trim($outGcc[0]);
        
        return null;
    }
}
