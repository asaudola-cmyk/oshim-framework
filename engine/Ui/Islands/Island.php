<?php
declare(strict_types=1);

namespace Oshim\Ui\Islands;

use JsonSerializable;

/**
 * Island component wrapper for selective, progressive client hydration.
 */
class Island implements JsonSerializable
{
    private string $name;
    private string $renderedHtml;
    private array $props;
    private HydrationStrategy $strategy;
    private ?string $mediaQuery;
    private string $id;

    public function __construct(
        string $name,
        string $renderedHtml,
        array $props = [],
        HydrationStrategy $strategy = HydrationStrategy::VISIBLE,
        ?string $mediaQuery = null,
        ?string $id = null
    ) {
        $this->name = $name;
        $this->renderedHtml = $renderedHtml;
        $this->props = $props;
        $this->strategy = $strategy;
        $this->mediaQuery = $mediaQuery;
        $this->id = $id ?? ('island-' . substr(md5($name . uniqid('', true)), 0, 8));
    }

    public static function make(
        string $name,
        string $renderedHtml,
        array $props = [],
        HydrationStrategy $strategy = HydrationStrategy::VISIBLE
    ): self {
        return new self($name, $renderedHtml, $props, $strategy);
    }

    public function clientLoad(): self { $this->strategy = HydrationStrategy::LOAD; return $this; }
    public function clientIdle(): self { $this->strategy = HydrationStrategy::IDLE; return $this; }
    public function clientVisible(): self { $this->strategy = HydrationStrategy::VISIBLE; return $this; }
    public function clientMedia(string $query): self { $this->strategy = HydrationStrategy::MEDIA; $this->mediaQuery = $query; return $this; }

    public function getId(): string { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getStrategy(): HydrationStrategy { return $this->strategy; }

    public function render(): string
    {
        $propsJson = htmlspecialchars(json_encode($this->props, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
        $mediaAttr = $this->mediaQuery !== null ? sprintf(' data-media="%s"', htmlspecialchars($this->mediaQuery, ENT_QUOTES)) : '';

        return sprintf(
            '<oshim-island id="%s" data-island="%s" data-strategy="%s" data-props=\'%s\'%s>%s</oshim-island>',
            htmlspecialchars($this->id, ENT_QUOTES),
            htmlspecialchars($this->name, ENT_QUOTES),
            $this->strategy->value,
            $propsJson,
            $mediaAttr,
            $this->renderedHtml
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'strategy' => $this->strategy->value,
            'props' => $this->props,
            'html' => $this->renderedHtml,
        ];
    }

    public function __toString(): string
    {
        return $this->render();
    }
}
