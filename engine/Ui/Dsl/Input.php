<?php
declare(strict_types=1);

namespace Oshim\Ui\Dsl;

class Input extends Element
{
    public function __construct(string $type = 'text', string $name = '', mixed $value = '')
    {
        parent::__construct('input');
        $this->isSelfClosing = true;
        $this->attr('type', $type);
        if ($name !== '') {
            $this->attr('name', $name);
        }
        if ($value !== '') {
            $this->attr('value', $value);
        }
    }

    public static function hidden(string $name, string $value): static
    {
        return new static('hidden', $name, $value);
    }

    public static function textInput(string $name, string $placeholder = ''): static
    {
        $el = new static('text', $name);
        if ($placeholder !== '') {
            $el->attr('placeholder', $placeholder);
        }
        return $el;
    }
}
