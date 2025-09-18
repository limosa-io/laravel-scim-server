<?php

namespace ArieTimmerman\Laravel\SCIMServer\Parser;

use ArieTimmerman\Laravel\SCIMServer\Filter\Ast\ComparisonExpression;
use ArieTimmerman\Laravel\SCIMServer\Filter\Ast\Filter as AstFilter;

class Filter
{
    public function __construct(public AstFilter $filter)
    {
    }

    public function getComparisonExpression(): ?ComparisonExpression
    {
        return $this->filter instanceof ComparisonExpression ? $this->filter : null;
    }
}
