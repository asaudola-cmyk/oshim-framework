<?php
namespace Oshim\Ui\Dsl;
class Input extends Element { 
    public function __construct(string $type = 'text', string $name = '', mixed $value = '') { 
        parent::__construct('input'); 
        $this->attr('type', $type);
        if ($name !== '') {
            $this->attr('name', $name);
        }
        if ($value !== '') {
            $this->attr('value', (string)$value);
        }
    }
    public static function create(string $type = 'text'): static { return new static($type); }
    public static function hidden(string $name, string $value): static { return new static('hidden', $name, $value); }
    public static function textInput(string $name, string $placeholder = ''): static {
        $el = new static('text', $name);
        if ($placeholder !== '') {
            $el->attr('placeholder', $placeholder);
        }
        return $el;
    }
}
