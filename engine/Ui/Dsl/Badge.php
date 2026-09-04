<?php
declare(strict_types=1);

namespace Oshim\Ui\Dsl;

class Badge extends Element
{
    public function __construct(string $text = '', string $color = '#00f2fe')
    {
        parent::__construct('span');
        $this->class('oshim-glow-badge');
        if ($text !== '') {
            $this->text($text);
        }
    }
}
