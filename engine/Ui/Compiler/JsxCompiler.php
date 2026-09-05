<?php
declare(strict_types=1);

namespace Oshim\Ui\Compiler;

/**
 * 👑 Sovereign OSHIM PHP-JSX Compiler
 * 
 * WHY: This allows developers to write React-style JSX directly inside PHP.
 * This compiler reads `.oshim.php` files and transpiles the JSX blocks into 
 * the Pure PHP Fluent DSL (Option 2) before execution.
 * 
 * Note: This is a fast-path regex transpiler for demonstration of the paradigm.
 */
class JsxCompiler
{
    public static function compile(string $sourceCode): string
    {
        // 1. Find the JSX block inside return ( ... );
        return preg_replace_callback('/return\s*\(\s*(<.*?>)\s*\);/is', function ($matches) {
            $jsx = $matches[1];
            $dslCode = self::transpileNode($jsx);
            return "return " . $dslCode . ";";
        }, $sourceCode);
    }

    protected static function transpileNode(string $jsx): string
    {
        $jsx = trim($jsx);
        
        // Match a single node: <tag attr="val">children</tag>
        if (preg_match('/^<([a-zA-Z0-9\-]+)([^>]*)>(.*)<\/\1>$/is', $jsx, $matches)) {
            $tag = $matches[1];
            $attrsRaw = trim($matches[2]);
            $inner = trim($matches[3]);
            
            $code = "\Oshim\Ui\Dsl\Element::make('{$tag}')";
            
            // Parse attributes
            if ($attrsRaw) {
                preg_match_all('/([a-zA-Z0-9\-]+)=(?:\"([^\"]*)\"|\'([^\']*)\'|\{([^\}]+)\})/', $attrsRaw, $attrMatches, PREG_SET_ORDER);
                foreach ($attrMatches as $m) {
                    $key = $m[1];
                    $val = $m[2] ?? $m[3] ?? '';
                    $phpExpr = $m[4] ?? null;
                    
                    if ($key === 'class') {
                        $code .= "->classes('{$val}')";
                    } elseif ($key === 'oshim-click') {
                        $code .= "->onClick('{$val}')";
                    } elseif ($key === 'oshim-model') {
                        $code .= "->model('{$val}')";
                    } elseif ($phpExpr) {
                        $code .= "->attr('{$key}', (string)({$phpExpr}))";
                    } else {
                        $code .= "->attr('{$key}', '{$val}')";
                    }
                }
            }
            
            // Parse inner text / children (Simplified for this advanced PoC)
            if ($inner) {
                // If it contains nested tags, we recursively transpile (Basic support)
                if (preg_match('/^<[a-zA-Z]/', $inner)) {
                    // It's a child element
                    $childCode = self::transpileNode($inner);
                    $code .= "->child({$childCode})";
                } else {
                    // It's text or PHP expression { $var }
                    // Convert { $var } to concatenation
                    $innerParsed = preg_replace('/\{([^\}]+)\}/', "' . ($1) . '", $inner);
                    $code .= "->text('{$innerParsed}')";
                }
            }
            
            return $code;
        }
        
        // Self closing tags: <input type="text" />
        if (preg_match('/^<([a-zA-Z0-9\-]+)([^>]*)\/>$/is', $jsx, $matches)) {
            $tag = $matches[1];
            $code = "\Oshim\Ui\Dsl\Element::make('{$tag}')";
            return $code; // Attributes parsing omitted for brevity in self-closing
        }

        return "\Oshim\Ui\Dsl\Element::make('div')->text('Compiler Error')";
    }
}
