<?php
declare(strict_types=1);

namespace Oshim\Compiler\Isomorphic;

/**
 * 👑 Sovereign OSHIM Isomorphic PHP-to-TypeScript Transpiler
 * 
 * WHY: To beat Next.js and React, OSHIM must run everywhere.
 * This engine reads PHP UI Components and transpiles them into TypeScript.
 * This allows OSHIM to compile a "React-like" client bundle without Node.js,
 * enabling pure Client-Side Rendering (CSR) and Offline capabilities.
 */
class TsTranspiler
{
    /**
     * Transpiles a PHP OSHIM Component class into a TypeScript Class.
     */
    public static function transpileClass(string $phpSource): string
    {
        // 1. Extract Class Name
        preg_match('/class\s+([a-zA-Z0-9_]+)/', $phpSource, $classMatches);
        $className = $classMatches[1] ?? 'OshimComponent';

        // 2. Extract Public Properties (State)
        preg_match_all('/public\s+(int|string|bool|float|array)\s+\$([a-zA-Z0-9_]+)\s*=\s*([^;]+);/', $phpSource, $propMatches, PREG_SET_ORDER);
        
        $tsCode = "/**\n * 🚀 OSHIM Isomorphic Auto-Generated TypeScript\n */\n";
        $tsCode .= "export class {$className} {\n";
        
        foreach ($propMatches as $match) {
            $type = self::mapType($match[1]);
            $name = $match[2];
            $default = $match[3];
            $tsCode .= "    public {$name}: {$type} = {$default};\n";
        }

        // 3. Extract Methods (Actions)
        preg_match_all('/public\s+function\s+([a-zA-Z0-9_]+)\s*\((.*?)\)(?:\s*:\s*[a-zA-Z0-9_\\\\]+)?\s*\{([^}]*)\}/', $phpSource, $methodMatches, PREG_SET_ORDER);
        
        foreach ($methodMatches as $match) {
            $methodName = $match[1];
            if ($methodName === 'render' || $methodName === '__construct') continue;
            
            $args = $match[2]; // Simplified TS args
            $body = self::transpileBody($match[3]);
            
            $tsCode .= "\n    public {$methodName}({$args}): void {\n        {$body}\n    }\n";
        }
        
        $tsCode .= "}\n";
        
        return $tsCode;
    }

    protected static function mapType(string $phpType): string
    {
        return match($phpType) {
            'int', 'float' => 'number',
            'string' => 'string',
            'bool' => 'boolean',
            'array' => 'any[]',
            default => 'any'
        };
    }

    protected static function transpileBody(string $phpBody): string
    {
        // Convert $this-> to this.
        $tsBody = str_replace('$this->', 'this.', $phpBody);
        // Convert PHP variables $var to var
        $tsBody = preg_replace('/\$([a-zA-Z0-9_]+)/', '$1', $tsBody);
        return trim($tsBody);
    }
}
