<?php

namespace ArieTimmerman\Laravel\SCIMServer\Parser;

use ArieTimmerman\Laravel\SCIMServer\Filter\Ast\AttributePath as AstAttributePath;

class AttributePath
{
    public function __construct(public AstAttributePath $path)
    {
    }

    public function getAttributeNames(): array
    {
        return $this->path->getAttributeNames();
    }

    public function shiftAttributeName(): ?string
    {
        return $this->path->shift();
    }
}
