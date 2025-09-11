<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;
use Illuminate\Database\Eloquent\Model;
use ArieTimmerman\Laravel\SCIMServer\Parser\Path;

class Constant extends Attribute
{
    public function __construct($name, protected $value)
    {
        parent::__construct($name);
    }

    protected function doRead(&$object, $attributes = [])
    {
        return $this->value;
    }

    public function add($value, &$object)
    {
        return $this->replace($value, $object);
    }

    public function replace($value, &$object, $path = null)
    {
        $current = json_encode($this->read($object)?->value);

        if (json_encode($value) != $current) {
            throw new SCIMException(sprintf('Write to "%s" is not supported, tried to change "%s" to "%s"', $this->getFullKey(), $current, json_encode($value)))->setCode(403)->setScimType('mutability');
        }

        $this->dirty = true;
    }

    public function patch($operation, $value, Model &$object, ?Path $path = null)
    {
        throw new SCIMException('Patch operation not supported for constant attributes');
    }
}
