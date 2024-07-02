<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;
use ArieTimmerman\Laravel\SCIMServer\Parser\Path;
use Illuminate\Database\Eloquent\Model;

class Eloquent extends Attribute
{
    protected $attribute;

    public function __construct($name, $attribute = null, $schemaNode = false)
    {
        parent::__construct($name, $schemaNode);

        $this->attribute = $attribute ?? $name;
        $this->setSortAttribute($this->attribute);
    }

    public function read(&$object)
    {
        $value = $object->{$this->attribute};

        if ($value instanceof \Carbon\Carbon) {
                $value = $value->format('c');
        }
        return $value;
    }

    public function add($value, Model &$object)
    {
        $object->{$this->attribute} = $value;
        $this->dirty = true;
    }

    public function replace($value, Model &$object, $path = null)
    {
        $object->{$this->attribute} = $value;
        $this->dirty = true;
    }

    public function patch($operation, $value, Model &$object, ?Path $path = null)
    {
        if($path->isNotEmpty()){
            throw new SCIMException('path operation not support for eloquent type attributes');
        }

        if($operation == 'replace' || $operation == 'add'){
            $object->{$this->attribute} = $value;
            $this->dirty = true;
        }else if ($operation == 'remove'){
            $object->{$this->attribute} = null;
            $this->dirty = true;
        }
    }
}
