<?php
namespace Oshim\Ui\Dsl;
class Button extends Element { 
    public function __construct(string $type = 'button', string $text = '') { 
        parent::__construct('button'); 
        if ($text === '' && $type !== 'button' && $type !== 'submit' && $type !== 'reset') {
            $this->attr('type', 'button');
            $this->text($type);
        } else {
            $this->attr('type', $type);
            if ($text !== '') {
                $this->text($text);
            }
        }
    }
    public static function create(string $text = ''): static { return new static('button', $text); }
    public static function submit(string $text = ''): static { return new static('submit', $text); }
}
