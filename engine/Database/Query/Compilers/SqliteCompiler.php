<?php
declare(strict_types=1);

namespace Oshim\Database\Query\Compilers;

use Oshim\Database\Query\QueryBuilder;
use Oshim\Database\Query\Expression;

class SqliteCompiler implements CompilerInterface
{
    /**
     * Compile a select query into standard SQL.
     */
    public function compileSelect(QueryBuilder $query): string
    {
        $components = [
            'aggregate' => $this->compileAggregateClause($query),
            'columns'   => $this->compileColumns($query),
            'from'      => $this->compileFrom($query),
            'joins'     => $this->compileJoins($query),
            'wheres'    => $this->compileWheres($query),
            'groups'    => $this->compileGroups($query),
            'havings'   => $this->compileHavings($query),
            'orders'    => $this->compileOrders($query),
            'limit'     => $this->compileLimit($query),
            'offset'    => $this->compileOffset($query),
        ];

        return trim(preg_replace('/\s+/', ' ', implode(' ', array_filter($components))));
    }

    public function compileAggregate(QueryBuilder $query, string $aggregate, string|Expression $column): string
    {
        $columnSql = $column instanceof Expression ? (string)$column : ($column === '*' ? '*' : $this->wrap($column));
        $query->columns = [new Expression("{$aggregate}({$columnSql}) AS aggregate")];
        return $this->compileSelect($query);
    }

    protected function compileAggregateClause(QueryBuilder $query): string
    {
        return '';
    }

    protected function compileColumns(QueryBuilder $query): string
    {
        $select = $query->distinct ? 'SELECT DISTINCT ' : 'SELECT ';

        if (empty($query->columns)) {
            return $select . '*';
        }

        $columns = [];
        foreach ($query->columns as $col) {
            if ($col instanceof Expression) {
                $columns[] = (string)$col;
            } elseif (is_string($col)) {
                $columns[] = $this->wrapColumnWithAlias($col);
            }
        }

        return $select . implode(', ', $columns);
    }

    protected function compileFrom(QueryBuilder $query): string
    {
        if (empty($query->from)) {
            return '';
        }

        $table = $query->from instanceof Expression ? (string)$query->from : $this->wrapTable($query->from);
        if ($query->fromAlias !== null) {
            $table .= ' AS ' . $this->wrapValue($query->fromAlias);
        }

        return 'FROM ' . $table;
    }

    protected function compileJoins(QueryBuilder $query): string
    {
        if (empty($query->joins)) {
            return '';
        }

        $sql = [];
        foreach ($query->joins as $join) {
            $type = strtoupper($join['type'] ?? 'INNER');
            $table = $this->wrapTable($join['table']);
            $first = $this->wrap($join['first']);
            $operator = $join['operator'] ?? '=';
            $second = $this->wrap($join['second']);

            $sql[] = "{$type} JOIN {$table} ON {$first} {$operator} {$second}";
        }

        return implode(' ', $sql);
    }

    public function compileWheres(QueryBuilder $query): string
    {
        if (empty($query->wheres)) {
            return '';
        }

        $sql = [];
        foreach ($query->wheres as $where) {
            $type = $where['type'];
            $method = 'where' . ucfirst($type);

            if (method_exists($this, $method)) {
                $segment = $this->$method($query, $where);
                if ($segment !== '') {
                    $boolean = strtoupper($where['boolean'] ?? 'AND');
                    $sql[] = empty($sql) ? $segment : "{$boolean} {$segment}";
                }
            }
        }

        return empty($sql) ? '' : 'WHERE ' . implode(' ', $sql);
    }

    protected function whereBasic(QueryBuilder $query, array $where): string
    {
        $column = $this->wrap($where['column']);
        $operator = $where['operator'];
        return "{$column} {$operator} ?";
    }

    protected function whereRaw(QueryBuilder $query, array $where): string
    {
        return (string)$where['sql'];
    }

    protected function whereIn(QueryBuilder $query, array $where): string
    {
        $column = $this->wrap($where['column']);
        $count = count($where['values']);
        if ($count === 0) {
            return '0 = 1';
        }
        $placeholders = implode(', ', array_fill(0, $count, '?'));
        return "{$column} IN ({$placeholders})";
    }

    protected function whereNotIn(QueryBuilder $query, array $where): string
    {
        $column = $this->wrap($where['column']);
        $count = count($where['values']);
        if ($count === 0) {
            return '1 = 1';
        }
        $placeholders = implode(', ', array_fill(0, $count, '?'));
        return "{$column} NOT IN ({$placeholders})";
    }

    protected function whereNull(QueryBuilder $query, array $where): string
    {
        return $this->wrap($where['column']) . ' IS NULL';
    }

