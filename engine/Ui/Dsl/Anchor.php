<?php
declare(strict_types=1);

namespace Oshim\Ui\Dsl;

class Anchor extends Element
{
    public function __construct(string $href = '#', string $text = '')
    {
        parent::__construct('a');
        $this->attr('href', $href);
        if ($text !== '') {
            $this->text($text);
        }
    }

    public static function link(string $href, string $text): static
    {
        return new static($href, $text);
    }
}
