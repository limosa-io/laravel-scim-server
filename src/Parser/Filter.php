<?php

namespace ArieTimmerman\Laravel\SCIMServer\Parser;

use Tmilos\ScimFilterParser\Ast\ComparisonExpression;
use Tmilos\ScimFilterParser\Ast\Filter as AstFilter;

class Filter
{
    public function __construct(public AstFilter $filter)
    {
    }

    public function getComparisonExpression(): ComparisonExpression
    {
        return $this->filter instanceof ComparisonExpression ? $this->filter : null;
    }
}
