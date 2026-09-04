<?php
declare(strict_types=1);

namespace Oshim\Security;

class RateLimiter
{
    /** @var array<string, array{attempts: int, reset_at: float}> */
    protected static array $hits = [];

    public function attempt(string $key, int $maxAttempts, int $decaySeconds, ?callable $callback = null): bool
    {
        if ($this->tooManyAttempts($key, $maxAttempts)) {
            return false;
        }

        $this->hit($key, $decaySeconds);

        if ($callback !== null) {
            $callback();
        }

        return true;
    }

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        $this->cleanUp($key);
        return $this->attempts($key) >= $maxAttempts;
    }

    public function hit(string $key, int $decaySeconds = 60): int
    {
        $this->cleanUp($key);
        $now = microtime(true);

        if (!isset(self::$hits[$key])) {
            self::$hits[$key] = [
                'attempts' => 1,
                'reset_at' => $now + $decaySeconds,
            ];
        } else {
            self::$hits[$key]['attempts']++;
        }

        return self::$hits[$key]['attempts'];
    }

    public function attempts(string $key): int
    {
        $this->cleanUp($key);
        return self::$hits[$key]['attempts'] ?? 0;
    }

    public function resetAttempts(string $key): void
    {
        unset(self::$hits[$key]);
    }

    public function availableIn(string $key): int
    {
        $this->cleanUp($key);
        if (!isset(self::$hits[$key])) {
            return 0;
        }

        $now = microtime(true);
        return (int)max(0, ceil(self::$hits[$key]['reset_at'] - $now));
    }

    public function clear(): void
    {
        self::$hits = [];
    }

    protected function cleanUp(string $key): void
    {
        if (isset(self::$hits[$key])) {
            if (microtime(true) >= self::$hits[$key]['reset_at']) {
                unset(self::$hits[$key]);
            }
        }
    }
}
