<?php

namespace ArieTimmerman\Laravel\SCIMServer\Filter\Ast;

class Negation extends Factor
{
    public function __construct(private Filter $filter)
    {
    }

    public function getFilter(): Filter
    {
        return $this->filter;
    }

    public function __toString(): string
    {
        return sprintf('not (%s)', $this->filter);
    }

    public function dump(): array
    {
        return [
            'Negation' => $this->filter->dump(),
        ];
    }
}
