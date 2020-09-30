<?php

/**
 * @author Felix Huang <yelfivehuang@gmail.com>
 * @date 2017-11-24
 */

namespace Overlord\Model\Support;

class DumperExpression
{
    public string $expression;

    public function __construct(string $expression)
    {
        $this->expression = $expression;
    }
}