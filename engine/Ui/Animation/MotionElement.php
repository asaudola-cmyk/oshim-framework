<?php
declare(strict_types=1);

namespace Oshim\Ui\Animation;

use JsonSerializable;
use Oshim\Ui\Dsl\Element;

/**
 * Server-Driven Declarative Motion Element.
 *
 * Framer Motion equivalent declarative physics component in pure PHP.
 * Supports spring physics, variants, keyframes, scroll triggers, and stagger orchestration.
 */
class MotionElement implements JsonSerializable
{
    protected string $tag = 'div';
    protected string $id;
    /** @var array<string, mixed> */
    protected array $attributes = [];
    /** @var list<MotionElement|Element|string> */
    protected array $children = [];
    protected ?string $textContent = null;

    protected ?AnimationVariant $initial = null;
    protected ?AnimationVariant $animate = null;
    protected ?AnimationVariant $exit = null;
    protected ?AnimationVariant $whileHover = null;
    protected ?AnimationVariant $whileTap = null;
    protected ?AnimationVariant $whileInView = null;

    protected ?Spring $spring = null;
    protected ?ScrollTrigger $scrollTrigger = null;
    protected ?Stagger $stagger = null;
    protected ?Keyframes $keyframes = null;
    /** @var array<string, AnimationVariant> */
    protected array $variants = [];

    protected bool $cssKeyframeMode = true;

    public function __construct(string $tag = 'div', ?string $id = null)
    {
        $this->tag = $tag;
        $this->id = $id ?? 'motion_' . bin2hex(random_bytes(4));
        $this->spring = Spring::default();
    }

