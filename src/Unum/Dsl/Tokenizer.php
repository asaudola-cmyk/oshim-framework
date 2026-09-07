<?php

declare(strict_types=1);

namespace Unum\Dsl;

use RuntimeException;

/**
 * 👑 UNUM High-Speed Lexical Tokenizer
 * 
 * WHY: Scans mathematical expressions and algorithmic DSL scripts into discrete
 * token streams in single-pass linear time O(N) with zero regex backtrack overhead.
 */
final class Tokenizer
{
    private string $source;
    private int $length;
    private int $pos = 0;

    public function __construct(string $source)
    {
        $this->source = $source;
        $this->length = strlen($source);
    }

    /**
     * Tokenizes source into an array of lexical tokens.
     * 
     * @return list<Token>
     */
    public function tokenize(): array
    {
        $tokens = [];

        while ($this->pos < $this->length) {
            $ch = $this->source[$this->pos];

            /* WHY: Skip whitespace and newlines */
            if (ctype_space($ch)) {
                $this->pos++;
                continue;
            }

            /* Skip single-line comments // */
            if ($ch === '/' && $this->pos + 1 < $this->length && $this->source[$this->pos + 1] === '/') {
                $this->pos += 2;
                while ($this->pos < $this->length && $this->source[$this->pos] !== "\n") {
                    $this->pos++;
                }
                continue;
            }

            /* Skip single-line comments # */
            if ($ch === '#') {
                $this->pos++;
                while ($this->pos < $this->length && $this->source[$this->pos] !== "\n") {
                    $this->pos++;
                }
                continue;
            }

            /* Single-character delimiters and operators */
            switch ($ch) {
                case '+':
                    $tokens[] = new Token(Token::TYPE_PLUS, '+', $this->pos++);
                    continue 2;
                case '-':
                    $tokens[] = new Token(Token::TYPE_MINUS, '-', $this->pos++);
                    continue 2;
                case '*':
                    $tokens[] = new Token(Token::TYPE_STAR, '*', $this->pos++);
                    continue 2;
                case '/':
                    $tokens[] = new Token(Token::TYPE_SLASH, '/', $this->pos++);
                    continue 2;
                case '^':
                    $tokens[] = new Token(Token::TYPE_CARET, '^', $this->pos++);
                    continue 2;
                case '=':
                    $tokens[] = new Token(Token::TYPE_ASSIGN, '=', $this->pos++);
                    continue 2;
                case ';':
                    $tokens[] = new Token(Token::TYPE_SEMICOLON, ';', $this->pos++);
                    continue 2;
                case '(':
                    $tokens[] = new Token(Token::TYPE_LPAREN, '(', $this->pos++);
                    continue 2;
                case ')':
                    $tokens[] = new Token(Token::TYPE_RPAREN, ')', $this->pos++);
                    continue 2;
                case '{':
                    $tokens[] = new Token(Token::TYPE_LBRACE, '{', $this->pos++);
                    continue 2;
                case '}':
                    $tokens[] = new Token(Token::TYPE_RBRACE, '}', $this->pos++);
                    continue 2;
            }

            /* Numeric Literals */
            if (ctype_digit($ch)) {
                $start = $this->pos;
                while ($this->pos < $this->length && ctype_digit($this->source[$this->pos])) {
                    $this->pos++;
                }
                $numStr = substr($this->source, $start, $this->pos - $start);
                $tokens[] = new Token(Token::TYPE_NUMBER, (int)$numStr, $start);
                continue;
            }

            /* Identifiers and Keywords */
            if (ctype_alpha($ch) || $ch === '_') {
                $start = $this->pos;
                while ($this->pos < $this->length && (ctype_alnum($this->source[$this->pos]) || $this->source[$this->pos] === '_')) {
                    $this->pos++;
                }
                $ident = substr($this->source, $start, $this->pos - $start);

                if (strtolower($ident) === 'loop') {
                    $tokens[] = new Token(Token::TYPE_KEYWORD_LOOP, 'loop', $start);
                } elseif (strtolower($ident) === 'return') {
                    $tokens[] = new Token(Token::TYPE_KEYWORD_RET, 'return', $start);
                } else {
                    $tokens[] = new Token(Token::TYPE_IDENTIFIER, $ident, $start);
                }
                continue;
            }

            throw new RuntimeException("Unexpected character '{$ch}' at position {$this->pos}");
        }

        $tokens[] = new Token(Token::TYPE_EOF, '', $this->pos);
        return $tokens;
    }
}
