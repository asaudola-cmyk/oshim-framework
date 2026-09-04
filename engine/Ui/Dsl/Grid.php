<?php
declare(strict_types=1);

namespace Oshim\Ui\Dsl;

class Grid extends Element
{
    public function __construct(int $columns = 3, string $gap = '1.5rem')
    {
        parent::__construct('div');
        $this->style(Style::make()->grid("repeat(auto-fit, minmax(280px, 1fr))", $gap));
        $this->class('oshim-grid-' . $columns);
    }

    public static function cols(int $columns = 3, string $gap = '1.5rem'): static
    {
        return new static($columns, $gap);
    }
}
