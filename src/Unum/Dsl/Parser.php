<?php

declare(strict_types=1);

namespace Unum\Dsl;

require_once __DIR__ . '/Ast.php';

use RuntimeException;

/**
 * 👑 UNUM Algorithmic DSL Parser (Pratt / Operator-Precedence)
 * 
 * WHY: Translates flat lexical tokens into structured Abstract Syntax Trees (AST).
 * Employs Pratt operator-precedence parsing to naturally handle mathematical priorities
 * (e.g. exponentiation ^ over multiplication * over addition +) without ambiguity.
 */
final class Parser
{
    /** @var list<Token> */
    private array $tokens;
    private int $pos = 0;
    private int $count;

    /**
     * @param list<Token> $tokens
     */
    public function __construct(array $tokens)
    {
        $this->tokens = $tokens;
        $this->count = count($tokens);
    }

    public static function fromString(string $source): self
    {
        $tokenizer = new Tokenizer($source);
        return new self($tokenizer->tokenize());
    }

    private function current(): Token
    {
        return $this->tokens[$this->pos] ?? new Token(Token::TYPE_EOF, '', -1);
    }

    private function peek(): Token
    {
        return $this->tokens[$this->pos + 1] ?? new Token(Token::TYPE_EOF, '', -1);
    }

    private function advance(): Token
    {
        $tok = $this->current();
        if ($this->pos < $this->count) {
            $this->pos++;
        }
        return $tok;
    }

    private function match(string $type): bool
    {
        if ($this->current()->is($type)) {
            $this->advance();
            return true;
        }
        return false;
    }

    private function expect(string $type, string $errorMessage): Token
    {
        if ($this->current()->is($type)) {
            return $this->advance();
        }
        throw new RuntimeException($errorMessage . " at position " . $this->current()->position . ", found: " . $this->current()->type);
    }

    /**
     * Parses the entire source as a program composed of multiple statements.
     */
    public function parseProgram(): ProgramNode
    {
        $statements = [];

        while (!$this->current()->is(Token::TYPE_EOF)) {
            $statements[] = $this->parseStatement();
        }

        return new ProgramNode($statements);
    }

    /**
     * Parses source strictly as a single mathematical expression.
     */
    public function parseExpressionOnly(): AstNode
    {
        $expr = $this->parseExpression();
        if (!$this->current()->is(Token::TYPE_EOF) && !$this->current()->is(Token::TYPE_SEMICOLON)) {
            throw new RuntimeException("Unexpected trailing tokens after expression at position " . $this->current()->position);
        }
        return $expr;
    }

    /**
     * Parses a single statement.
     */
    public function parseStatement(): AstNode
    {
        /* 1. Return statement: return <expr>; */
        if ($this->match(Token::TYPE_KEYWORD_RET)) {
            $expr = $this->parseExpression();
            $this->match(Token::TYPE_SEMICOLON);
            return new ReturnNode($expr);
        }

        /* 2. Hardware Loop statement: loop (countExpr) { body } */
        if ($this->match(Token::TYPE_KEYWORD_LOOP)) {
            $this->expect(Token::TYPE_LPAREN, "Expected '(' after 'loop'");
            $countExpr = $this->parseExpression();
            $this->expect(Token::TYPE_RPAREN, "Expected ')' after loop count expression");

            $this->expect(Token::TYPE_LBRACE, "Expected '{' to start loop body");
            $body = [];
            while (!$this->current()->is(Token::TYPE_RBRACE) && !$this->current()->is(Token::TYPE_EOF)) {
                $body[] = $this->parseStatement();
            }
            $this->expect(Token::TYPE_RBRACE, "Expected '}' to close loop body");

            return new LoopNode($countExpr, $body);
        }

        /* 3. Assignment statement: ident = <expr>; */
        if ($this->current()->is(Token::TYPE_IDENTIFIER) && $this->peek()->is(Token::TYPE_ASSIGN)) {
            $ident = (string)$this->advance()->value;
            $this->advance(); // Consume '='
            $expr = $this->parseExpression();
            $this->match(Token::TYPE_SEMICOLON);
            return new AssignNode($ident, $expr);
        }

        /* 4. Standalone expression statement: <expr>; */
        $expr = $this->parseExpression();
        $this->match(Token::TYPE_SEMICOLON);
        return $expr;
    }

    /**
     * Parses expressions using operator precedence.
     */
    public function parseExpression(int $minPrecedence = 0): AstNode
    {
        $left = $this->parsePrimary();

        while (true) {
            $curr = $this->current();
            $prec = $this->getPrecedence($curr->type);

            if ($prec < $minPrecedence || $prec === 0) {
                break;
            }

            $opToken = $this->advance();
            $op = (string)$opToken->value;

            /* Right-associative operators (like exponentiation ^) */
            $nextMinPrec = ($op === '^') ? $prec : $prec + 1;
            $right = $this->parseExpression($nextMinPrec);

            $left = new BinaryOpNode($op, $left, $right);
        }

        return $left;
    }

    /**
     * Parses primary expressions (numbers, variables, parenthesized sub-expressions, unary negation).
     */
    private function parsePrimary(): AstNode
    {
        $curr = $this->current();

        /* Unary minus */
        if ($this->match(Token::TYPE_MINUS)) {
            $sub = $this->parsePrimary();
            if ($sub instanceof NumberNode) {
                return new NumberNode(-$sub->value);
            }
            return new BinaryOpNode('-', new NumberNode(0), $sub);
        }

        /* Numeric literal */
        if ($curr->is(Token::TYPE_NUMBER)) {
            $this->advance();
            return new NumberNode((int)$curr->value);
        }

        /* Variable identifier */
        if ($curr->is(Token::TYPE_IDENTIFIER)) {
            $this->advance();
            return new VariableNode((string)$curr->value);
        }

        /* Parenthesized expression: ( expr ) */
        if ($this->match(Token::TYPE_LPAREN)) {
            $expr = $this->parseExpression();
            $this->expect(Token::TYPE_RPAREN, "Expected ')' after parenthesized expression");
            return $expr;
        }

        throw new RuntimeException("Unexpected token in expression: '{$curr->value}' ({$curr->type}) at position {$curr->position}");
    }

    /**
     * Returns precedence weight for binary operators.
     */
    private function getPrecedence(string $tokenType): int
    {
        return match ($tokenType) {
            Token::TYPE_PLUS, Token::TYPE_MINUS => 10,
            Token::TYPE_STAR, Token::TYPE_SLASH => 20,
            Token::TYPE_CARET                  => 30,
            default                            => 0,
        };
    }
}