    protected function whereNotNull(QueryBuilder $query, array $where): string
    {
        return $this->wrap($where['column']) . ' IS NOT NULL';
    }

    protected function whereBetween(QueryBuilder $query, array $where): string
    {
        $column = $this->wrap($where['column']);
        return "{$column} BETWEEN ? AND ?";
    }

    protected function whereNotBetween(QueryBuilder $query, array $where): string
    {
        $column = $this->wrap($where['column']);
        return "{$column} NOT BETWEEN ? AND ?";
    }

    protected function whereNested(QueryBuilder $query, array $where): string
    {
        $nestedQuery = $where['query'];
        $nestedSql = $this->compileWheres($nestedQuery);
        return '(' . substr($nestedSql, 6) . ')';
    }

    protected function compileGroups(QueryBuilder $query): string
    {
        if (empty($query->groups)) {
            return '';
        }

        $groups = array_map([$this, 'wrap'], $query->groups);
        return 'GROUP BY ' . implode(', ', $groups);
    }

    protected function compileHavings(QueryBuilder $query): string
    {
        if (empty($query->havings)) {
            return '';
        }

        $sql = [];
        foreach ($query->havings as $having) {
            $col = $this->wrap($having['column']);
            $op = $having['operator'];
            $bool = strtoupper($having['boolean'] ?? 'AND');
            $segment = "{$col} {$op} ?";
            $sql[] = empty($sql) ? $segment : "{$bool} {$segment}";
        }

        return 'HAVING ' . implode(' ', $sql);
    }

    protected function compileOrders(QueryBuilder $query): string
    {
        if (empty($query->orders)) {
            return '';
        }

        $orders = [];
        foreach ($query->orders as $order) {
            $col = $order['column'] instanceof Expression ? (string)$order['column'] : $this->wrap($order['column']);
            $direction = strtoupper($order['direction'] ?? 'ASC');
            $orders[] = "{$col} {$direction}";
        }

        return 'ORDER BY ' . implode(', ', $orders);
    }

    protected function compileLimit(QueryBuilder $query): string
    {
        if ($query->limit !== null) {
            return 'LIMIT ' . (int)$query->limit;
        }
        return '';
    }

    protected function compileOffset(QueryBuilder $query): string
    {
        if ($query->offset !== null) {
            return 'OFFSET ' . (int)$query->offset;
        }
        return '';
    }

    public function compileInsert(QueryBuilder $query, array $values): string
    {
        $table = $this->wrapTable($query->from);

        if (empty($values)) {
            return "INSERT INTO {$table} DEFAULT VALUES";
        }

        // Check if single record or multiple records
        if (!isset($values[0]) || !is_array($values[0])) {
            $values = [$values];
        }

        $columns = array_keys($values[0]);
        $wrappedColumns = implode(', ', array_map([$this, 'wrapValue'], $columns));

        $rows = [];
        foreach ($values as $row) {
            $rows[] = '(' . implode(', ', array_fill(0, count($row), '?')) . ')';
        }

        return "INSERT INTO {$table} ({$wrappedColumns}) VALUES " . implode(', ', $rows);
    }

    public function compileUpdate(QueryBuilder $query, array $values): string
    {
        $table = $this->wrapTable($query->from);

        $sets = [];
        foreach ($values as $col => $val) {
            $sets[] = $this->wrapValue($col) . ' = ?';
        }

        $setClause = implode(', ', $sets);
        $whereClause = $this->compileWheres($query);

        return trim("UPDATE {$table} SET {$setClause} {$whereClause}");
    }

    public function compileDelete(QueryBuilder $query): string
    {
        $table = $this->wrapTable($query->from);
        $whereClause = $this->compileWheres($query);

        return trim("DELETE FROM {$table} {$whereClause}");
    }

    public function wrap(string|Expression $value): string
    {
        if ($value instanceof Expression) {
            return (string)$value;
        }

        if ($value === '*') {
            return '*';
        }

        if (str_contains($value, '.')) {
            $segments = explode('.', $value);
            return implode('.', array_map([$this, 'wrapValue'], $segments));
        }

        return $this->wrapValue($value);
    }

    public function wrapTable(string|Expression $table): string
    {
        if ($table instanceof Expression) {
            return (string)$table;
        }
        return $this->wrap($table);
    }

    public function wrapValue(string $value): string
    {
        if ($value === '*') {
            return '*';
        }
        return '"' . str_replace('"', '""', $value) . '"';
    }

    protected function wrapColumnWithAlias(string $col): string
    {
        if (stripos($col, ' as ') !== false) {
            [$original, $alias] = preg_split('/\s+as\s+/i', $col);
            return $this->wrap(trim($original)) . ' AS ' . $this->wrapValue(trim($alias));
        }
        return $this->wrap($col);
    }
}
