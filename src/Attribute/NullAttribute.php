<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

use ArieTimmerman\Laravel\SCIMServer\Attribute\AttributeMapping;
use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;

class NullAttribute extends AttributeMapping{
    
    public function read(&$object){
        return null;
    }
    
    public function write($value, &$object){
        throw new SCIMException("No mapping defined for ...");
    }
    
}