<?php
declare(strict_types=1);

namespace Oshim\Ui\Animation;

use JsonSerializable;

/**
 * Declarative Scroll-Driven Animation & Viewport Trigger.
 *
 * Configures intersection observation, scroll progress scrubbing,
 * parallax tracking, and entrance/exit triggers.
 */
class ScrollTrigger implements JsonSerializable
{
    /** @var float|list<float> */
    protected float|array $threshold = 0.1;
    protected string $rootMargin = '0px';
    protected bool $once = true;
    protected bool|float $scrub = false;
    protected string $start = 'top 85%';
    protected string $end = 'bottom 15%';
    protected string $enterAction = 'play';
    protected string $exitAction = 'none';
    protected ?float $parallax = null;
    protected string $direction = 'vertical';
    protected bool $markers = false;

    public function __construct(
        float|array $threshold = 0.1,
        string $rootMargin = '0px',
        bool $once = true
    ) {
        $this->threshold = $threshold;
        $this->rootMargin = $rootMargin;
        $this->once = $once;
    }

    public static function make(float|array $threshold = 0.1, string $rootMargin = '0px', bool $once = true): self
    {
        return new self($threshold, $rootMargin, $once);
    }

    /**
     * Trigger animation when the element crosses the viewport threshold.
     */
    public static function onVisible(float $threshold = 0.15, bool $once = true): self
    {
        return new self($threshold, '0px', $once);
    }

    /**
     * Smooth scroll-linked scrub (tied to scroll progression 0.0 -> 1.0).
     */
    public static function scrub(
        string $start = 'top bottom',
        string $end = 'bottom top',
        float|bool $smooth = true
    ): self {
        $trigger = new self(0.0, '0px', false);
        $trigger->start = $start;
        $trigger->end = $end;
        $trigger->scrub = $smooth;
        return $trigger;
    }

    /**
     * Parallax scroll trigger.
     */
    public static function parallax(float $factor = 0.2, string $direction = 'vertical'): self
    {
        $trigger = new self(0.0, '0px', false);
        $trigger->parallax = $factor;
        $trigger->direction = $direction;
        $trigger->scrub = true;
        return $trigger;
    }

    /**
     * Reveal trigger with bottom margin offset.
     */
    public static function reveal(float $threshold = 0.2, string $rootMargin = '0px 0px -50px 0px'): self
    {
        return new self($threshold, $rootMargin, true);
    }

    public function getThreshold(): float|array
    {
        return $this->threshold;
    }

    public function threshold(float|array $threshold): self
    {
        $this->threshold = $threshold;
        return $this;
    }

    public function getRootMargin(): string
    {
        return $this->rootMargin;
    }

    public function rootMargin(string $rootMargin): self
    {
        $this->rootMargin = $rootMargin;
        return $this;
    }

    public function isOnce(): bool
    {
        return $this->once;
    }

    public function once(bool $once = true): self
    {
        $this->once = $once;
        return $this;
    }

    public function getScrub(): bool|float
    {
        return $this->scrub;
    }

    public function withScrub(bool|float $scrub = true): self
    {
        $this->scrub = $scrub;
        return $this;
    }

    public function getStart(): string
    {
        return $this->start;
    }

    public function start(string $start): self
    {
        $this->start = $start;
        return $this;
    }

    public function getEnd(): string
    {
        return $this->end;
    }

    public function end(string $end): self
    {
        $this->end = $end;
        return $this;
    }

    public function getEnterAction(): string
    {
        return $this->enterAction;
    }

    public function enterAction(string $action): self
    {
        $this->enterAction = $action;
        return $this;
    }

    public function getExitAction(): string
    {
        return $this->exitAction;
    }

    public function exitAction(string $action): self
    {
        $this->exitAction = $action;
        return $this;
    }

    public function getParallax(): ?float
    {
        return $this->parallax;
    }

    public function withParallax(float $factor): self
    {
        $this->parallax = $factor;
        return $this;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function direction(string $direction): self
    {
        $this->direction = in_array($direction, ['vertical', 'horizontal', 'both'], true) ? $direction : 'vertical';
        return $this;
    }

    public function withMarkers(bool $markers = true): self
    {
        $this->markers = $markers;
        return $this;
    }

    public function hasMarkers(): bool
    {
        return $this->markers;
    }

    /**
     * Format as associative data attributes for DOM rendering.
     *
     * @return array<string, string>
     */
    public function toDataAttributes(): array
    {
        $attrs = [
            'data-scroll' => 'true',
            'data-scroll-threshold' => is_array($this->threshold) ? implode(',', $this->threshold) : (string)$this->threshold,
            'data-scroll-margin' => $this->rootMargin,
            'data-scroll-once' => $this->once ? 'true' : 'false',
        ];

        if ($this->scrub !== false) {
            $attrs['data-scroll-scrub'] = is_float($this->scrub) ? (string)$this->scrub : 'true';
            $attrs['data-scroll-start'] = $this->start;
            $attrs['data-scroll-end'] = $this->end;
        }

        if ($this->parallax !== null) {
            $attrs['data-scroll-parallax'] = (string)$this->parallax;
            $attrs['data-scroll-direction'] = $this->direction;
        }

        if ($this->enterAction !== 'play') {
            $attrs['data-scroll-enter'] = $this->enterAction;
        }

        if ($this->exitAction !== 'none') {
            $attrs['data-scroll-exit'] = $this->exitAction;
        }

        if ($this->markers) {
            $attrs['data-scroll-markers'] = 'true';
        }

        return $attrs;
    }

    /**
     * Render as HTML attribute string.
     */
    public function toHtmlAttributes(): string
    {
        $parts = [];
        foreach ($this->toDataAttributes() as $k => $v) {
            $parts[] = sprintf('%s="%s"', $k, htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        }
        return implode(' ', $parts);
    }

    public function toArray(): array
    {
        return [
            'threshold' => $this->threshold,
            'rootMargin' => $this->rootMargin,
            'once' => $this->once,
            'scrub' => $this->scrub,
            'start' => $this->start,
            'end' => $this->end,
            'enterAction' => $this->enterAction,
            'exitAction' => $this->exitAction,
            'parallax' => $this->parallax,
            'direction' => $this->direction,
            'markers' => $this->markers,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
