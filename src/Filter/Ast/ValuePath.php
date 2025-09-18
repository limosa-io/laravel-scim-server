<?php

namespace ArieTimmerman\Laravel\SCIMServer\Filter\Ast;

class ValuePath extends Factor
{
    public function __construct(private AttributePath $attributePath, private Filter $filter)
    {
    }

    public function getAttributePath(): AttributePath
    {
        return $this->attributePath;
    }

    public function getFilter(): Filter
    {
        return $this->filter;
    }

    public function __toString(): string
    {
        return sprintf('%s[%s]', $this->attributePath, $this->filter);
    }

    public function dump(): array
    {
        return [
            'ValuePath' => [
                $this->attributePath->dump(),
                $this->filter->dump(),
            ],
        ];
    }
}
