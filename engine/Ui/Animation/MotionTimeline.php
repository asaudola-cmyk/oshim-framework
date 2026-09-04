<?php
declare(strict_types=1);

namespace Oshim\Ui\Animation;

use JsonSerializable;

/**
 * Multi-Element Animation Timeline Orchestrator.
 *
 * Sequences complex choreographed animations across multiple UI elements
 * with relative offsets, overlapping tracks, and physics springs.
 */
class MotionTimeline implements JsonSerializable
{
    /** @var list<array{id: string, element: MotionElement|null, variant: AnimationVariant, offset: float, startTime: float, duration: float, endTime: float, spring: Spring}> */
    protected array $tracks = [];
    protected float $cursor = 0.0;
    protected ?Spring $defaultSpring = null;

    public function __construct(?Spring $defaultSpring = null)
    {
        $this->defaultSpring = $defaultSpring ?? Spring::default();
    }

    public static function make(?Spring $defaultSpring = null): self
    {
        return new self($defaultSpring);
    }

    /**
     * Add an animation step to the timeline.
     *
     * @param string|MotionElement $target Element instance or target DOM id.
     * @param array<string, mixed>|AnimationVariant $properties Visual target properties.
     * @param float $offset Offset relative to current cursor (e.g. 0.0 for sequential, -0.2 for overlap).
     * @param Spring|null $spring Custom spring for this step.
     */
    public function add(
        string|MotionElement $target,
        array|AnimationVariant $properties,
        float $offset = 0.0,
        ?Spring $spring = null
    ): self {
        $id = $target instanceof MotionElement ? $target->getId() : $target;
        $element = $target instanceof MotionElement ? $target : null;

        $variant = $properties instanceof AnimationVariant
            ? $properties
            : new AnimationVariant('step_' . count($this->tracks), $properties);

        $activeSpring = $spring ?? $variant->getSpring() ?? $this->defaultSpring ?? Spring::default();
        $duration = $activeSpring->settlingDuration();

        $startTime = max(0.0, $this->cursor + $offset);
        $endTime = $startTime + $duration;

        $this->tracks[] = [
            'id' => $id,
            'element' => $element,
            'variant' => $variant,
            'offset' => $offset,
            'startTime' => round($startTime, 4),
            'duration' => round($duration, 4),
            'endTime' => round($endTime, 4),
            'spring' => $activeSpring,
        ];

        $this->cursor = $endTime;
        return $this;
    }

    public function getTracks(): array
    {
        return $this->tracks;
    }

    /**
     * Total span in seconds from 0 to when the last animation track completes.
     */
    public function getTotalDuration(): float
    {
        $maxEnd = 0.0;
        foreach ($this->tracks as $track) {
            if ($track['endTime'] > $maxEnd) {
                $maxEnd = $track['endTime'];
            }
        }
        return round($maxEnd, 4);
    }

    public function toArray(): array
    {
        return [
            'totalDuration' => $this->getTotalDuration(),
            'trackCount' => count($this->tracks),
            'tracks' => array_map(function ($track) {
                return [
                    'id' => $track['id'],
                    'variant' => $track['variant']->toArray(),
                    'startTime' => $track['startTime'],
                    'duration' => $track['duration'],
                    'endTime' => $track['endTime'],
                    'spring' => $track['spring']->toArray(),
                ];
            }, $this->tracks),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
