<?php
declare(strict_types=1);

namespace Oshim\Async;

use Throwable;
use RuntimeException;

/**
 * Promises/A+ compliant Promise implementation.
 */
class Promise
{
    public const STATE_PENDING = 0;
    public const STATE_FULFILLED = 1;
    public const STATE_REJECTED = 2;

    protected int $state = self::STATE_PENDING;
    protected mixed $result = null;
    /** @var list<callable> */
    protected array $onFulfilledCallbacks = [];
    /** @var list<callable> */
    protected array $onRejectedCallbacks = [];

    public function __construct(?callable $executor = null)
    {
        if ($executor !== null) {
            try {
                $executor(
                    fn(mixed $val = null) => $this->resolve($val),
                    fn(mixed $reason) => $this->reject($reason)
                );
            } catch (Throwable $e) {
                $this->reject($e);
            }
        }
    }

    public function resolve(mixed $value = null): void
    {
        if ($this->state !== self::STATE_PENDING) {
            return;
        }

        // If resolving with another promise, chain onto it
        if ($value instanceof self) {
            $value->then(
                fn($v) => $this->resolve($v),
                fn($r) => $this->reject($r)
            );
            return;
        }

        $this->state = self::STATE_FULFILLED;
        $this->result = $value;

        foreach ($this->onFulfilledCallbacks as $callback) {
            $callback($value);
        }

        $this->onFulfilledCallbacks = [];
        $this->onRejectedCallbacks = [];
    }

    public function reject(mixed $reason): void
    {
        if ($this->state !== self::STATE_PENDING) {
            return;
        }

        $this->state = self::STATE_REJECTED;
        $this->result = $reason;

        foreach ($this->onRejectedCallbacks as $callback) {
            $callback($reason);
        }

        $this->onFulfilledCallbacks = [];
        $this->onRejectedCallbacks = [];
    }

    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): self
    {
        return new self(function (callable $resolve, callable $reject) use ($onFulfilled, $onRejected) {
            $handleFulfilled = function (mixed $value) use ($resolve, $reject, $onFulfilled) {
                if ($onFulfilled === null) {
                    $resolve($value);
                    return;
                }
                try {
                    $result = $onFulfilled($value);
                    $resolve($result);
                } catch (Throwable $e) {
                    $reject($e);
                }
            };

            $handleRejected = function (mixed $reason) use ($resolve, $reject, $onRejected) {
                if ($onRejected === null) {
                    $reject($reason);
                } else {
                    try {
                        $result = $onRejected($reason);
                        $resolve($result);
                    } catch (Throwable $e) {
                        $reject($e);
                    }
                }
            };

            if ($this->state === self::STATE_FULFILLED) {
                $handleFulfilled($this->result);
            } elseif ($this->state === self::STATE_REJECTED) {
                $handleRejected($this->result);
            } else {
                $this->onFulfilledCallbacks[] = $handleFulfilled;
                $this->onRejectedCallbacks[] = $handleRejected;
            }
        });
    }

    public function catch(callable $onRejected): self
    {
        return $this->then(null, $onRejected);
    }

    public function finally(callable $onFinally): self
    {
        return $this->then(
            function ($value) use ($onFinally) {
                $onFinally();
                return $value;
            },
            function ($reason) use ($onFinally) {
                $onFinally();
                if ($reason instanceof Throwable) {
                    throw $reason;
                }
                throw new RuntimeException((string)$reason);
            }
        );
    }

    public function isPending(): bool
    {
        return $this->state === self::STATE_PENDING;
    }

    public function isFulfilled(): bool
    {
        return $this->state === self::STATE_FULFILLED;
    }

    public function isRejected(): bool
    {
        return $this->state === self::STATE_REJECTED;
    }

    public function getResult(): mixed
    {
        return $this->result;
    }

    public function getState(): int
    {
        return $this->state;
    }

    // --- Static Combinators ---
    public static function resolved(mixed $value = null): self
    {
        $p = new self();
        $p->resolve($value);
        return $p;
    }

    public static function rejected(mixed $reason): self
    {
        $p = new self();
        $p->reject($reason);
        return $p;
    }

    public static function all(array $promises): self
    {
        return new self(function (callable $resolve, callable $reject) use ($promises) {
            if (empty($promises)) {
                $resolve([]);
                return;
            }

            $count = count($promises);
            $results = array_fill(0, $count, null);
            $completed = 0;
            $hasRejected = false;

            foreach ($promises as $index => $promise) {
                if (!$promise instanceof self) {
                    $promise = self::resolved($promise);
                }

                $promise->then(
                    function ($val) use (&$results, &$completed, $count, $resolve, $index, &$hasRejected) {
                        if ($hasRejected) return;
                        $results[$index] = $val;
                        $completed++;
                        if ($completed === $count) {
                            $resolve($results);
                        }
                    },
                    function ($err) use ($reject, &$hasRejected) {
                        if ($hasRejected) return;
                        $hasRejected = true;
                        $reject($err);
                    }
                );
            }
        });
    }

    public static function race(array $promises): self
    {
        return new self(function (callable $resolve, callable $reject) use ($promises) {
            if (empty($promises)) {
                return;
            }

            $settled = false;

            foreach ($promises as $promise) {
                if (!$promise instanceof self) {
                    $promise = self::resolved($promise);
                }

                $promise->then(
                    function ($val) use ($resolve, &$settled) {
                        if (!$settled) {
                            $settled = true;
                            $resolve($val);
                        }
                    },
                    function ($err) use ($reject, &$settled) {
                        if (!$settled) {
                            $settled = true;
                            $reject($err);
                        }
                    }
                );
            }
        });
    }

    public static function any(array $promises): self
    {
        return new self(function (callable $resolve, callable $reject) use ($promises) {
            if (empty($promises)) {
                $reject(new RuntimeException('All promises were rejected'));
                return;
            }

            $count = count($promises);
            $errors = [];
            $rejectedCount = 0;
            $resolved = false;

            foreach ($promises as $index => $promise) {
                if (!$promise instanceof self) {
                    $promise = self::resolved($promise);
                }

                $promise->then(
                    function ($val) use ($resolve, &$resolved) {
                        if (!$resolved) {
                            $resolved = true;
                            $resolve($val);
                        }
                    },
                    function ($err) use ($reject, &$errors, &$rejectedCount, $count, &$resolved, $index) {
                        if ($resolved) return;
                        $errors[$index] = $err;
                        $rejectedCount++;
                        if ($rejectedCount === $count) {
                            $reject($errors);
                        }
                    }
                );
            }
        });
    }

    public static function allSettled(array $promises): self
    {
        return new self(function (callable $resolve) use ($promises) {
            if (empty($promises)) {
                $resolve([]);
                return;
            }

            $count = count($promises);
            $results = array_fill(0, $count, null);
            $completed = 0;

            foreach ($promises as $index => $promise) {
                if (!$promise instanceof self) {
                    $promise = self::resolved($promise);
                }

                $promise->then(
                    function ($val) use (&$results, &$completed, $count, $resolve, $index) {
                        $results[$index] = ['status' => 'fulfilled', 'value' => $val];
                        $completed++;
                        if ($completed === $count) {
                            $resolve($results);
                        }
                    },
                    function ($err) use (&$results, &$completed, $count, $resolve, $index) {
                        $results[$index] = ['status' => 'rejected', 'reason' => $err];
                        $completed++;
                        if ($completed === $count) {
                            $resolve($results);
                        }
                    }
                );
            }
        });
    }
}
