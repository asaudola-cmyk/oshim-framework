<?php
declare(strict_types=1);

namespace Oshim\Database;

use Oshim\Database\Query\QueryBuilder;
use Oshim\Database\Query\Expression;
use PDO;

class DB
{
    public static function connection(?string $name = null): Connection
    {
        return ConnectionManager::getInstance()->connection($name);
    }

    public static function table(string|Expression $table, ?string $as = null): QueryBuilder
    {
        return static::connection()->table($table, $as);
    }

    public static function query(): QueryBuilder
    {
        return static::connection()->query();
    }

    public static function select(string $query, array $bindings = []): array
    {
        return static::connection()->select($query, $bindings);
    }

    public static function selectOne(string $query, array $bindings = []): ?array
    {
        return static::connection()->selectOne($query, $bindings);
    }

    public static function insert(string $query, array $bindings = []): bool
    {
        return static::connection()->insert($query, $bindings);
    }

    public static function insertGetId(string $query, array $bindings = [], ?string $sequence = null): int|string
    {
        return static::connection()->insertGetId($query, $bindings, $sequence);
    }

    public static function update(string $query, array $bindings = []): int
    {
        return static::connection()->update($query, $bindings);
    }

    public static function delete(string $query, array $bindings = []): int
    {
        return static::connection()->delete($query, $bindings);
    }

    public static function statement(string $query, array $bindings = []): bool
    {
        return static::connection()->statement($query, $bindings);
    }

    public static function unprepared(string $query): bool
    {
        return static::connection()->unprepared($query);
    }

    public static function raw(string|int|float $value): Expression
    {
        return new Expression($value);
    }

    public static function beginTransaction(): void
    {
        static::connection()->beginTransaction();
    }

    public static function commit(): void
    {
        static::connection()->commit();
    }

    public static function rollback(): void
    {
        static::connection()->rollback();
    }

    public static function transaction(callable $callback, int $attempts = 3): mixed
    {
        return static::connection()->transaction($callback, $attempts);
    }

    public static function getQueryLog(): array
    {
        return static::connection()->getQueryLog();
    }

    public static function enableQueryLog(): void
    {
        static::connection()->enableQueryLog();
    }

    public static function disableQueryLog(): void
    {
        static::connection()->disableQueryLog();
    }

    public static function flushQueryLog(): void
    {
        static::connection()->flushQueryLog();
    }

    public static function getPdo(): PDO
    {
        return static::connection()->getPdo();
    }

    public static function __callStatic(string $method, array $arguments): mixed
    {
        return static::connection()->$method(...$arguments);
    }
}
