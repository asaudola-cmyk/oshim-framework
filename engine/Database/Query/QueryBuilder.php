<?php
declare(strict_types=1);

namespace Oshim\Database\Query;

use Oshim\Database\Connection;
use Oshim\Database\Query\Compilers\CompilerInterface;
use Closure;
use InvalidArgumentException;

class QueryBuilder
{
    public Connection $connection;
    public CompilerInterface $compiler;

    public array|string|Expression $from = '';
    public ?string $fromAlias = null;
    public array $columns = [];
    public bool $distinct = false;
    public array $joins = [];
    public array $wheres = [];
    public array $groups = [];
    public array $havings = [];
    public array $orders = [];
    public ?int $limit = null;
    public ?int $offset = null;
    public array $bindings = [
        'select' => [],
        'join'   => [],
        'where'  => [],
        'having' => [],
        'order'  => [],
    ];

    public function __construct(Connection $connection, CompilerInterface $compiler)
    {
        $this->connection = $connection;
        $this->compiler = $compiler;
    }

    public function table(string|Expression $table, ?string $as = null): static
    {
        return $this->from($table, $as);
    }

    public function from(string|Expression $table, ?string $as = null): static
    {
        $this->from = $table;
        $this->fromAlias = $as;
        return $this;
    }

    public function select(string|array|Expression ...$columns): static
    {
        $this->columns = [];
        $this->bindings['select'] = [];
        return $this->addSelect(...$columns);
    }

    public function selectRaw(string $expression, array $bindings = []): static
    {
        $this->addSelect(new Expression($expression));
        if (!empty($bindings)) {
            $this->addBinding($bindings, 'select');
        }
        return $this;
    }

    public function addSelect(string|array|Expression ...$columns): static
    {
        foreach ($columns as $column) {
            if (is_array($column)) {
                foreach ($column as $col) {
                    $this->columns[] = $col;
                }
            } else {
                $this->columns[] = $column;
            }
        }
        return $this;
    }

    public function distinct(bool $distinct = true): static
    {
        $this->distinct = $distinct;
        return $this;
    }

    public function join(string $table, string|Closure $first, ?string $operator = null, ?string $second = null, string $type = 'inner'): static
    {
        $this->joins[] = [
            'type'     => $type,
            'table'    => $table,
            'first'    => $first,
            'operator' => $operator ?? '=',
            'second'   => $second,
        ];
        return $this;
    }

    public function leftJoin(string $table, string $first, ?string $operator = null, ?string $second = null): static
    {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    public function rightJoin(string $table, string $first, ?string $operator = null, ?string $second = null): static
    {
        return $this->join($table, $first, $operator, $second, 'right');
    }

    public function crossJoin(string $table): static
    {
        $this->joins[] = [
            'type'     => 'cross',
            'table'    => $table,
            'first'    => null,
            'operator' => null,
            'second'   => null,
        ];
        return $this;
    }

    public function where(string|Closure|array|Expression $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): static
    {
        // 1. Nested closure: where(function($query) { ... })
        if ($column instanceof Closure) {
            $nested = new static($this->connection, $this->compiler);
            $nested->from = $this->from;
            $column($nested);

            $this->wheres[] = [
                'type'    => 'nested',
                'query'   => $nested,
                'boolean' => $boolean,
            ];
            $this->addBinding($nested->getRawBindings()['where'], 'where');
            return $this;
        }

        // 2. Key-value array: where(['status' => 'active', 'role' => 'admin'])
        if (is_array($column)) {
            foreach ($column as $col => $val) {
                $this->where($col, '=', $val, $boolean);
            }
            return $this;
        }

        // 3. Raw Expression
        if ($column instanceof Expression) {
            $this->wheres[] = [
                'type'    => 'raw',
                'sql'     => (string)$column,
                'boolean' => $boolean,
            ];
            return $this;
        }

        // 4. Two arguments passed: where('status', 'active') => where('status', '=', 'active')
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type'     => 'basic',
            'column'   => $column,
            'operator' => $operator,
            'value'    => $value,
            'boolean'  => $boolean,
        ];

        $this->addBinding($value, 'where');
        return $this;
    }

