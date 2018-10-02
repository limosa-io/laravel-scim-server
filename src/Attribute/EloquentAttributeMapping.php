<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

class EloquentAttributeMapping extends AttributeMapping {

    public function read(&$object){
        return self::eloquentAttributeToString($object->{$this->eloquentReadAttribute}); 
    }
}