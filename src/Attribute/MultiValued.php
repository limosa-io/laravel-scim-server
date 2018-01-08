<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

use ArieTimmerman\Laravel\SCIMServer\Attribute\ConstantAttributeMapping;

class MultiValued extends ConstantAttributeMapping{
    
    public function getSubNode($key){
        
        return $this->eloquentAttribute[$key];
        
    }
    
}