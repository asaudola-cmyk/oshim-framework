<?php
declare(strict_types=1);

namespace Oshim\Cron;

use Closure;

class ScheduleEvent
{
    private mixed $callback;
    private string $cronExpression = '* * * * *';
    private string $description = '';

    public function __construct(mixed $callback, string $description = '')
    {
        $this->callback = $callback;
        $this->description = $description;
    }

    public function everyMinute(): self
    {
        $this->cronExpression = '* * * * *';
        return $this;
    }

    public function hourly(): self
    {
        $this->cronExpression = '0 * * * *';
        return $this;
    }

    public function daily(): self
    {
        $this->cronExpression = '0 0 * * *';
        return $this;
    }

    public function cron(string $expression): self
    {
        $this->cronExpression = $expression;
        return $this;
    }

    public function isDue(int $timestamp = 0): bool
    {
        $timestamp = $timestamp > 0 ? $timestamp : time();
        $minute = (int)date('i', $timestamp);
        $hour = (int)date('G', $timestamp);
        $dayOfMonth = (int)date('j', $timestamp);
        $month = (int)date('n', $timestamp);
        $dayOfWeek = (int)date('w', $timestamp);

        $parts = preg_split('/\s+/', trim($this->cronExpression));
        if (count($parts) !== 5) {
            return false;
        }

        return $this->matchField($parts[0], $minute)
            && $this->matchField($parts[1], $hour)
            && $this->matchField($parts[2], $dayOfMonth)
            && $this->matchField($parts[3], $month)
            && $this->matchField($parts[4], $dayOfWeek);
    }

    private function matchField(string $pattern, int $value): bool
    {
        if ($pattern === '*') {
            return true;
        }

        if (str_starts_with($pattern, '*/')) {
            $step = (int)substr($pattern, 2);
            return $step > 0 && ($value % $step === 0);
        }

        if (str_contains($pattern, ',')) {
            $values = array_map('intval', explode(',', $pattern));
            return in_array($value, $values, true);
        }

        return (int)$pattern === $value;
    }

    public function run(): mixed
    {
        if ($this->callback instanceof Closure) {
            return ($this->callback)();
        }

        if (is_callable($this->callback)) {
            return call_user_func($this->callback);
        }

        return null;
    }

    public function getDescription(): string { return $this->description; }
    public function getExpression(): string { return $this->cronExpression; }
}