    public function orWhere(string|Closure|array|Expression $column, mixed $operator = null, mixed $value = null): static
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        return $this->where($column, $operator, $value, 'or');
    }

    public function whereIn(string $column, array $values, string $boolean = 'and', bool $not = false): static
    {
        $type = $not ? 'notIn' : 'in';
        $this->wheres[] = [
            'type'    => $type,
            'column'  => $column,
            'values'  => array_values($values),
            'boolean' => $boolean,
        ];

        $this->addBinding(array_values($values), 'where');
        return $this;
    }

    public function orWhereIn(string $column, array $values): static
    {
        return $this->whereIn($column, $values, 'or');
    }

    public function whereNotIn(string $column, array $values, string $boolean = 'and'): static
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    public function orWhereNotIn(string $column, array $values): static
    {
        return $this->whereNotIn($column, $values, 'or');
    }

    public function whereNull(string $column, string $boolean = 'and', bool $not = false): static
    {
        $type = $not ? 'notNull' : 'null';
        $this->wheres[] = [
            'type'    => $type,
            'column'  => $column,
            'boolean' => $boolean,
        ];
        return $this;
    }

    public function orWhereNull(string $column): static
    {
        return $this->whereNull($column, 'or');
    }

    public function whereNotNull(string $column, string $boolean = 'and'): static
    {
        return $this->whereNull($column, $boolean, true);
    }

    public function orWhereNotNull(string $column): static
    {
        return $this->whereNotNull($column, 'or');
    }

    /**
     * Remove soft delete constraints from the query.
     */
    public function withTrashed(?string $column = 'deleted_at'): static
    {
        $this->wheres = array_values(array_filter($this->wheres, function ($where) use ($column) {
            if (isset($where['type']) && ($where['type'] === 'null' || $where['type'] === 'notNull')) {
                $col = (string)($where['column'] ?? '');
                if ($column !== null && ($col === $column || str_ends_with($col, '.' . $column) || str_contains($col, $column))) {
                    return false;
                }
                if ($column === null && str_contains($col, 'deleted_at')) {
                    return false;
                }
            }
            return true;
        }));

        return $this;
    }

    /**
     * Filter the query to only include soft deleted records.
     */
    public function onlyTrashed(?string $column = 'deleted_at'): static
    {
        $this->withTrashed($column);
        $col = $column ?? 'deleted_at';
        if (!empty($this->from) && is_string($this->from) && !str_contains($col, '.')) {
            $col = $this->from . '.' . $col;
        }
        return $this->whereNotNull($col);
    }

    public function whereBetween(string $column, array $values, string $boolean = 'and', bool $not = false): static
    {
        $type = $not ? 'notBetween' : 'between';
        $this->wheres[] = [
            'type'    => $type,
            'column'  => $column,
            'boolean' => $boolean,
        ];
        $this->addBinding([$values[0], $values[1]], 'where');
        return $this;
    }

    public function whereNotBetween(string $column, array $values, string $boolean = 'and'): static
    {
        return $this->whereBetween($column, $values, $boolean, true);
    }

    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'and'): static
    {
        $this->wheres[] = [
            'type'    => 'raw',
            'sql'     => $sql,
            'boolean' => $boolean,
        ];
        $this->addBinding($bindings, 'where');
        return $this;
    }

    public function orWhereRaw(string $sql, array $bindings = []): static
    {
        return $this->whereRaw($sql, $bindings, 'or');
    }

    public function groupBy(string ...$groups): static
    {
        foreach ($groups as $group) {
            $this->groups[] = $group;
        }
        return $this;
    }

    public function having(string $column, string $operator, mixed $value, string $boolean = 'and'): static
    {
        $this->havings[] = [
            'column'   => $column,
            'operator' => $operator,
            'value'    => $value,
            'boolean'  => $boolean,
        ];
        $this->addBinding($value, 'having');
        return $this;
    }

    public function orderBy(string|Expression $column, string $direction = 'asc'): static
    {
        $this->orders[] = [
            'column'    => $column,
            'direction' => strtolower($direction) === 'desc' ? 'desc' : 'asc',
        ];
        return $this;
    }

    public function orderByDesc(string|Expression $column): static
    {
        return $this->orderBy($column, 'desc');
    }

    public function latest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'desc');
    }

    public function oldest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'asc');
    }

    public function limit(int $value): static
    {
        $this->limit = max(0, $value);
        return $this;
    }

    public function take(int $value): static
    {
        return $this->limit($value);
    }

    public function offset(int $value): static
    {
        $this->offset = max(0, $value);
        return $this;
    }

    public function skip(int $value): static
    {
        return $this->offset($value);
    }

    public function addBinding(mixed $value, string $type = 'where'): static
    {
        if (!isset($this->bindings[$type])) {
            throw new InvalidArgumentException("Invalid binding type: {$type}");
        }

        if (is_array($value)) {
            $this->bindings[$type] = array_merge($this->bindings[$type], array_values($value));
        } else {
            $this->bindings[$type][] = $value;
        }

        return $this;
    }

    public function getRawBindings(): array
    {
        return $this->bindings;
    }

    public function getBindings(): array
    {
        $all = [];
        foreach (['select', 'join', 'where', 'having', 'order'] as $type) {
            foreach ($this->bindings[$type] as $binding) {
                $all[] = $binding;
            }
        }
        return $all;
    }

    public function toSql(): string
    {
        return $this->compiler->compileSelect($this);
    }

    // --- Execution Methods ---
    public function get(array $columns = ['*']): array
    {
        if ($columns !== ['*']) {
            $this->select($columns);
        }

        return $this->connection->select($this->toSql(), $this->getBindings());
    }

    public function first(array $columns = ['*']): ?array
    {
        $clone = clone $this;
        $clone->limit(1);
        $results = $clone->get($columns);
        return $results[0] ?? null;
    }

    public function find(mixed $id, string $key = 'id'): ?array
    {
        return $this->where($key, '=', $id)->first();
    }

    public function value(string $column): mixed
    {
        $record = $this->first([$column]);
        if ($record && array_key_exists($column, $record)) {
            return $record[$column];
        }
        return null;
    }

    public function pluck(string $column, ?string $key = null): array
    {
        $columns = $key ? [$column, $key] : [$column];
        $results = $this->get($columns);

        $plucked = [];
        foreach ($results as $row) {
            if ($key !== null && isset($row[$key])) {
                $plucked[$row[$key]] = $row[$column] ?? null;
            } else {
                $plucked[] = $row[$column] ?? null;
            }
        }

        return $plucked;
    }

    public function count(string $column = '*'): int
    {
        $clone = clone $this;
        $sql = $this->compiler->compileAggregate($clone, 'COUNT', $column);
        $result = $this->connection->selectOne($sql, $clone->getBindings());
        return (int)($result['aggregate'] ?? 0);
    }

    public function sum(string $column): float|int
    {
        $clone = clone $this;
        $sql = $this->compiler->compileAggregate($clone, 'SUM', $column);
        $result = $this->connection->selectOne($sql, $clone->getBindings());
        $val = $result['aggregate'] ?? 0;
        return is_numeric($val) ? (str_contains((string)$val, '.') ? (float)$val : (int)$val) : 0;
    }

    public function avg(string $column): float
    {
        $clone = clone $this;
        $sql = $this->compiler->compileAggregate($clone, 'AVG', $column);
        $result = $this->connection->selectOne($sql, $clone->getBindings());
        return (float)($result['aggregate'] ?? 0.0);
    }

    public function min(string $column): mixed
    {
        $clone = clone $this;
        $sql = $this->compiler->compileAggregate($clone, 'MIN', $column);
        $result = $this->connection->selectOne($sql, $clone->getBindings());
        return $result['aggregate'] ?? null;
    }

    public function max(string $column): mixed
    {
        $clone = clone $this;
        $sql = $this->compiler->compileAggregate($clone, 'MAX', $column);
        $result = $this->connection->selectOne($sql, $clone->getBindings());
        return $result['aggregate'] ?? null;
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function doesntExist(): bool
    {
        return !$this->exists();
    }

    public function paginate(int $perPage = 15, int $currentPage = 1): Paginator
    {
        $total = $this->count();
        $offset = ($currentPage - 1) * $perPage;

        $clone = clone $this;
        $items = $clone->limit($perPage)->offset($offset)->get();

        return new Paginator($items, $total, $perPage, $currentPage);
    }

    public function insert(array $values): bool
    {
        if (empty($values)) {
            return true;
        }

        $sql = $this->compiler->compileInsert($this, $values);

        // Flatten bindings for insert
        $bindings = [];
        if (isset($values[0]) && is_array($values[0])) {
            foreach ($values as $row) {
                foreach ($row as $val) {
                    $bindings[] = $val;
                }
            }
        } else {
            foreach ($values as $val) {
                $bindings[] = $val;
            }
        }

        return $this->connection->insert($sql, $bindings);
    }

    public function insertGetId(array $values, ?string $sequence = null): int|string
    {
        $sql = $this->compiler->compileInsert($this, $values);
        $bindings = array_values($values);

        return $this->connection->insertGetId($sql, $bindings, $sequence);
    }

    public function update(array $values): int
    {
        if (empty($values)) {
            return 0;
        }

        $sql = $this->compiler->compileUpdate($this, $values);
        $bindings = array_merge(array_values($values), $this->getBindings());

        return $this->connection->update($sql, $bindings);
    }

    public function delete(): int
    {
        $sql = $this->compiler->compileDelete($this);
        return $this->connection->delete($sql, $this->getBindings());
    }

    public function truncate(): void
    {
        $table = $this->compiler->wrapTable($this->from);
        $this->connection->statement("DELETE FROM {$table}");
    }
}
