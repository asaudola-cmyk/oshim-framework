<?php
declare(strict_types=1);

namespace Oshim\Ui\Dsl;

class Table extends Element
{
    public function __construct()
    {
        parent::__construct('table');
        $this->class('w-full text-left border-collapse');
    }

    public static function table(): static
    {
        return new static();
    }
}

class Thead extends Element
{
    public function __construct()
    {
        parent::__construct('thead');
    }
    public static function thead(): static { return new static(); }
}

class Tbody extends Element
{
    public function __construct()
    {
        parent::__construct('tbody');
    }
    public static function tbody(): static { return new static(); }
}

class Tr extends Element
{
    public function __construct()
    {
        parent::__construct('tr');
    }
    public static function tr(): static { return new static(); }
}

class Th extends Element
{
    public function __construct(string $text = '')
    {
        parent::__construct('th');
        if ($text !== '') {
            $this->text($text);
        }
    }
    public static function th(string $text = ''): static { return new static($text); }
}

class Td extends Element
{
    public function __construct(string $text = '')
    {
        parent::__construct('td');
        if ($text !== '') {
            $this->text($text);
        }
    }
    public static function td(string $text = ''): static { return new static($text); }
}
