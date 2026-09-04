<?php
declare(strict_types=1);

namespace Oshim\Ui\Dsl;

class Span extends Element
{
    public function __construct(string $text = '')
    {
        parent::__construct('span');
        if ($text !== '') {
            $this->text($text);
        }
    }
}
