<?php
declare(strict_types=1);

namespace Oshim\Ui\Dsl;

class Form extends Element
{
    public function __construct(string $method = 'POST', string $action = '')
    {
        parent::__construct('form');
        $this->attr('method', $method);
        if ($action !== '') {
            $this->attr('action', $action);
        }
    }

    public static function post(string $action): static
    {
        return new static('POST', $action);
    }

    public static function get(string $action): static
    {
        return new static('GET', $action);
    }
}
