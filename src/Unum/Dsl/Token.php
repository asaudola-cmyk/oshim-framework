<?php

declare(strict_types=1);

namespace Unum\Dsl;

/**
 * 👑 UNUM Algorithmic DSL Lexical Token
 * 
 * WHY: High-performance discrete lexical unit representing numbers, identifiers,
 * arithmetic operators, and control flow keywords for JIT compilation.
 */
final class Token
{
    public const TYPE_NUMBER        = 'NUMBER';
    public const TYPE_IDENTIFIER    = 'IDENTIFIER';
    public const TYPE_PLUS          = 'PLUS';          // +
    public const TYPE_MINUS         = 'MINUS';         // -
    public const TYPE_STAR          = 'STAR';          // *
    public const TYPE_SLASH         = 'SLASH';         // /
    public const TYPE_CARET         = 'CARET';         // ^
    public const TYPE_ASSIGN        = 'ASSIGN';        // =
    public const TYPE_SEMICOLON     = 'SEMICOLON';     // ;
    public const TYPE_LPAREN        = 'LPAREN';        // (
    public const TYPE_RPAREN        = 'RPAREN';        // )
    public const TYPE_LBRACE        = 'LBRACE';        // {
    public const TYPE_RBRACE        = 'RBRACE';        // }
    public const TYPE_KEYWORD_LOOP  = 'KEYWORD_LOOP';  // loop
    public const TYPE_KEYWORD_RET   = 'KEYWORD_RET';   // return
    public const TYPE_EOF           = 'EOF';

    public string $type;
    public string|int|float $value;
    public int $position;

    public function __construct(string $type, string|int|float $value, int $position)
    {
        $this->type = $type;
        $this->value = $value;
        $this->position = $position;
    }

    public function is(string $type): bool
    {
        return $this->type === $type;
    }
}
