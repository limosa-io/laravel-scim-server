<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

class EloquentAttributeMapping extends AttributeMapping
{
    public function read(&$object)
    {
        return $this->read ? ($this->read)($object) : self::eloquentAttributeToString($object->{$this->eloquentReadAttribute});
    }
}
