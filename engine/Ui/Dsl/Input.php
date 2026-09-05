<?php
namespace Oshim\Ui\Dsl;
class Input extends Element { 
    public function __construct(string $type = 'text') { 
        parent::__construct('input'); 
        $this->attr('type', $type);
    }
    public static function create(string $type = 'text'): static { return new static($type); }
}
