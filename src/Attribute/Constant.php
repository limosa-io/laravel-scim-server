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
        if (json_encode($value) != json_encode($this->read($object))) {
            throw (new SCIMException(sprintf('Write to "%s" is not supported, tried to change "%s" to "%s"', $this->getFullKey(), json_encode($this->read($object)), json_encode($value))))->setCode(500)->setScimType('mutability');
        }

        $this->dirty = true;
    }
}