    public static function make(string $tag = 'div', ?string $id = null): self
    {
        return new self($tag, $id);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function id(string $id): self
    {
        $this->id = $id;
        $this->attributes['id'] = $id;
        return $this;
    }

    public function getTag(): string
    {
        return $this->tag;
    }

    public function class(string ...$classes): self
    {
        $existing = (string)($this->attributes['class'] ?? '');
        $newClasses = implode(' ', array_filter($classes));
        $this->attributes['class'] = trim($existing . ' ' . $newClasses);
        return $this;
    }

    public function style(string|array $style): self
    {
        $existing = (string)($this->attributes['style'] ?? '');
        $styleStr = is_array($style)
            ? implode('; ', array_map(fn($k, $v) => "{$k}: {$v}", array_keys($style), $style))
            : $style;

        $this->attributes['style'] = trim($existing . '; ' . trim($styleStr, '; '), '; ');
        return $this;
    }

    public function attr(string $name, mixed $value): self
    {
        $this->attributes[$name] = $value;
        return $this;
    }

    public function initial(array|AnimationVariant $variant): self
    {
        $this->initial = $variant instanceof AnimationVariant
            ? $variant
            : new AnimationVariant('initial', $variant);
        return $this;
    }

    public function animate(array|AnimationVariant $variant): self
    {
        $this->animate = $variant instanceof AnimationVariant
            ? $variant
            : new AnimationVariant('animate', $variant);
        return $this;
    }

    public function exit(array|AnimationVariant $variant): self
    {
        $this->exit = $variant instanceof AnimationVariant
            ? $variant
            : new AnimationVariant('exit', $variant);
        return $this;
    }

    public function whileHover(array|AnimationVariant $variant): self
    {
        $this->whileHover = $variant instanceof AnimationVariant
            ? $variant
            : new AnimationVariant('hover', $variant);
        return $this;
    }

    public function whileTap(array|AnimationVariant $variant): self
    {
        $this->whileTap = $variant instanceof AnimationVariant
            ? $variant
            : new AnimationVariant('tap', $variant);
        return $this;
    }

    public function whileInView(array|AnimationVariant $variant): self
    {
        $this->whileInView = $variant instanceof AnimationVariant
            ? $variant
            : new AnimationVariant('inView', $variant);
        return $this;
    }

    public function spring(Spring $spring): self
    {
        $this->spring = $spring;
        return $this;
    }

    public function transition(Spring|array $config): self
    {
        if ($config instanceof Spring) {
            $this->spring = $config;
        } elseif (is_array($config)) {
            $this->spring = new Spring(
                stiffness: (float)($config['stiffness'] ?? 100.0),
                damping: (float)($config['damping'] ?? 10.0),
                mass: (float)($config['mass'] ?? 1.0),
                initialVelocity: (float)($config['velocity'] ?? 0.0),
                delay: (float)($config['delay'] ?? 0.0)
            );
        }
        return $this;
    }

    public function scrollTrigger(ScrollTrigger $trigger): self
    {
        $this->scrollTrigger = $trigger;
        return $this;
    }

    public function stagger(Stagger $stagger): self
    {
        $this->stagger = $stagger;
        return $this;
    }

    public function keyframes(Keyframes $keyframes): self
    {
        $this->keyframes = $keyframes;
        return $this;
    }

    /**
     * Set multiple named variants (e.g. ['hidden' => ..., 'visible' => ...]).
     *
     * @param array<string, array|AnimationVariant> $variants
     */
    public function variants(array $variants): self
    {
        foreach ($variants as $name => $v) {
            $this->variants[$name] = $v instanceof AnimationVariant
                ? $v
                : new AnimationVariant((string)$name, (array)$v);
        }
        return $this;
    }

    public function withCssKeyframes(bool $enable = true): self
    {
        $this->cssKeyframeMode = $enable;
        return $this;
    }

    public function child(MotionElement|Element|string|null $child): self
    {
        if ($child !== null) {
            $this->children[] = $child;
        }
        return $this;
    }

    /**
     * @param list<MotionElement|Element|string> $children
     */
    public function children(array $children): self
    {
        foreach ($children as $child) {
            $this->child($child);
        }
        return $this;
    }

    public function text(string $content): self
    {
        $this->textContent = htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return $this;
    }

    public function raw(string $html): self
    {
        $this->textContent = $html;
        return $this;
    }

    public function getInitial(): ?AnimationVariant
    {
        return $this->initial;
    }

    public function getAnimate(): ?AnimationVariant
    {
        return $this->animate;
    }

    public function getSpring(): ?Spring
    {
        return $this->spring;
    }

    public function getScrollTrigger(): ?ScrollTrigger
    {
        return $this->scrollTrigger;
    }

    public function getStagger(): ?Stagger
    {
        return $this->stagger;
    }

    public function getKeyframes(): ?Keyframes
    {
        return $this->keyframes;
    }

    public function getVariants(): array
    {
        return $this->variants;
    }

    /**
     * Build or resolve the Keyframes instance for this motion element.
     */
    public function resolveKeyframes(): ?Keyframes
    {
        if ($this->keyframes !== null) {
            return $this->keyframes;
        }

        if ($this->initial !== null && $this->animate !== null) {
            $spring = $this->animate->getSpring() ?? $this->spring ?? Spring::default();
            $kfName = 'oshim_kf_' . $this->id;

            return Keyframes::fromSpring(
                $kfName,
                $this->initial->getProperties(),
                $this->animate->getProperties(),
                $spring,
                25
            );
        }

        return null;
    }

    /**
     * Convert to standard Oshim Dsl Element.
     */
    public function toElement(): Element
    {
        $el = Element::make($this->tag);
        $el->id($this->id);

        foreach ($this->attributes as $k => $v) {
            if ($k === 'id') {
                continue;
            }
            $el->attr($k, $v);
        }

        if ($this->textContent !== null) {
            $el->raw($this->textContent);
        }

        foreach ($this->children as $child) {
            if ($child instanceof MotionElement) {
                $el->child($child->toElement());
            } else {
                $el->child($child);
            }
        }

        return $el;
    }

    /**
     * Render the animated motion element to HTML with declarative attributes and scoped CSS.
     */
    public function render(bool $includeStyles = true): string
    {
        $attrs = $this->attributes;
        $attrs['id'] = $this->id;
        $attrs['data-motion'] = 'true';
        $attrs['data-motion-id'] = $this->id;

        // Apply scroll triggers if present
        if ($this->scrollTrigger !== null) {
            foreach ($this->scrollTrigger->toDataAttributes() as $k => $v) {
                $attrs[$k] = $v;
            }
        }

        // Apply stagger config if present
        if ($this->stagger !== null) {
            foreach ($this->stagger->toDataAttributes() as $k => $v) {
                $attrs[$k] = $v;
            }
        }

        // Encode variants for client hydration
        if (!empty($this->variants)) {
            $attrs['data-variants'] = htmlspecialchars(
                (string)json_encode($this->variants, JSON_UNESCAPED_SLASHES),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );
        }

        if ($this->whileHover !== null) {
            $attrs['data-while-hover'] = htmlspecialchars(
                (string)json_encode($this->whileHover->getProperties(), JSON_UNESCAPED_SLASHES),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );
        }

        if ($this->whileTap !== null) {
            $attrs['data-while-tap'] = htmlspecialchars(
                (string)json_encode($this->whileTap->getProperties(), JSON_UNESCAPED_SLASHES),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );
        }

        if ($this->whileInView !== null) {
            $attrs['data-while-in-view'] = htmlspecialchars(
                (string)json_encode($this->whileInView->getProperties(), JSON_UNESCAPED_SLASHES),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );
        }

        if ($this->spring !== null) {
            $attrs['data-spring'] = htmlspecialchars(
                (string)json_encode($this->spring->toArray(), JSON_UNESCAPED_SLASHES),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );
        }

        $styleBlock = '';
        $inlineStyles = [];

        if (isset($attrs['style'])) {
            $inlineStyles[] = rtrim((string)$attrs['style'], '; ');
        }

        // Initial state CSS to avoid layout shift (FOUC)
        if ($this->initial !== null) {
            $initStyle = $this->initial->toStyleString();
            if ($initStyle !== '') {
                $inlineStyles[] = rtrim($initStyle, '; ');
            }
        }

        // Generate Spring Keyframe CSS if enabled
        $kf = $this->resolveKeyframes();
        if ($kf !== null && $this->cssKeyframeMode) {
            $kfCss = $kf->toCss();
            $animCss = $kf->toAnimationCss();

            $styleBlock = $includeStyles ? "<style>{$kfCss}</style>" : '';
            $inlineStyles[] = "animation: {$animCss}";
            $inlineStyles[] = "will-change: transform, opacity";
        }

        if (!empty($inlineStyles)) {
            $attrs['style'] = implode('; ', $inlineStyles) . ';';
        }

        // Process children with stagger if defined
        $renderedChildren = '';
        $childCount = count($this->children);

        foreach ($this->children as $index => $child) {
            if ($child instanceof MotionElement) {
                if ($this->stagger !== null && $child->spring !== null) {
                    $childDelay = $this->stagger->calculateDelay($index, $childCount);
                    $child->spring = $child->spring->withDelay($childDelay);
                }
                $renderedChildren .= $child->render($includeStyles);
            } elseif ($child instanceof Element) {
                $renderedChildren .= $child->render();
            } elseif (is_string($child)) {
                $renderedChildren .= $child;
            }
        }

        if ($this->textContent !== null) {
            $renderedChildren .= $this->textContent;
        }

        // Format HTML attributes
        $attrPairs = [];
        foreach ($attrs as $k => $v) {
            if ($v === true) {
                $attrPairs[] = htmlspecialchars($k);
            } elseif ($v !== false && $v !== null) {
                $attrPairs[] = sprintf('%s="%s"', htmlspecialchars($k), (string)$v);
            }
        }

        $attrStr = !empty($attrPairs) ? ' ' . implode(' ', $attrPairs) : '';
        $html = "{$styleBlock}<{$this->tag}{$attrStr}>{$renderedChildren}</{$this->tag}>";

        return $html;
    }

    public function __toString(): string
    {
        return $this->render();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tag' => $this->tag,
            'attributes' => $this->attributes,
            'initial' => $this->initial?->toArray(),
            'animate' => $this->animate?->toArray(),
            'exit' => $this->exit?->toArray(),
            'whileHover' => $this->whileHover?->toArray(),
            'whileTap' => $this->whileTap?->toArray(),
            'whileInView' => $this->whileInView?->toArray(),
            'spring' => $this->spring?->toArray(),
            'scrollTrigger' => $this->scrollTrigger?->toArray(),
            'stagger' => $this->stagger?->toArray(),
            'keyframes' => $this->keyframes?->toArray(),
            'variants' => array_map(fn($v) => $v->toArray(), $this->variants),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
