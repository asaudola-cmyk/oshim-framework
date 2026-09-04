<?php
declare(strict_types=1);

namespace Oshim\Ui\Dsl;

class Select extends Element
{
    public function __construct(string $name = '')
    {
        parent::__construct('select');
        if ($name !== '') {
            $this->attr('name', $name);
        }
    }

    public function options(array $options, ?string $selected = null): static
    {
        foreach ($options as $val => $label) {
            $opt = Option::make()->attr('value', $val)->text((string)$label);
            if ((string)$val === (string)$selected) {
                $opt->attr('selected', true);
            }
            $this->child($opt);
        }
        return $this;
    }
}
