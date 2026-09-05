<?php
namespace Oshim\Ui\Dsl;
class H1 extends Element { 
    public function __construct(string $text = '') { 
        parent::__construct('h1'); 
        $this->text = $text; 
    } 
    public static function create(string $text = ''): static { return new static($text); }
}
