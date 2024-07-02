<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;

class Constant extends Attribute
{
    protected $value;

    public function __construct($name, $value, $schemaNode = false)
    {
        parent::__construct($name, $schemaNode);

        $this->value = $value;
    }

    public function read(&$object)
    {
        return $this->value;
    }

    public function add($value, &$object)
    {
        throw (new SCIMException(sprintf('Write to "%s" is not supported', $this->getFullKey())))->setCode(500)->setScimType('mutability');
    }

    public function replace($value, &$object, $path = null)
    {
        throw (new SCIMException(sprintf('Write to "%s" is not supported', $this->getFullKey())))->setCode(500)->setScimType('mutability');
    }
}
