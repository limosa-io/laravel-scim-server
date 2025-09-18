<?php

namespace ArieTimmerman\Laravel\SCIMServer\Parser;

use ArieTimmerman\Laravel\SCIMServer\Filter\Ast\ValuePath as AstValuePath;

class ValuePath
{
    public AttributePath $attributePath;

    public ?Filter $filter;

    public function __construct(public AstValuePath $path)
    {
        $this->attributePath = new AttributePath($this->path->getAttributePath());
        $this->filter = new Filter($this->path->getFilter());
    }

    public function getAttributePath(): AttributePath
    {
        return $this->attributePath;
    }

    public function setAttributePath(AttributePath $attributePath): void
    {
        $this->attributePath = $attributePath;
    }

    public function getFilter(): ?Filter
    {
        return $this->filter;
    }

    public function setFilter(?Filter $filter): void
    {
        $this->filter = $filter;
    }
}
