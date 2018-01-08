<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

use ArieTimmerman\Laravel\SCIMServer\Attribute\ConstantAttributeMapping;
use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;

class Object extends ConstantAttributeMapping{
    
    public function read(&$object){
        return null;
    }
    
    public function write($value, &$object){
        throw new SCIMException("No mapping defined");
    }
    
    public function getSubNode($sub){
        
        if( array_key_exists($sub, $this->eloquentAttribute) ) {
            return AttributeMapping::ensureAttributeMappingObject($this->eloquentAttribute[$sub]);
        }else{
            var_dump($sub);
            var_dump($this->eloquentAttribute);
            throw new SCIMException("Not found!");
        }
        
    }
    
}