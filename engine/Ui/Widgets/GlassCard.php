<?php
declare(strict_types=1);

namespace Oshim\Ui\Widgets;

use Oshim\Ui\Dsl\Element;
use Oshim\Ui\Dsl\Div;
use Oshim\Ui\Dsl\Heading;
use Oshim\Ui\Dsl\Badge;

class GlassCard extends Element
{
    public function __construct(string $title = '', ?string $badge = null)
    {
        parent::__construct('div');
        $this->class('oshim-glass-card');

        if ($badge !== null) {
            $this->child(Badge::make()->text($badge));
        }

        if ($title !== '') {
            $this->child(Heading::h3($title)->style('margin: 0.5rem 0 1rem 0; color: #f8fafc; font-size: 1.25rem; font-weight: 700;'));
        }
    }

    public static function widget(string $title = '', ?string $badge = null): static
    {
        return new static($title, $badge);
    }
}
