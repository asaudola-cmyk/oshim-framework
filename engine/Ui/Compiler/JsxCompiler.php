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
        
        // 1. Match standard container node: <tag attr="val">children</tag>
        if (preg_match('/^<([a-zA-Z0-9\-]+)([^>]*)>(.*)<\/\1>$/is', $jsx, $matches)) {
            $tag = $matches[1];
            $attrsRaw = trim($matches[2]);
            $inner = trim($matches[3]);
            
            $code = "\Oshim\Ui\Dsl\Element::make('{$tag}')";
            
            // Parse attributes
            if ($attrsRaw !== '') {
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
                    } elseif ($phpExpr !== null) {
                        $code .= "->attr('{$key}', (string)({$phpExpr}))";
                    } else {
                        $code .= "->attr('{$key}', '{$val}')";
                    }
                }
            }
            
            // Parse nested children using depth-aware tokenizer
            // WHY: Prevents multiple sibling tags from failing regex boundary checks
            if ($inner !== '') {
                $children = self::tokenizeChildren($inner);
                foreach ($children as $child) {
                    $child = trim($child);
                    if ($child === '') continue;

                    if ($child[0] === '<') {
                        $childCode = self::transpileNode($child);
                        $code .= "->child({$childCode})";
                    } else {
                        // Text or inline PHP expression { $var }
                        $innerParsed = preg_replace('/\{([^\}]+)\}/', "' . ($1) . '", $child);
                        $code .= "->child('{$innerParsed}')";
                    }
                }
            }
            
            return $code;
        }
        
        // 2. Self-closing tags: <input type="text" ... />
        if (preg_match('/^<([a-zA-Z0-9\-]+)([^>]*)\/>$/is', $jsx, $matches)) {
            $tag = $matches[1];
            $attrsRaw = trim($matches[2]);
            $code = "\Oshim\Ui\Dsl\Element::make('{$tag}')";
            if ($attrsRaw !== '') {
                preg_match_all('/([a-zA-Z0-9\-]+)=(?:\"([^\"]*)\"|\'([^\']*)\'|\{([^\}]+)\})/', $attrsRaw, $attrMatches, PREG_SET_ORDER);
                foreach ($attrMatches as $m) {
                    $key = $m[1];
                    $val = $m[2] ?? $m[3] ?? '';
                    $code .= "->attr('{$key}', '{$val}')";
                }
            }
            return $code;
        }

        return "\Oshim\Ui\Dsl\Element::make('div')->text(" . var_export($jsx, true) . ")";
    }

    /**
     * Depth-aware XML/HTML Tokenizer for JSX children.
     * WHY: Accurately separates sibling tags and text nodes without regex corruption.
     */
    protected static function tokenizeChildren(string $html): array
    {
        $html = trim($html);
        $nodes = [];
        $len = strlen($html);
        $pos = 0;

        while ($pos < $len) {
            if ($html[$pos] === '<') {
                if ($pos + 1 < $len && $html[$pos + 1] === '/') {
                    break;
                }
                if (preg_match('/^<([a-zA-Z0-9\-]+)/', substr($html, $pos), $m)) {
                    $tagName = $m[1];
                    $tagEnd = strpos($html, '>', $pos);
                    if ($tagEnd === false) break;
                    
                    if ($html[$tagEnd - 1] === '/') {
                        $nodes[] = substr($html, $pos, $tagEnd - $pos + 1);
                        $pos = $tagEnd + 1;
                        continue;
                    }
                    
                    $closeTag = "</{$tagName}>";
                    $openTagPattern = "<{$tagName}";
                    $depth = 1;
                    $searchPos = $tagEnd + 1;
                    $foundEnd = false;

                    while ($searchPos < $len) {
                        $nextOpen = strpos($html, $openTagPattern, $searchPos);
                        $nextClose = strpos($html, $closeTag, $searchPos);

                        if ($nextClose === false) break;

                        if ($nextOpen !== false && $nextOpen < $nextClose) {
                            $charAfter = $html[$nextOpen + strlen($openTagPattern)] ?? '';
                            if (in_array($charAfter, [' ', '>', '/'], true)) {
                                $depth++;
                            }
                            $searchPos = $nextOpen + strlen($openTagPattern);
                        } else {
                            $depth--;
                            if ($depth === 0) {
                                $endOfNode = $nextClose + strlen($closeTag);
                                $nodes[] = substr($html, $pos, $endOfNode - $pos);
                                $pos = $endOfNode;
                                $foundEnd = true;
                                break;
                            }
                            $searchPos = $nextClose + strlen($closeTag);
                        }
                    }
                    if (!$foundEnd) {
                        $nodes[] = substr($html, $pos);
                        break;
                    }
                } else {
                    $pos++;
                }
            } else {
                $nextTag = strpos($html, '<', $pos);
                if ($nextTag === false) {
                    $text = trim(substr($html, $pos));
                    if ($text !== '') $nodes[] = $text;
                    break;
                } else {
                    $text = trim(substr($html, $pos, $nextTag - $pos));
                    if ($text !== '') $nodes[] = $text;
                    $pos = $nextTag;
                }
            }
        }
        return $nodes;
    }
}
