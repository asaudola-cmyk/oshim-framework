<?php
declare(strict_types=1);

namespace Oshim\Database\Query\Compilers;

class MysqlCompiler extends SqliteCompiler
{
    public function wrapValue(string $value): string
    {
        if ($value === '*') {
            return '*';
        }
        return '`' . str_replace('`', '``', $value) . '`';
    }
}
