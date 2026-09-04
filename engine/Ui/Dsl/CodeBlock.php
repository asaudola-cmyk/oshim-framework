<?php
declare(strict_types=1);

namespace Oshim\Ui\Dsl;

class CodeBlock extends Element
{
    public function __construct(string $code = '')
    {
        parent::__construct('code');
        if ($code !== '') {
            $this->text($code);
        }
    }
}
