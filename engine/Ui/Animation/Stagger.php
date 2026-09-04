<?php
declare(strict_types=1);

namespace Oshim\Ui\Animation;

use JsonSerializable;

/**
 * Orchestrated Stagger Timing Generator.
 *
 * Coordinates cascade timing across collections and child elements with
 * directional flow, center distribution, and delay calculations.
 */
class Stagger implements JsonSerializable
{
    protected float $interval = 0.08;
    protected float $delay = 0.0;
    protected string $direction = 'forward';
    protected string|int $from = 'first';
    protected ?float $maxDelay = null;
    protected ?string $ease = null;

    public function __construct(
        float $interval = 0.08,
        float $delay = 0.0,
        string $direction = 'forward'
    ) {
        $this->interval = max(0.001, $interval);
        $this->delay = max(0.0, $delay);
        $this->direction = $direction;
    }

    public static function make(float $interval = 0.08, float $delay = 0.0, string $direction = 'forward'): self
    {
        return new self($interval, $delay, $direction);
    }

    public static function forward(float $interval = 0.05, float $delay = 0.0): self
    {
        return new self($interval, $delay, 'forward');
    }

    public static function reverse(float $interval = 0.05, float $delay = 0.0): self
    {
        $stagger = new self($interval, $delay, 'reverse');
        $stagger->from = 'last';
        return $stagger;
    }

    public static function center(float $interval = 0.05, float $delay = 0.0): self
    {
        $stagger = new self($interval, $delay, 'center');
        $stagger->from = 'center';
        return $stagger;
    }

    public static function cascade(float $interval = 0.1, string $direction = 'forward'): self
    {
        return new self($interval, 0.0, $direction);
    }

    public function getInterval(): float
    {
        return $this->interval;
    }

    public function interval(float $interval): self
    {
        $this->interval = max(0.001, $interval);
        return $this;
    }

    public function getDelay(): float
    {
        return $this->delay;
    }

    public function delay(float $delay): self
    {
        $this->delay = max(0.0, $delay);
        return $this;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function direction(string $direction): self
    {
        $this->direction = in_array($direction, ['forward', 'reverse', 'center', 'random'], true)
            ? $direction
            : 'forward';
        return $this;
    }

    public function getFrom(): string|int
    {
        return $this->from;
    }

    public function from(string|int $from): self
    {
        $this->from = $from;
        return $this;
    }

    public function getMaxDelay(): ?float
    {
        return $this->maxDelay;
    }

    public function maxDelay(?float $maxDelay): self
    {
        $this->maxDelay = $maxDelay !== null ? max(0.0, $maxDelay) : null;
        return $this;
    }

    public function getEase(): ?string
    {
        return $this->ease;
    }

    public function ease(?string $ease): self
    {
        $this->ease = $ease;
        return $this;
    }

    /**
     * Compute the stagger delay for an item at a specific index.
     *
     * @param int $index Zero-based index.
     * @param int $totalCount Total number of elements.
     * @return float Delay in seconds.
     */
    public function calculateDelay(int $index, int $totalCount): float
    {
        if ($totalCount <= 1) {
            return $this->delay;
        }

        $calc = match ($this->direction) {
            'reverse' => ($totalCount - 1 - $index) * $this->interval,
            'center' => abs($index - (($totalCount - 1) / 2.0)) * $this->interval,
            'random' => (((float)(crc32((string)$index . '_oshim_stagger') % 1000)) / 1000.0) * ($totalCount * $this->interval),
            default => match ($this->from) {
                'last' => ($totalCount - 1 - $index) * $this->interval,
                'center' => abs($index - (($totalCount - 1) / 2.0)) * $this->interval,
                default => is_numeric($this->from)
                    ? abs($index - (int)$this->from) * $this->interval
                    : $index * $this->interval,
            },
        };

        $result = $this->delay + $calc;

        if ($this->maxDelay !== null && $result > $this->maxDelay) {
            $result = $this->maxDelay;
        }

        return round($result, 4);
    }

    /**
     * Apply calculated stagger delays to a list of items.
     *
     * @template T
     * @param list<T> $items
     * @param (callable(T, float, int, int): mixed)|null $callback
     * @return array
     */
    public function apply(array $items, ?callable $callback = null): array
    {
        $count = count($items);
        $results = [];

        foreach (array_values($items) as $index => $item) {
            $computedDelay = $this->calculateDelay($index, $count);
            if ($callback !== null) {
                $results[] = $callback($item, $computedDelay, $index, $count);
            } else {
                $results[] = $computedDelay;
            }
        }

        return $results;
    }

    public function toDataAttributes(): array
    {
        return [
            'data-stagger' => 'true',
            'data-stagger-interval' => (string)$this->interval,
            'data-stagger-delay' => (string)$this->delay,
            'data-stagger-direction' => $this->direction,
        ];
    }

    public function toArray(): array
    {
        return [
            'interval' => $this->interval,
            'delay' => $this->delay,
            'direction' => $this->direction,
            'from' => $this->from,
            'maxDelay' => $this->maxDelay,
            'ease' => $this->ease,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
