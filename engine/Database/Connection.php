<?php
declare(strict_types=1);

namespace Oshim\Database;

use Oshim\Database\Drivers\DriverInterface;
use Oshim\Database\Query\QueryBuilder;
use Oshim\Database\Query\Expression;
use Oshim\Database\Exceptions\DatabaseException;
use Oshim\Database\Exceptions\QueryException;
use PDO;
use PDOException;
use PDOStatement;
use Closure;
use Throwable;

class Connection
{
    protected ?PDO $pdo = null;
    protected DriverInterface $driver;
    protected array $config;
    protected string $name;
    protected int $transactions = 0;
    protected array $queryLog = [];
    protected bool $loggingQueries = false;

    public function __construct(DriverInterface $driver, array $config = [], string $name = 'default')
    {
        $this->driver = $driver;
        $this->config = $config;
        $this->name = $name;
    }

    public function getDriver(): DriverInterface
    {
        return $this->driver;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getConfig(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }
        return $this->config[$key] ?? $default;
    }

    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = $this->driver->connect($this->config);
        }
        return $this->pdo;
    }

    public function setPdo(?PDO $pdo): static
    {
        $this->pdo = $pdo;
        return $this;
    }

    public function disconnect(): void
    {
        $this->pdo = null;
        $this->transactions = 0;
    }

    public function query(): QueryBuilder
    {
        return new QueryBuilder($this, $this->driver->getCompiler());
    }

    public function table(string|Expression $table, ?string $as = null): QueryBuilder
    {
        return $this->query()->from($table, $as);
    }

    public function select(string $query, array $bindings = []): array
    {
        return $this->run($query, $bindings, function (string $sql, array $binds) {
            $stmt = $this->prepareAndExecute($sql, $binds);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        });
    }

    public function selectOne(string $query, array $bindings = []): ?array
    {
        $records = $this->select($query, $bindings);
        return $records[0] ?? null;
    }

    public function insert(string $query, array $bindings = []): bool
    {
        return $this->statement($query, $bindings);
    }

    public function insertGetId(string $query, array $bindings = [], ?string $sequence = null): int|string
    {
        return $this->run($query, $bindings, function (string $sql, array $binds) use ($sequence) {
            $this->prepareAndExecute($sql, $binds);
            $id = $this->getPdo()->lastInsertId($sequence);
            return is_numeric($id) ? (int)$id : $id;
        });
    }

    public function update(string $query, array $bindings = []): int
    {
        return $this->affectingStatement($query, $bindings);
    }

    public function delete(string $query, array $bindings = []): int
    {
        return $this->affectingStatement($query, $bindings);
    }

    public function statement(string $query, array $bindings = []): bool
    {
        return $this->run($query, $bindings, function (string $sql, array $binds) {
            $stmt = $this->prepareAndExecute($sql, $binds);
            return $stmt !== false;
        });
    }

    public function affectingStatement(string $query, array $bindings = []): int
    {
        return $this->run($query, $bindings, function (string $sql, array $binds) {
            $stmt = $this->prepareAndExecute($sql, $binds);
            return $stmt->rowCount();
        });
    }

    public function unprepared(string $query): bool
    {
        return $this->run($query, [], function (string $sql) {
            return $this->getPdo()->exec($sql) !== false;
        });
    }

    public function raw(string|int|float $value): Expression
    {
        return new Expression($value);
    }

    // --- Transactions & Savepoints ---
    public function beginTransaction(): void
    {
        $pdo = $this->getPdo();
        if ($this->transactions === 0) {
            $pdo->beginTransaction();
        } else {
            $pdo->exec("SAVEPOINT trans_{$this->transactions}");
        }
        $this->transactions++;
    }

    public function commit(): void
    {
        if ($this->transactions <= 0) {
            return;
        }

        $this->transactions--;
        $pdo = $this->getPdo();

        if ($this->transactions === 0) {
            $pdo->commit();
        } else {
            $pdo->exec("RELEASE SAVEPOINT trans_{$this->transactions}");
        }
    }

    public function rollback(): void
    {
        if ($this->transactions <= 0) {
            return;
        }

        $this->transactions--;
        $pdo = $this->getPdo();

        if ($this->transactions === 0) {
            $pdo->rollBack();
        } else {
            $pdo->exec("ROLLBACK TO SAVEPOINT trans_{$this->transactions}");
        }
    }

    public function inTransaction(): bool
    {
        return $this->transactions > 0 || ($this->pdo !== null && $this->pdo->inTransaction());
    }

    public function transaction(callable $callback, int $attempts = 3): mixed
    {
        for ($currentAttempt = 1; $currentAttempt <= $attempts; $currentAttempt++) {
            $this->beginTransaction();

            try {
                $result = $callback($this);
                $this->commit();
                return $result;
            } catch (Throwable $e) {
                $this->rollback();

                if ($currentAttempt < $attempts && $this->isDeadlockOrLocked($e)) {
                    usleep(50000 * $currentAttempt); // Exponential backoff (50ms, 100ms...)
                    continue;
                }

                throw $e;
            }
        }

        throw new DatabaseException("Transaction failed after {$attempts} attempts.");
    }

    protected function isDeadlockOrLocked(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, 'database is locked')
            || str_contains($message, 'busy')
            || str_contains($message, 'deadlock');
    }

    // --- Query Execution Core ---
    protected array $statementCache = [];

        protected function prepareAndExecute(string $query, array $bindings = []): PDOStatement
    {
        // ADVANCED OPTIMIZATION: Statement Caching
        // WHY: Preparing the same SQL query repeatedly in the same request cycle is slow.
        // We cache the PDOStatement object in RAM.
        if (isset($this->statementCache[$query])) {
            $stmt = $this->statementCache[$query];
        } else {
            $stmt = $this->getPdo()->prepare($query);
            // Limit cache size to prevent memory leaks in long-running processes (Swoole/Worker)
            if (count($this->statementCache) > 500) {
                array_shift($this->statementCache);
            }
            $this->statementCache[$query] = $stmt;
        }

        $this->bindValues($stmt, $bindings);
        $stmt->execute();
        return $stmt;
    }

    protected function bindValues(PDOStatement $statement, array $bindings): void
    {
        $index = 1;
        foreach ($bindings as $key => $value) {
            $param = is_int($key) ? $index : ":{$key}";
            $type = match (true) {
                is_int($value)      => PDO::PARAM_INT,
                is_bool($value)     => PDO::PARAM_BOOL,
                is_null($value)     => PDO::PARAM_NULL,
                is_resource($value) => PDO::PARAM_LOB,
                default             => PDO::PARAM_STR,
            };

            $statement->bindValue($param, $value, $type);
            $index++;
        }
    }

    protected function run(string $query, array $bindings, Closure $callback): mixed
    {
        $start = microtime(true);

        try {
            $result = $callback($query, $bindings);
        } catch (PDOException $e) {
            throw new QueryException($query, $bindings, $e);
        }

        $timeMs = round((microtime(true) - $start) * 1000, 2);

        if ($this->loggingQueries) {
            $this->logQuery($query, $bindings, $timeMs);
        }

        return $result;
    }

    public function logQuery(string $query, array $bindings, float $timeMs): void
    {
        $this->queryLog[] = [
            'query'    => $query,
            'bindings' => $bindings,
            'time_ms'  => $timeMs,
        ];
    }

    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    public function enableQueryLog(): void
    {
        $this->loggingQueries = true;
    }

    public function disableQueryLog(): void
    {
        $this->loggingQueries = false;
    }

    public function flushQueryLog(): void
    {
        $this->queryLog = [];
    }
}
