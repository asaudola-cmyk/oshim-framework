<?php

declare(strict_types=1);

namespace Unum\Dsl;

/**
 * 👑 UNUM Abstract Syntax Tree (AST) Node Hierarchy
 * 
 * WHY: Provides strongly typed structural representation of mathematical expressions
 * and hardware algorithmic loops before mapping into 64-bit Universal Numbers.
 */

interface AstNode
{
}

final class NumberNode implements AstNode
{
    public int $value;

    public function __construct(int $value)
    {
        $this->value = $value;
    }
}

final class VariableNode implements AstNode
{
    public string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}

final class BinaryOpNode implements AstNode
{
    public string $op;
    public AstNode $left;
    public AstNode $right;

    public function __construct(string $op, AstNode $left, AstNode $right)
    {
        $this->op = $op;
        $this->left = $left;
        $this->right = $right;
    }
}

final class AssignNode implements AstNode
{
    public string $varName;
    public AstNode $expr;

    public function __construct(string $varName, AstNode $expr)
    {
        $this->varName = $varName;
        $this->expr = $expr;
    }
}

final class LoopNode implements AstNode
{
    public AstNode $countExpr;
    /** @var list<AstNode> */
    public array $body;

    /**
     * @param list<AstNode> $body
     */
    public function __construct(AstNode $countExpr, array $body)
    {
        $this->countExpr = $countExpr;
        $this->body = $body;
    }
}

final class ReturnNode implements AstNode
{
    public AstNode $expr;

    public function __construct(AstNode $expr)
    {
        $this->expr = $expr;
    }
}

final class ProgramNode implements AstNode
{
    /** @var list<AstNode> */
    public array $statements;

    /**
     * @param list<AstNode> $statements
     */
    public function __construct(array $statements)
    {
        $this->statements = $statements;
    }
}
