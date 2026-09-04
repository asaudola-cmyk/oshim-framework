<?php
declare(strict_types=1);

namespace Oshim\Ui\Dsl;

class Paragraph extends Element
{
    public function __construct(string $text = '')
    {
        parent::__construct('p');
        if ($text !== '') {
            $this->text($text);
        }
    }
}
