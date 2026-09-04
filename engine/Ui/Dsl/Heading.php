<?php
declare(strict_types=1);

namespace Oshim\Ui\Dsl;

class Heading extends Element
{
    public function __construct(int $level = 1, string $text = '')
    {
        parent::__construct('h' . max(1, min(6, $level)));
        if ($text !== '') {
            $this->text($text);
        }
    }

    public static function h1(string $text = ''): static
    {
        return new static(1, $text);
    }

    public static function h2(string $text = ''): static
    {
        return new static(2, $text);
    }

    public static function h3(string $text = ''): static
    {
        return new static(3, $text);
    }

    public static function h4(string $text = ''): static
    {
        return new static(4, $text);
    }
}
