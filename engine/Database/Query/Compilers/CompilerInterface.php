<?php
declare(strict_types=1);

namespace Oshim\Database\Query\Compilers;

use Oshim\Database\Query\QueryBuilder;
use Oshim\Database\Query\Expression;

interface CompilerInterface
{
    public function compileSelect(QueryBuilder $query): string;
    public function compileInsert(QueryBuilder $query, array $values): string;
    public function compileUpdate(QueryBuilder $query, array $values): string;
    public function compileDelete(QueryBuilder $query): string;
    public function compileAggregate(QueryBuilder $query, string $aggregate, string|Expression $column): string;
    public function wrap(string|Expression $value): string;
    public function wrapTable(string|Expression $table): string;
}
