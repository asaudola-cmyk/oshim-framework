<?php
declare(strict_types=1);

namespace Oshim\Ui\Dsl;

class Button extends Element
{
    public function __construct(string $type = 'button', string $text = '')
    {
        parent::__construct('button');
        $this->attr('type', $type);
        if ($text !== '') {
            $this->text($text);
        }
    }

    public static function submit(string $text): static
    {
        return new static('submit', $text);
    }
}
