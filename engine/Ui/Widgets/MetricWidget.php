<?php
declare(strict_types=1);

namespace Oshim\Ui\Widgets;

use Oshim\Ui\Dsl\Element;
use Oshim\Ui\Dsl\Div;
use Oshim\Ui\Dsl\Span;

class MetricWidget extends Element
{
    public function __construct(string $label, string|int $value, string $color = '#00f2fe')
    {
        parent::__construct('div');
        $this->class('oshim-glass-card');

        $labelEl = Div::make()->style('color: #94a3b8; font-size: 0.85rem;')->text($label);
        $valEl = Div::make()->style("font-size: 2rem; font-weight: 800; color: {$color}; margin-top: 0.5rem;")->text((string)$value);

        $this->child($labelEl);
        $this->child($valEl);
    }

    public static function makeMetric(string $label, string|int $value, string $color = '#00f2fe'): static
    {
        return new static($label, $value, $color);
    }
}
